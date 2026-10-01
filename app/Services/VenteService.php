<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Vendeuse;
use App\Models\Vente;
use App\Support\Fcfa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VenteService
{
    /**
     * Enregistre une vente. Les prix viennent de la base, jamais du navigateur,
     * sauf pour un service à prix variable (« à partir de ») : le prix saisi doit alors
     * être au moins égal au prix minimum du catalogue.
     *
     * @param  array<int, array{service_id:int, quantite:int, prix?:int|null}>  $lignes
     */
    public function enregistrer(?Vendeuse $vendeuse, array $lignes, string $modePaiement, ?int $montantRecu = null): Vente
    {
        $ids = array_unique(array_map(fn ($l) => (int) $l['service_id'], $lignes));
        $services = Service::actifs()->whereIn('id', $ids)->get()->keyBy('id');

        if ($services->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'lignes' => 'Un des services du ticket n\'existe plus ou a été désactivé. Rechargez la caisse.',
            ]);
        }

        // Regroupe par service et par prix unitaire (une tresse à 10 000 et une à 15 000 = deux lignes)
        $groupes = [];
        foreach ($lignes as $ligne) {
            $service = $services[(int) $ligne['service_id']];
            $prix = $this->prixUnitaire($service, $ligne['prix'] ?? null);
            $cle = $service->id.'@'.$prix;

            $groupes[$cle] ??= ['service' => $service, 'prix' => $prix, 'quantite' => 0];
            $groupes[$cle]['quantite'] += (int) $ligne['quantite'];
        }

        $total = array_sum(array_map(fn ($g) => $g['prix'] * $g['quantite'], $groupes));

        if ($modePaiement === 'especes' && $montantRecu !== null && $montantRecu < $total) {
            throw ValidationException::withMessages([
                'montant_recu' => 'Le montant reçu est inférieur au total du ticket.',
            ]);
        }

        return DB::transaction(function () use ($vendeuse, $groupes, $total, $modePaiement, $montantRecu) {
            $vente = Vente::create([
                'numero' => $this->prochainNumero(),
                'vendeuse_id' => $vendeuse?->id,
                'total' => $total,
                'mode_paiement' => $modePaiement,
                'montant_recu' => $modePaiement === 'especes' ? ($montantRecu ?? $total) : null,
                'monnaie_rendue' => $modePaiement === 'especes' && $montantRecu !== null ? $montantRecu - $total : 0,
            ]);

            foreach ($groupes as ['service' => $service, 'prix' => $prix, 'quantite' => $quantite]) {
                $vente->lignes()->create([
                    'service_id' => $service->id,
                    'categorie_id' => $service->categorie_id,
                    'libelle' => $service->nom,
                    'prix_unitaire' => $prix,
                    'quantite' => $quantite,
                    'total' => $prix * $quantite,
                ]);
            }

            return $vente;
        });
    }

    private function prixUnitaire(Service $service, mixed $prixSaisi): int
    {
        if (! $service->prix_variable) {
            return $service->prix;
        }

        $prix = $prixSaisi === null ? $service->prix : (int) $prixSaisi;

        if ($prix < $service->prix) {
            throw ValidationException::withMessages([
                'lignes' => "Le prix de « {$service->nom} » doit être d'au moins ".Fcfa::format($service->prix).'.',
            ]);
        }

        return $prix;
    }

    public function annuler(Vente $vente, string $motif): void
    {
        if ($vente->estAnnulee()) {
            throw ValidationException::withMessages(['motif' => 'Ce ticket est déjà annulé.']);
        }

        $vente->update([
            'annulee_at' => now(),
            'motif_annulation' => $motif,
        ]);
    }

    /** Numéro de ticket qui repart à 0001 chaque jour : T-20261001-0001 */
    private function prochainNumero(): string
    {
        $prefixe = 'T-'.now()->format('Ymd').'-';

        $dernier = Vente::where('numero', 'like', $prefixe.'%')->max('numero');
        $suivant = $dernier ? ((int) substr($dernier, -4)) + 1 : 1;

        return $prefixe.str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
    }
}
