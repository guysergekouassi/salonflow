<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Prix « à partir de » : la caisse demande le prix réel, au moins égal à `prix`
            $table->boolean('prix_variable')->default(false)->after('prix');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('prix_variable');
        });
    }
};
