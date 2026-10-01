<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personnes qui encaissent : on touche son nom sur la caisse, sans mot de passe
        Schema::create('vendeuses', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 60)->unique();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });

        // Réglages modifiables depuis l'application (ex. code PIN de la gérante, haché)
        Schema::create('parametres', function (Blueprint $table) {
            $table->string('cle', 60)->primary();
            $table->text('valeur')->nullable();
            $table->timestamps();
        });

        // Code PIN de départ : 1234 (à changer dans Paramètres)
        DB::table('parametres')->insert([
            'cle' => 'pin_gerante', 'valeur' => Hash::make('1234'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
        Schema::dropIfExists('vendeuses');
    }
};
