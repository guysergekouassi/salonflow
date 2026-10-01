<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendeuse extends Model
{
    protected $table = 'vendeuses';

    protected $fillable = ['nom', 'actif', 'ordre'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class);
    }

    public function scopeActives(Builder $query): Builder
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('nom');
    }
}
