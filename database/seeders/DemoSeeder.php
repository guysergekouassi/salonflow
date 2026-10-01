<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Vendeuse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Données de démonstration : quelques vendeuses et des ventes réalistes sur les derniers jours
 * (plus de monde le samedi et aux heures de pointe, quelques tickets annulés).
 * Ne jamais lancer sur la base du salon : php artisan salon:demo refuse hors mode démo sans --force.
 */
class DemoSeeder extends Seeder
{
    public const VENDEUSES = ['Awa', 'Fatou', 'Mariam', 'Aïcha'];

    /** Fréquentation selon le jour (0 = dimanche) */
    private const JOURS = [0 => 0.6, 1 => 0.7, 2 => 0.85, 3 => 0.9, 4 => 1.0, 5 => 1.25, 6 => 1.6];

    /** Fréquentation selon l'heure : le salon ouvre de 8 h à 20 h */
    private const HEURES = [8 => 2, 9 => 4, 10 => 7, 11 => 8, 12 => 5, 13 => 4, 14 => 5, 15 => 6, 16 => 8, 17 => 9, 18 => 8, 19 => 4];

    private const MOTIFS = ['Erreur de saisie', 'Cliente partie avant la prestation', 'Doublon'];

    public int $jours = 60;

    public function run(): void
    {
        foreach (self::VENDEUSES as $rang => $nom) {
            Vendeuse::firstOrCreate(['nom' => $nom], ['ordre' => $rang + 2]);
        }
        $vendeuses = Vendeuse::actives()->pluck('id')->all();

        // Les petites prestations reviennent plus souvent que les grosses
        $services = Service::actifs()->get()->map(fn (Service $s) => [
            'service' => $s,
            'poids' => max(1, (int) round(60000 / max($s->prix, 1000))),
        ])->all();

        if (! $services || ! $vendeuses) {
            return;
        }

        $maintenant = now();
        $tickets = [];

        for ($d = $this->jours - 1; $d >= 0; $d--) {
            $jour = today()->subDays($d);
            // Légère progression sur la période : les KPI montrent une évolution positive
            $tendance = 0.85 + 0.3 * (1 - $d / max($this->jours, 1));
            $nombre = (int) round(14 * self::JOURS[$jour->dayOfWeek] * $tendance) + mt_rand(-2, 2);

            $heures = [];
            for ($i = 0; $i < $nombre; $i++) {
                $heure = $jour->copy()->setTime($this->tirer(self::HEURES), mt_rand(0, 59), mt_rand(0, 59));
                if ($heure->lessThan($maintenant)) {
                    $heures[] = $heure;
                }
            }
            sort($heures);

            foreach ($heures as $rang => $heure) {
                $tickets[] = $this->ticket($heure, $rang + 1, $services, $vendeuses);
            }
        }

        DB::transaction(function () use ($tickets) {
            foreach ($tickets as [$vente, $lignes]) {
                $id = DB::table('ventes')->insertGetId($vente);
                DB::table('vente_lignes')->insert(array_map(fn ($l) => $l + ['vente_id' => $id], $lignes));
            }
        });
    }

    /** @return array{0: array<string, mixed>, 1: list<array<string, mixed>>} */
    private function ticket(Carbon $heure, int $rang, array $services, array $vendeuses): array
    {
        $lignes = [];
        $nombreLignes = $this->tirer([1 => 6, 2 => 3, 3 => 1]);

        for ($i = 0; $i < $nombreLignes; $i++) {
            /** @var Service $service */
            $service = $this->tirer(array_column($services, 'poids'), array_column($services, 'service'));
            if (isset($lignes[$service->id])) {
                continue;
            }

            $prix = $service->prix_variable ? $service->prix + 2500 * mt_rand(0, 4) : $service->prix;
            $quantite = str_starts_with($service->code, 'DEC-') ? mt_rand(2, 10) : 1;

            $lignes[$service->id] = [
                'service_id' => $service->id,
                'categorie_id' => $service->categorie_id,
                'libelle' => $service->nom,
                'prix_unitaire' => $prix,
                'quantite' => $quantite,
                'total' => $prix * $quantite,
            ];
        }

        $total = array_sum(array_column($lignes, 'total'));
        // La cliente paie avec des billets : montant arrondi au billet supérieur
        $billet = $total >= 10000 ? 10000 : ($total >= 5000 ? 5000 : 1000);
        $recu = mt_rand(1, 3) === 1 ? $total : (int) ceil($total / $billet) * $billet;
        $annulee = mt_rand(1, 40) === 1;

        $vente = [
            'numero' => 'T-'.$heure->format('Ymd').'-'.str_pad((string) $rang, 4, '0', STR_PAD_LEFT),
            'vendeuse_id' => $vendeuses[array_rand($vendeuses)],
            'total' => $total,
            'mode_paiement' => 'especes',
            'montant_recu' => $recu,
            'monnaie_rendue' => $recu - $total,
            'annulee_at' => $annulee ? $heure->copy()->addMinutes(mt_rand(2, 30)) : null,
            'motif_annulation' => $annulee ? self::MOTIFS[array_rand(self::MOTIFS)] : null,
            'created_at' => $heure,
            'updated_at' => $heure,
        ];

        return [$vente, array_values($lignes)];
    }

    /** Tirage pondéré : renvoie la clé (ou la valeur correspondante de $valeurs) */
    private function tirer(array $poids, ?array $valeurs = null): mixed
    {
        $cles = array_keys($poids);
        $tirage = mt_rand(1, array_sum($poids));

        foreach ($cles as $i => $cle) {
            $tirage -= $poids[$cle];
            if ($tirage <= 0) {
                return $valeurs === null ? $cle : $valeurs[$i];
            }
        }

        return $valeurs === null ? end($cles) : end($valeurs);
    }
}
