<?php

namespace App\Http\Middleware;

use App\Services\PinService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Actions sensibles : demande le code PIN de la gérante si le mode gérante n'est pas ouvert */
class ModeGerante
{
    public function __construct(private PinService $pin) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->pin->estOuvert()) {
            $this->pin->prolonger();

            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403, 'Code PIN de la gérante requis.');
        }

        // Après le code, on revient sur la page demandée (ou sur la page d'où venait le formulaire)
        $retour = $request->isMethod('GET') ? $request->fullUrl() : url()->previous();

        return redirect()->route('gerante.create', ['retour' => $retour])
            ->with('info', $request->isMethod('GET') ? null : 'Action réservée à la gérante : saisissez le code PIN, puis recommencez l\'action.');
    }
}
