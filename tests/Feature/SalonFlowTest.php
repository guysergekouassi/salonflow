<?php

namespace Tests\Feature;

use App\Models\Categorie;
use App\Models\Service;
use App\Models\Vendeuse;
use App\Models\Vente;
use App\Services\KpiService;
use App\Services\PinService;
use App\Support\Periode;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalonFlowTest extends TestCase
{
    use RefreshDatabase;

    private Vendeuse $awa;

    private Vendeuse $fatou;

    private Service $brushing;

    private Service $tresses;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 10:00:00'); // jeudi

        // Les migrations installent le catalogue du salon : on part d'un catalogue de test réduit
        Service::query()->delete();
        Categorie::query()->delete();

        $this->awa = Vendeuse::create(['nom' => 'Awa', 'ordre' => 1]);
        $this->fatou = Vendeuse::create(['nom' => 'Fatou', 'ordre' => 2]);

        $categorie = Categorie::create(['nom' => 'Coiffure', 'couleur' => '#2563eb']);
        $this->brushing = Service::create(['categorie_id' => $categorie->id, 'code' => 'COI-001', 'nom' => 'Brushing', 'prix' => 3000]);
        $this->tresses = Service::create(['categorie_id' => $categorie->id, 'code' => 'TRE-001', 'nom' => 'Tresses', 'prix' => 10000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vendre(?Vendeuse $vendeuse, array $lignes, string $mode = 'especes', ?int $recu = null)
    {
        return $this->postJson('/caisse', [
            'vendeuse_id' => $vendeuse?->id,
            'lignes' => $lignes,
            'mode_paiement' => $mode,
            'montant_recu' => $recu,
        ]);
    }

    private function ouvrirModeGerante(): void
    {
        $this->withSession(['mode_gerante_jusqua' => now()->addMinutes(10)->timestamp]);
    }

    public function test_la_caisse_s_ouvre_sans_connexion_avec_kpi_du_jour_et_services(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Brushing')
            ->assertSee('Awa')
            ->assertSee('Fatou')
            ->assertSee("Chiffre d'affaires du jour", false)
            ->assertSee('10 000 FCFA');

        foreach (['/dashboard', '/ventes', '/services', '/tickets/'.Vente::first()->id] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/login')->assertNotFound();
    }

    public function test_la_vente_est_calculee_avec_les_prix_de_la_base(): void
    {
        $this->vendre($this->awa, [
            ['service_id' => $this->brushing->id, 'quantite' => 2, 'prix' => 1],
            ['service_id' => $this->tresses->id, 'quantite' => 1],
        ], 'especes', 20000)
            ->assertCreated()
            ->assertJsonPath('numero', 'T-20261001-0001')
            ->assertJsonPath('total', 16000);

        $vente = Vente::with('lignes')->first();
        $this->assertSame(16000, $vente->total);
        $this->assertSame(4000, $vente->monnaie_rendue);
        $this->assertSame($this->awa->id, $vente->vendeuse_id);
        $this->assertCount(2, $vente->lignes);

        $this->get('/tickets/'.$vente->id)->assertSee('Servi par : Awa');
    }

    public function test_la_vendeuse_est_obligatoire_et_doit_etre_active(): void
    {
        $ligne = [['service_id' => $this->brushing->id, 'quantite' => 1]];

        $this->vendre(null, $ligne)->assertUnprocessable()->assertJsonValidationErrors('vendeuse_id');

        $this->fatou->update(['actif' => false]);
        $this->vendre($this->fatou, $ligne)->assertUnprocessable()->assertJsonValidationErrors('vendeuse_id');

        $this->assertSame(0, Vente::count());
    }

    public function test_sans_aucune_vendeuse_la_vente_passe_sans_nom(): void
    {
        Vendeuse::query()->update(['actif' => false]);

        $this->vendre(null, [['service_id' => $this->brushing->id, 'quantite' => 1]])->assertCreated();
        $this->assertNull(Vente::first()->vendeuse_id);
    }

    public function test_un_meme_service_clique_plusieurs_fois_est_regroupe(): void
    {
        $this->vendre($this->awa, [
            ['service_id' => $this->brushing->id, 'quantite' => 1],
            ['service_id' => $this->brushing->id, 'quantite' => 1],
        ])->assertCreated();

        $vente = Vente::with('lignes')->first();
        $this->assertCount(1, $vente->lignes);
        $this->assertSame(2, $vente->lignes->first()->quantite);
        $this->assertSame(6000, $vente->montant_recu); // montant exact par défaut
        $this->assertSame(0, $vente->monnaie_rendue);
    }

    public function test_le_catalogue_du_salon_est_installe(): void
    {
        (new CatalogueSeeder)->remplacer();

        $this->assertSame(22, Service::count());
        $this->assertSame(['Onglerie', 'Décorations', 'Pédicure, manucure & visage', 'Coiffure, tresses & soins', 'Cils'],
            Categorie::orderBy('ordre')->pluck('nom')->all());
        $this->assertSame(30000, Service::where('nom', 'Pose gel capsules long')->value('prix'));
        $this->assertSame(10000, Service::where('nom', 'Pose extension de cils')->value('prix'));
        $this->assertSame(['Teinture cheveux', 'Tresse'], Service::where('prix_variable', true)->orderBy('nom')->pluck('nom')->all());

        $this->get('/')->assertSee('Pose capsules Acrygel long')->assertSee('Remplissage extension de cils');
    }

    public function test_remplacer_le_catalogue_garde_les_anciens_tickets(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        (new CatalogueSeeder)->remplacer();

        $ligne = Vente::first()->lignes()->first();
        $this->assertSame('Brushing', $ligne->libelle);
        $this->assertSame(3000, $ligne->total);
        $this->assertNull($ligne->service_id);
        $this->get('/dashboard')->assertOk()->assertSee('Brushing');
    }

    public function test_service_a_prix_variable_prend_le_prix_saisi_avec_un_minimum(): void
    {
        $tresse = Service::create([
            'categorie_id' => Categorie::first()->id, 'code' => 'COI-T', 'nom' => 'Tresse', 'prix' => 10000, 'prix_variable' => true,
        ]);

        // Prix saisi inférieur au minimum : refusé
        $this->vendre($this->awa, [['service_id' => $tresse->id, 'quantite' => 1, 'prix' => 8000]])
            ->assertUnprocessable()->assertJsonValidationErrors('lignes');

        // Deux tresses à des prix différents + un brushing dont le prix envoyé est ignoré
        $this->vendre($this->awa, [
            ['service_id' => $tresse->id, 'quantite' => 1, 'prix' => 15000],
            ['service_id' => $tresse->id, 'quantite' => 1, 'prix' => 25000],
            ['service_id' => $this->brushing->id, 'quantite' => 1, 'prix' => 1],
        ])->assertCreated()->assertJsonPath('total', 43000);

        $lignes = Vente::first()->lignes()->orderBy('id')->get();
        $this->assertSame([15000, 25000, 3000], $lignes->pluck('prix_unitaire')->all());

        // Sans prix saisi : prix minimum
        $this->vendre($this->awa, [['service_id' => $tresse->id, 'quantite' => 1]])->assertJsonPath('total', 10000);

        $this->get('/')->assertSee('"variable":true', false);
    }

    public function test_le_ticket_porte_le_logo_et_le_filigrane(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $url = '/tickets/'.Vente::first()->id;

        $this->get($url)->assertSee('images/logo-ticket.png')->assertSee('images/filigrane-ticket.png');

        // Le ticket garde sa largeur à l'impression (pas de pleine largeur sur A4), format valide
        $this->get($url)->assertDontSee('width: auto', false)->assertDontSee('mm auto', false);
        config(['salon.ticket.papier' => 'a4']);
        $this->get($url)->assertSee('size: A4 portrait', false)->assertSee('border: 1px dashed', false);

        config(['salon.ticket.logo' => false, 'salon.ticket.filigrane' => false]);
        $this->get($url)->assertDontSee('images/logo-ticket.png')->assertDontSee('images/filigrane-ticket.png');
    }

    public function test_seules_les_especes_sont_acceptees(): void
    {
        $ligne = [['service_id' => $this->brushing->id, 'quantite' => 1]];

        $this->vendre($this->awa, $ligne, 'mobile_money')->assertUnprocessable()->assertJsonValidationErrors('mode_paiement');
        $this->vendre($this->awa, $ligne, 'carte')->assertUnprocessable()->assertJsonValidationErrors('mode_paiement');
        $this->assertSame(0, Vente::count());

        $this->get('/')->assertDontSee('Mobile Money')->assertDontSee('>Carte<', false);

        // Un ancien ticket payé par Mobile Money garde son libellé dans l'historique
        $ancien = Vente::create(['numero' => 'T-20261001-0099', 'vendeuse_id' => $this->awa->id, 'total' => 3000, 'mode_paiement' => 'mobile_money']);
        $this->assertSame('Mobile Money', $ancien->libelleModePaiement());
        $this->get('/ventes')->assertSee('Mobile Money');
    }

    public function test_les_numeros_de_ticket_se_suivent_et_repartent_chaque_jour(): void
    {
        $ligne = [['service_id' => $this->brushing->id, 'quantite' => 1]];

        $this->vendre($this->awa, $ligne)->assertJsonPath('numero', 'T-20261001-0001');
        $this->vendre($this->fatou, $ligne)->assertJsonPath('numero', 'T-20261001-0002');

        Carbon::setTestNow('2026-10-02 09:00:00');
        $this->vendre($this->awa, $ligne)->assertJsonPath('numero', 'T-20261002-0001');
    }

    public function test_ticket_vide_service_desactive_ou_especes_insuffisantes_refuses(): void
    {
        $this->vendre($this->awa, [])->assertUnprocessable();

        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]], 'especes', 5000)
            ->assertUnprocessable()->assertJsonValidationErrors('montant_recu');

        $this->brushing->update(['actif' => false]);
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]])
            ->assertUnprocessable()->assertJsonValidationErrors('lignes');

        $this->assertSame(0, Vente::count());
    }

    public function test_changer_un_prix_ne_modifie_pas_les_anciens_tickets(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $this->brushing->update(['prix' => 3500, 'nom' => 'Brushing long']);

        $vente = Vente::with('lignes')->first();
        $this->assertSame(3000, $vente->total);
        $this->get('/tickets/'.$vente->id)->assertOk()->assertSee('Brushing')->assertSee('3 000 FCFA');
    }

    public function test_les_actions_sensibles_demandent_le_code_pin(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $vente = Vente::first();

        $this->get('/services/create')->assertRedirectContains('/gerante');
        $this->get('/services/'.$this->brushing->id.'/edit')->assertRedirectContains('/gerante');
        $this->get('/parametres')->assertRedirectContains('/gerante');
        $this->put('/services/'.$this->brushing->id, ['prix' => 1])->assertRedirectContains('/gerante');
        $this->delete('/services/'.$this->brushing->id)->assertRedirectContains('/gerante');
        $this->post("/ventes/{$vente->id}/annuler", ['motif' => 'x'])->assertRedirectContains('/gerante');
        $this->post('/vendeuses', ['nom' => 'Intruse'])->assertRedirectContains('/gerante');
        $this->put('/parametres/pin', ['pin' => '0000', 'pin_confirmation' => '0000'])->assertRedirectContains('/gerante');

        $this->assertSame(3000, $this->brushing->fresh()->prix);
        $this->assertNotNull($this->brushing->fresh());
        $this->assertNull($vente->fresh()->annulee_at);
        $this->assertFalse(Vendeuse::where('nom', 'Intruse')->exists());
        $this->assertTrue(app(PinService::class)->verifier('1234'));
    }

    public function test_code_pin_correct_ouvre_le_mode_gerante_et_mauvais_code_refuse(): void
    {
        $this->post('/gerante', ['pin' => '9999', 'retour' => url('/parametres')])->assertSessionHasErrors('pin');
        $this->get('/parametres')->assertRedirectContains('/gerante');

        $this->post('/gerante', ['pin' => '1234', 'retour' => url('/parametres')])->assertRedirect(url('/parametres'));
        $this->get('/parametres')->assertOk()->assertSee('Awa');

        // Retour vers un site externe refusé
        $this->post('/gerante/fermer');
        $this->post('/gerante', ['pin' => '1234', 'retour' => 'https://exemple.com'])->assertRedirect(route('dashboard'));

        $this->post('/gerante/fermer');
        $this->get('/parametres')->assertRedirectContains('/gerante');
    }

    public function test_le_mode_gerante_expire(): void
    {
        $this->post('/gerante', ['pin' => '1234']);
        $this->get('/parametres')->assertOk();

        Carbon::setTestNow(now()->addMinutes(11));
        $this->get('/parametres')->assertRedirectContains('/gerante');
    }

    public function test_trop_d_essais_de_code_pin_sont_bloques(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/gerante', ['pin' => '0000']);
        }
        $this->post('/gerante', ['pin' => '1234'])->assertStatus(429);
    }

    public function test_gerante_gere_les_vendeuses_et_le_code_pin(): void
    {
        $this->ouvrirModeGerante();

        $this->post('/vendeuses', ['nom' => 'Mariam'])->assertSessionHasNoErrors();
        $this->post('/vendeuses', ['nom' => 'Mariam'])->assertSessionHasErrors('nom');
        $mariam = Vendeuse::where('nom', 'Mariam')->firstOrFail();

        $this->put("/vendeuses/{$mariam->id}", ['nom' => 'Mariam K.'])->assertSessionHasNoErrors();
        $this->patch("/vendeuses/{$mariam->id}/activation");
        $this->assertFalse($mariam->fresh()->actif);
        $this->get('/')->assertDontSee('data-id="'.$mariam->id.'"', false);

        $this->put('/parametres/pin', ['pin' => '12a4', 'pin_confirmation' => '12a4'])->assertSessionHasErrors('pin');
        $this->put('/parametres/pin', ['pin' => '2580', 'pin_confirmation' => '2581'])->assertSessionHasErrors('pin');
        $this->put('/parametres/pin', ['pin' => '2580', 'pin_confirmation' => '2580'])->assertSessionHasNoErrors();
        $this->assertTrue(app(PinService::class)->verifier('2580'));
        $this->assertFalse(app(PinService::class)->verifier('1234'));
    }

    public function test_kpi_jour_semaine_et_evolution(): void
    {
        Carbon::setTestNow('2026-09-30 15:00:00');
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);

        Carbon::setTestNow('2026-10-01 10:00:00');
        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        $this->vendre($this->fatou, [['service_id' => $this->brushing->id, 'quantite' => 1]]);

        Carbon::setTestNow('2026-09-22 11:00:00');
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 2]]);
        // Vendredi dernier : hors comparaison, la semaine en cours n'est qu'à jeudi
        Carbon::setTestNow('2026-09-25 11:00:00');
        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        Carbon::setTestNow('2026-10-01 18:00:00');

        $kpi = app(KpiService::class);

        $jour = $kpi->calculer(Periode::depuis('jour'));
        $this->assertSame(13000, $jour['resume']['ca']);
        $this->assertSame(2, $jour['resume']['tickets']);
        $this->assertSame(6500, $jour['resume']['panier_moyen']);
        $this->assertSame(333.3, $jour['evolution']['ca']);
        $this->assertSame('Tresses', $jour['top_services'][0]['libelle']);
        $this->assertSame(['Awa', 'Fatou'], array_column($jour['par_personne'], 'nom'));

        $semaine = $kpi->calculer(Periode::depuis('semaine'));
        $this->assertSame(16000, $semaine['resume']['ca']);
        $this->assertSame(6000, $semaine['precedent']['ca']);
        $this->assertSame(166.7, $semaine['evolution']['ca']);
        $this->assertCount(7, $semaine['courbe']);

        $dates = $kpi->calculer(Periode::depuis('dates', '2026-09-22', '2026-10-01'));
        $this->assertSame(32000, $dates['resume']['ca']);
    }

    public function test_ticket_annule_exclu_des_kpi(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]]);
        $this->vendre($this->awa, [['service_id' => $this->brushing->id, 'quantite' => 1]]);
        $vente = Vente::where('total', 10000)->first();

        $this->ouvrirModeGerante();
        $this->post("/ventes/{$vente->id}/annuler", [])->assertSessionHasErrors('motif');
        $this->post("/ventes/{$vente->id}/annuler", ['motif' => 'Erreur de saisie'])->assertSessionHasNoErrors();

        $kpi = app(KpiService::class)->calculer(Periode::depuis('jour'));
        $this->assertSame(3000, $kpi['resume']['ca']);
        $this->assertSame(1, $kpi['annulations']['nombre']);
    }

    public function test_les_pages_s_affichent_dans_les_deux_modes(): void
    {
        $this->vendre($this->awa, [['service_id' => $this->tresses->id, 'quantite' => 1]]);

        foreach ([false, true] as $gerante) {
            if ($gerante) {
                $this->ouvrirModeGerante();
            }
            foreach (['jour', 'semaine', 'mois'] as $periode) {
                $this->get('/dashboard?periode='.$periode)->assertOk()->assertSee('10 000');
            }
            $this->get('/dashboard?periode=dates&du=nimporte&au=quoi')->assertOk();
            $this->get('/ventes?vendeuse_id='.$this->awa->id)->assertOk()->assertSee('T-20261001-0001');
            $this->get('/services')->assertOk()->assertSee('Brushing');
            $this->get('/gerante')->assertOk();
        }

        $this->get('/services/create')->assertOk();
        $this->get('/services/'.$this->brushing->id.'/edit')->assertOk();
        $this->get('/parametres')->assertOk();
    }

    public function test_gerante_gere_les_services(): void
    {
        $this->ouvrirModeGerante();
        $categorie = Categorie::first();

        $this->post('/services', ['categorie_id' => $categorie->id, 'code' => 'coi-009', 'nom' => 'Chignon', 'prix' => 6000, 'actif' => '1', 'prix_variable' => '1'])
            ->assertRedirect('/services');
        $chignon = Service::where('code', 'COI-009')->firstOrFail();
        $this->assertTrue($chignon->prix_variable);
        $this->get('/services')->assertSee('à partir de');

        $this->put('/services/'.$chignon->id, ['categorie_id' => $categorie->id, 'code' => 'COI-009', 'nom' => 'Chignon', 'prix' => 6500])
            ->assertRedirect('/services');
        $this->assertSame(6500, $chignon->fresh()->prix);
        $this->assertFalse($chignon->fresh()->actif);
        $this->assertFalse($chignon->fresh()->prix_variable);
        $this->get('/')->assertDontSee('COI-009');

        $this->delete('/categories/'.$categorie->id)->assertSessionHas('erreur');
        $this->delete('/services/'.$chignon->id);
        $this->assertNull($chignon->fresh());
    }
}
