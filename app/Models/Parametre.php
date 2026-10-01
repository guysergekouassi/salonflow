<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parametre extends Model
{
    protected $table = 'parametres';

    protected $primaryKey = 'cle';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['cle', 'valeur'];

    public static function lire(string $cle, ?string $defaut = null): ?string
    {
        return static::find($cle)?->valeur ?? $defaut;
    }

    public static function ecrire(string $cle, ?string $valeur): void
    {
        static::updateOrCreate(['cle' => $cle], ['valeur' => $valeur]);
    }
}
