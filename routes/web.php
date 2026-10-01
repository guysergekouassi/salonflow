<?php

use App\Http\Controllers\CaisseController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GeranteController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\VendeuseController;
use Illuminate\Support\Facades\Route;

// Caisse ouverte sans connexion : on arrive directement sur la vente
Route::get('/', [CaisseController::class, 'index'])->name('caisse.index');
Route::post('/caisse', [CaisseController::class, 'store'])->name('caisse.store');

Route::get('/tickets/{vente}', [TicketController::class, 'show'])->name('tickets.show');
Route::post('/tickets/{vente}/imprimer', [TicketController::class, 'imprimer'])->name('tickets.imprimer');

// Consultation libre : KPI, historique, catalogue
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/ventes', [DashboardController::class, 'ventes'])->name('ventes.index');
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');

// Mode gérante (code PIN)
Route::get('/gerante', [GeranteController::class, 'create'])->name('gerante.create');
Route::post('/gerante', [GeranteController::class, 'store'])->middleware('throttle:5,1')->name('gerante.store');
Route::post('/gerante/fermer', [GeranteController::class, 'destroy'])->name('gerante.destroy');

// Actions sensibles : réservées à la gérante
Route::middleware('gerante')->group(function () {
    Route::post('/ventes/{vente}/annuler', [DashboardController::class, 'annuler'])->name('ventes.annuler');

    Route::resource('services', ServiceController::class)->except(['index', 'show']);
    Route::post('/categories', [CategorieController::class, 'store'])->name('categories.store');
    Route::put('/categories/{categorie}', [CategorieController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{categorie}', [CategorieController::class, 'destroy'])->name('categories.destroy');

    Route::get('/parametres', [VendeuseController::class, 'index'])->name('parametres.index');
    Route::post('/vendeuses', [VendeuseController::class, 'store'])->name('vendeuses.store');
    Route::put('/vendeuses/{vendeuse}', [VendeuseController::class, 'update'])->name('vendeuses.update');
    Route::patch('/vendeuses/{vendeuse}/activation', [VendeuseController::class, 'activation'])->name('vendeuses.activation');
    Route::put('/parametres/pin', [VendeuseController::class, 'pin'])->name('parametres.pin');
});
