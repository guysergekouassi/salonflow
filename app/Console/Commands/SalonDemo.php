<?php

namespace App\Console\Commands;

use App\Models\Vente;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/** Remplit la base avec des ventes de démonstration (version démo en ligne). */
class SalonDemo extends Command
{
    protected $signature = 'salon:demo
        {--fresh : Efface toute la base avant (remise à zéro de la démo)}
        {--jours=60 : Nombre de jours de ventes à générer}
        {--force : Lancer même si SALON_DEMO n\'est pas activé}';

    protected $description = 'Génère des vendeuses et des ventes de démonstration';

    public function handle(): int
    {
        // Garde-fou : ne jamais mélanger de fausses ventes avec la caisse du salon
        if (! config('salon.demo') && ! $this->option('force')) {
            $this->error('Mode démo désactivé : mettre SALON_DEMO=true dans .env (ou ajouter --force sur une base de test).');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        } elseif (Vente::exists()) {
            $this->error('La base contient déjà des ventes : relancer avec --fresh pour repartir de zéro.');

            return self::FAILURE;
        }

        $seeder = $this->laravel->make(DemoSeeder::class);
        $seeder->jours = max(1, (int) $this->option('jours'));
        $seeder->run();

        $this->info("Démo prête : {$seeder->jours} jours de ventes générés. Code PIN gérante : 1234.");

        return self::SUCCESS;
    }
}
