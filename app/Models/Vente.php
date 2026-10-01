<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $fillable = [
        'numero', 'vendeuse_id', 'total', 'mode_paiement', 'montant_recu', 'monnaie_rendue',
        'annulee_at', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'montant_recu' => 'integer',
            'monnaie_rendue' => 'integer',
            'annulee_at' => 'datetime',
        ];
    }

    public function vendeuse(): BelongsTo
    {
        return $this->belongsTo(Vendeuse::class);
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(VenteLigne::class);
    }

    /** Ventes qui comptent dans le chiffre d'affaires */
    public function scopeValides(Builder $query): Builder
    {
        return $query->whereNull('annulee_at');
    }

    public function estAnnulee(): bool
    {
        return $this->annulee_at !== null;
    }

    /** Libellés de tous les modes, y compris ceux qui ne sont plus proposés (anciens tickets) */
    public const LIBELLES_PAIEMENT = [
        'especes' => 'Espèces',
        'mobile_money' => 'Mobile Money',
        'carte' => 'Carte',
    ];

    public static function libellePaiement(string $mode): string
    {
        return config('salon.modes_paiement.'.$mode) ?? self::LIBELLES_PAIEMENT[$mode] ?? $mode;
    }

    public function libelleModePaiement(): string
    {
        return self::libellePaiement($this->mode_paiement);
    }
}
