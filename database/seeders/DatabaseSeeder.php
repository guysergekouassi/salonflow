<?php

namespace Database\Seeders;

use App\Models\Vendeuse;
use App\Services\PinService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $pin = app(PinService::class);
        if (! $pin->estDefini()) {
            $pin->definir('1234');
        }

        if (! Vendeuse::exists()) {
            Vendeuse::create(['nom' => 'Gérante', 'ordre' => 1]);
        }

        // Catalogue officiel (déjà installé par la migration ; ne fait rien s'il existe des services)
        $this->call(CatalogueSeeder::class);

        if ($pin->verifier('1234')) {
            $this->command?->warn('Code PIN gérante : 1234 — changez-le dans « Vendeuses & code PIN » dès la première utilisation.');
        }
    }
}
