<?php

namespace Database\Seeders;

use App\Models\Categorie;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue officiel du salon (prestations et prix de l'affiche).
 * La gérante le modifie ensuite dans « Services & prix ».
 */
class CatalogueSeeder extends Seeder
{
    /** [catégorie, couleur, [[code, nom, prix, prix variable « à partir de »], ...]] */
    public const CATALOGUE = [
        ['Onglerie', '#db2777', [
            ['ONG-001', 'Pose gel capsules long', 30000, false],
            ['ONG-002', 'Pose gel capsules court', 25000, false],
            ['ONG-003', 'Remplissage gel long', 20000, false],
            ['ONG-004', 'Remplissage gel court', 15000, false],
            ['ONG-005', 'Semi-permanent capsules mains long', 15000, false],
            ['ONG-006', 'Semi-permanent capsules mains court', 10000, false],
            ['ONG-007', 'Semi-permanent pieds', 10000, false],
            ['ONG-008', 'Pose vernis classique', 2000, false],
            ['ONG-009', 'Baby boomer', 5000, false],
            ['ONG-010', 'Pose capsules Acrygel long', 20000, false],
            ['ONG-011', 'Pose capsules Acrygel court', 15000, false],
            ['ONG-012', 'Pose Acrygel sur ongle simple', 10000, false],
        ]],
        ['Décorations', '#d97706', [
            ['DEC-001', 'Déco / strass (l\'unité)', 1000, false],
        ]],
        ['Pédicure, manucure & visage', '#059669', [
            ['PMV-001', 'Pédicure / manucure complète', 13000, false],
            ['PMV-002', 'Soin de visage', 5000, false],
        ]],
        ['Coiffure, tresses & soins', '#2563eb', [
            ['COI-001', 'Shampoing', 3000, false],
            ['COI-002', 'Défrisage', 5000, false],
            ['COI-003', 'Bain d\'huile', 5000, false],
            ['COI-004', 'Tresse', 10000, true],
            ['COI-005', 'Teinture cheveux', 5000, true],
        ]],
        ['Cils', '#7c3aed', [
            ['CIL-001', 'Pose extension de cils', 10000, false],
            ['CIL-002', 'Remplissage extension de cils', 15000, false],
        ]],
    ];

    public function run(): void
    {
        if (! Service::exists()) {
            $this->remplacer();
        }
    }

    /**
     * Supprime tous les services et catégories puis installe le catalogue officiel.
     * Les tickets déjà encaissés gardent leurs libellés et leurs prix.
     */
    public function remplacer(): void
    {
        DB::transaction(function () {
            Service::query()->delete();
            Categorie::query()->delete();

            foreach (self::CATALOGUE as $ordre => [$nom, $couleur, $services]) {
                $categorie = Categorie::create(['nom' => $nom, 'couleur' => $couleur, 'ordre' => $ordre + 1]);

                foreach ($services as $rang => [$code, $libelle, $prix, $variable]) {
                    Service::create([
                        'categorie_id' => $categorie->id,
                        'code' => $code,
                        'nom' => $libelle,
                        'prix' => $prix,
                        'prix_variable' => $variable,
                        'actif' => true,
                        'ordre' => $rang + 1,
                    ]);
                }
            }
        });
    }
}
