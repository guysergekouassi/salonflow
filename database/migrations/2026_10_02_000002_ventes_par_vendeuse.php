<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Passage à une caisse sans connexion : chaque vente est rattachée à une vendeuse (un simple nom)
 * au lieu d'un compte utilisateur. Les ventes existantes sont conservées et rattachées à une
 * vendeuse portant le nom du compte qui les avait encaissées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignId('vendeuse_id')->nullable()->after('numero')->constrained('vendeuses')->nullOnDelete();
        });

        $comptes = DB::table('users')
            ->whereIn('id', DB::table('ventes')->select('user_id'))
            ->orWhere(fn ($q) => $q->where('role', 'assistante')->where('actif', true))
            ->orderBy('id')
            ->get(['id', 'name']);

        $noms = [];
        foreach ($comptes as $rang => $compte) {
            $nom = mb_substr(trim($compte->name) ?: 'Vendeuse '.$compte->id, 0, 60);
            if (isset($noms[mb_strtolower($nom)])) {
                $nom = mb_substr($nom, 0, 54).' ('.$compte->id.')';
            }
            $noms[mb_strtolower($nom)] = true;

            $vendeuseId = DB::table('vendeuses')->insertGetId([
                'nom' => $nom, 'actif' => true, 'ordre' => $rang + 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ventes')->where('user_id', $compte->id)->update(['vendeuse_id' => $vendeuseId]);
        }

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['annulee_par']);
        });
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'annulee_par']);
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('annulee_par')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign(['vendeuse_id']);
        });
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('vendeuse_id');
        });
    }
};
