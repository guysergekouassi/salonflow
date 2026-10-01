<?php

namespace App\Http\Controllers;

use App\Services\PinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Ouverture / fermeture du mode gérante par code PIN */
class GeranteController extends Controller
{
    public function create(Request $request): View
    {
        return view('gerante.pin', ['retour' => $this->retour($request)]);
    }

    public function store(Request $request, PinService $pin): RedirectResponse
    {
        $data = $request->validate(['pin' => ['required', 'string']], ['pin.required' => 'Saisissez le code PIN.']);

        if (! $pin->verifier($data['pin'])) {
            return back()->withErrors(['pin' => 'Code PIN incorrect.'])->withInput($request->only('retour'));
        }

        $request->session()->regenerate();
        $pin->ouvrir();

        return redirect($this->retour($request))->with('succes', "Mode gérante ouvert pour {$pin->minutes()} minutes.");
    }

    public function destroy(PinService $pin): RedirectResponse
    {
        $pin->fermer();

        return redirect()->route('caisse.index')->with('succes', 'Mode gérante fermé.');
    }

    /** N'accepte qu'une adresse de l'application elle-même */
    private function retour(Request $request): string
    {
        $retour = (string) $request->input('retour', '');

        return $retour !== '' && Str::startsWith($retour, url('/')) ? $retour : route('dashboard');
    }
}
