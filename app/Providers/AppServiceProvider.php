<?php

namespace App\Providers;

use App\Services\PinService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dates en français dans les KPI et sur les tickets
        Carbon::setLocale(config('app.locale'));
        CarbonImmutable::setLocale(config('app.locale'));

        // Version démo en ligne : pas d'impression directe sur une imprimante du serveur
        if (config('salon.demo')) {
            config(['salon.ticket.driver' => 'navigateur']);
        }

        // Les écrans affichent les actions sensibles différemment selon que le mode gérante est ouvert
        View::composer('*', fn ($view) => $view->with([
            'modeGerante' => app(PinService::class)->estOuvert(),
            'demo' => (bool) config('salon.demo'),
        ]));
    }
}
