<?php

use App\Services\PinService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('salon:pin {pin : Nouveau code de 4 à 8 chiffres}', function (PinService $service, string $pin) {
    if (! preg_match('/^[0-9]{4,8}$/', $pin)) {
        $this->error('Le code PIN doit contenir de 4 à 8 chiffres.');

        return 1;
    }

    $service->definir($pin);
    $this->info('Nouveau code PIN de la gérante enregistré.');
})->purpose('Réinitialiser le code PIN de la gérante (en cas d\'oubli)');
