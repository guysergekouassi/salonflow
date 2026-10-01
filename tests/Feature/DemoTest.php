<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Vendeuse;
use App\Models\Vente;
use App\Services\PinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-01 15:00:00');
        app(PinService::class)->definir('1234');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_la_commande_demo_refuse_hors_mode_demo(): void
    {
        $this->artisan('salon:demo')->assertFailed();
        $this->assertSame(0, Vente::count());
    }

    public function test_la_commande_demo_genere_des_ventes_coherentes(): void
    {
        config(['salon.demo' => true]);

        $this->artisan('salon:demo', ['--jours' => 14])->assertSuccessful();

        $this->assertSame(4, Vendeuse::whereIn('nom', ['Awa', 'Fatou', 'Mariam', 'Aïcha'])->count());
        $this->assertGreaterThan(50, Vente::count());
        $this->assertSame(0, Vente::where('created_at', '>', now())->count());
        $this->assertSame(0, Vente::where('created_at', '<', today()->subDays(13))->count());

        // Total du ticket = somme des lignes, et la monnaie rendue est cohérente
        Vente::with('lignes')->get()->each(function (Vente $vente) {
            $this->assertSame($vente->total, $vente->lignes->sum('total'));
            $this->assertSame($vente->montant_recu - $vente->total, $vente->monnaie_rendue);
        });

        // Une vente faite après la démo prend le numéro suivant du jour
        $this->artisan('salon:demo')->assertFailed();
        $dernier = Vente::whereDate('created_at', today())->max('numero');
        $this->postJson('/caisse', [
            'vendeuse_id' => Vendeuse::where('nom', 'Awa')->value('id'),
            'lignes' => [['service_id' => Service::actifs()->where('prix_variable', false)->value('id'), 'quantite' => 1]],
            'mode_paiement' => 'especes',
        ])->assertCreated()->assertJsonPath('numero', 'T-20261001-'.str_pad((string) ((int) substr($dernier, -4) + 1), 4, '0', STR_PAD_LEFT));
    }

    public function test_le_mode_demo_affiche_le_bandeau_et_bloque_le_code_pin(): void
    {
        $this->get('/')->assertDontSee('Version de démonstration');

        config(['salon.demo' => true]);

        $this->get('/')->assertSee('Version de démonstration')->assertSee('demo: true', false);
        $this->get('/gerante')->assertSee('le code est');

        $this->post('/gerante', ['pin' => '1234']);
        $this->put('/parametres/pin', ['pin' => '2580', 'pin_confirmation' => '2580'])->assertSessionHas('erreur');
        $this->assertTrue(app(PinService::class)->verifier('1234'));
    }

    public function test_en_demo_le_ticket_ne_lance_pas_l_impression(): void
    {
        config(['salon.demo' => true]);
        $vente = Vente::create(['numero' => 'T-20261001-0001', 'total' => 3000, 'mode_paiement' => 'especes', 'montant_recu' => 3000]);

        $this->get("/tickets/{$vente->id}?imprimer=1")->assertOk()->assertDontSee('window.print());', false);
    }
}
