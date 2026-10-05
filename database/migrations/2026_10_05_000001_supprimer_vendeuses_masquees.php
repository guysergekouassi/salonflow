<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supprime les vendeuses masquées qui ne travaillent plus au salon.
 * Par sécurité, une vendeuse qui a déjà encaissé des ventes est conservée (ses tickets restent rattachés).
 */
return new class extends Migration
{
    private array $noms = ['Agnimel melede', 'guy', 'fatim'];

    public function up(): void
    {
        DB::table('vendeuses')
            ->whereIn(DB::raw('LOWER(TRIM(nom))'), array_map('mb_strtolower', $this->noms))
            ->where('actif', false)
            ->whereNotExists(fn ($q) => $q->from('ventes')->whereColumn('ventes.vendeuse_id', 'vendeuses.id'))
            ->delete();
    }

    public function down(): void
    {
        // Rien à défaire : la gérante peut recréer une vendeuse dans « Vendeuses »
    }
};
