<?php

namespace App\Http\Controllers;

use App\Models\Vendeuse;
use App\Services\PinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Paramètres de la gérante : vendeuses et code PIN */
class VendeuseController extends Controller
{
    public function index(): View
    {
        return view('parametres.index', [
            'vendeuses' => Vendeuse::withCount('ventes')->orderByDesc('actif')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendeuse = Vendeuse::create($this->valider($request) + [
            'ordre' => (int) Vendeuse::max('ordre') + 1,
        ]);

        return back()->with('succes', "{$vendeuse->nom} ajoutée : son nom apparaît maintenant sur la caisse.");
    }

    public function update(Request $request, Vendeuse $vendeuse): RedirectResponse
    {
        $vendeuse->update($this->valider($request, $vendeuse));

        return back()->with('succes', "Vendeuse « {$vendeuse->nom} » mise à jour.");
    }

    public function activation(Vendeuse $vendeuse): RedirectResponse
    {
        $vendeuse->update(['actif' => ! $vendeuse->actif]);

        return back()->with('succes', $vendeuse->actif
            ? "{$vendeuse->nom} réapparaît sur la caisse."
            : "{$vendeuse->nom} n'apparaît plus sur la caisse (ses ventes restent dans l'historique).");
    }

    public function pin(Request $request, PinService $pin): RedirectResponse
    {
        // En démo, le code reste 1234 pour tous les visiteurs
        if (config('salon.demo')) {
            return back()->with('erreur', 'Version de démonstration : le code PIN ne peut pas être changé.');
        }

        $data = $request->validate([
            'pin' => ['required', 'confirmed', 'regex:/^[0-9]{4,8}$/'],
        ], [
            'pin.regex' => 'Le code PIN doit contenir de 4 à 8 chiffres.',
            'pin.confirmed' => 'Les deux codes ne sont pas identiques.',
        ]);

        $pin->definir($data['pin']);

        return back()->with('succes', 'Nouveau code PIN enregistré.');
    }

    private function valider(Request $request, ?Vendeuse $vendeuse = null): array
    {
        $data = $request->validate([
            'nom' => ['required', 'string', 'max:60', Rule::unique('vendeuses', 'nom')->ignore($vendeuse)],
            'ordre' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], ['nom.unique' => 'Ce nom existe déjà.']);

        if ($vendeuse) {
            $data['ordre'] = $data['ordre'] ?? $vendeuse->ordre;
        } else {
            unset($data['ordre']);
        }

        return $data;
    }
}
