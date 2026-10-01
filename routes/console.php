<?php

use App\Services\PinService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('salon:pin {pin : Nouveau code de 4 à 8 chiffres}', function (PinService $service, string $pin) {
    if (! preg_match('/^[0-9]{4,8}$/', $pin)) {
        $this->error('Le code PIN doit contenir de 4 à 8 chiffres.');

        return 1;
    }

    $service->definir($pin);
    $this->info('Nouveau code PIN de la gérante enregistré.');
})->purpose('Réinitialiser le code PIN de la gérante (en cas d\'oubli)');

// Version démo en ligne : la base repart de zéro chaque nuit (php artisan schedule:work dans le conteneur)
Schedule::command('salon:demo --fresh')
    ->dailyAt('03:00')
    ->when(fn () => config('salon.demo'));
