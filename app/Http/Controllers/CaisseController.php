<?php

namespace App\Http\Controllers;

use App\Models\Categorie;
use App\Models\Vendeuse;
use App\Models\Vente;
use App\Services\VenteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaisseController extends Controller
{
    public function index(): View
    {
        $categories = Categorie::orderBy('ordre')->orderBy('nom')
            ->with(['services' => fn ($q) => $q->actifs()->orderBy('ordre')->orderBy('nom')])
            ->get()
            ->filter(fn (Categorie $c) => $c->services->isNotEmpty())
            ->values();

        $services = $categories->flatMap(fn (Categorie $c) => $c->services->map(fn ($s) => [
            'id' => $s->id,
            'code' => $s->code,
            'nom' => $s->nom,
            'prix' => $s->prix,
            'variable' => $s->prix_variable,
            'categorie_id' => $c->id,
            'categorie' => $c->nom,
            'couleur' => $c->couleur,
        ]))->values();

        $duJour = Vente::valides()->whereDate('created_at', today());
        $ca = (int) (clone $duJour)->sum('total');
        $tickets = (clone $duJour)->count();

        return view('caisse.index', [
            'categories' => $categories,
            'services' => $services,
            'vendeuses' => Vendeuse::actives()->get(['id', 'nom']),
            'jour' => [
                'ca' => $ca,
                'tickets' => $tickets,
                'panier_moyen' => $tickets ? (int) round($ca / $tickets) : 0,
            ],
        ]);
    }

    public function store(Request $request, VenteService $ventes): JsonResponse
    {
        $data = $request->validate([
            'vendeuse_id' => [
                Vendeuse::actives()->exists() ? 'required' : 'nullable',
                Rule::exists('vendeuses', 'id')->where('actif', true),
            ],
            'lignes' => ['required', 'array', 'min:1', 'max:50'],
            'lignes.*.service_id' => ['required', 'integer'],
            'lignes.*.quantite' => ['required', 'integer', 'min:1', 'max:99'],
            'lignes.*.prix' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'mode_paiement' => ['required', Rule::in(array_keys(config('salon.modes_paiement')))],
            'montant_recu' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ], [
            'lignes.required' => 'Le ticket est vide : cliquez sur au moins un service.',
            'vendeuse_id.required' => 'Touchez le nom de la vendeuse avant d\'encaisser.',
        ]);

        $vente = $ventes->enregistrer(
            isset($data['vendeuse_id']) ? Vendeuse::find($data['vendeuse_id']) : null,
            $data['lignes'],
            $data['mode_paiement'],
            $data['montant_recu'] ?? null,
        );

        return response()->json([
            'numero' => $vente->numero,
            'total' => $vente->total,
            'ticket_url' => route('tickets.show', ['vente' => $vente, 'imprimer' => 1]),
        ], 201);
    }
}
