<?php

use Database\Seeders\CatalogueSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Remplace la liste d'exemple par les vraies prestations du salon.
 * Les tickets déjà encaissés ne changent pas (libellé et prix figés sur chaque ligne).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new CatalogueSeeder)->remplacer();
    }

    public function down(): void
    {
        // Rien à défaire : la gérante gère le catalogue dans « Services & prix »
    }
};
