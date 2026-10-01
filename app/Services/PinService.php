<?php

namespace App\Services;

use App\Models\Parametre;
use Illuminate\Support\Facades\Hash;

/**
 * Code PIN de la gérante : protège les actions sensibles (prix, services, annulations, vendeuses).
 * Une fois saisi, le « mode gérante » reste ouvert quelques minutes sur ce poste.
 */
class PinService
{
    private const CLE = 'pin_gerante';

    private const SESSION = 'mode_gerante_jusqua';

    public function verifier(string $pin): bool
    {
        $hash = Parametre::lire(self::CLE);

        return $hash !== null && Hash::check($pin, $hash);
    }

    public function definir(string $pin): void
    {
        Parametre::ecrire(self::CLE, Hash::make($pin));
    }

    public function estDefini(): bool
    {
        return Parametre::lire(self::CLE) !== null;
    }

    public function ouvrir(): void
    {
        session([self::SESSION => now()->addMinutes($this->minutes())->timestamp]);
    }

    public function fermer(): void
    {
        session()->forget(self::SESSION);
    }

    public function estOuvert(): bool
    {
        return session(self::SESSION, 0) > now()->timestamp;
    }

    /** Chaque action de la gérante prolonge le mode gérante */
    public function prolonger(): void
    {
        if ($this->estOuvert()) {
            $this->ouvrir();
        }
    }

    public function minutes(): int
    {
        return (int) config('salon.mode_gerante_minutes', 10);
    }
}
