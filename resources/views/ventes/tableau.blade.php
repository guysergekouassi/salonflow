{{-- Tableau des tickets. $datatable = true : recherche, tri, pagination et export (public/js/tableau.js) --}}
@php use App\Support\Fcfa; $datatable = $datatable ?? false; @endphp
@if(! $datatable && $ventes->isEmpty())
    <div class="vide">Aucun ticket sur la période.</div>
@else
<div @unless($datatable) style="overflow-x:auto" @endunless>
<table class="tableau" @if($datatable) data-tableau data-titre="{{ $titre ?? 'Liste des ventes' }}" data-fichier="ventes" @endif>
    <thead>
        <tr>
            <th>Ticket</th>
            <th>Date</th>
            <th>Services</th>
            <th>Vendu par</th>
            <th>Paiement</th>
            <th class="right">Total</th>
            <th>Statut</th>
            <th data-tri="non" data-export="non">Actions</th>
        </tr>
    </thead>
    <tbody>
    @foreach($ventes as $vente)
        <tr class="{{ $vente->estAnnulee() ? 'annule' : '' }}">
            <td class="mono nowrap"><b class="barre-texte">{{ $vente->numero }}</b></td>
            <td class="nowrap" data-tri="{{ $vente->created_at->timestamp }}">{{ $vente->created_at->format('d/m/Y H:i') }}</td>
            <td class="small">{{ $vente->lignes->map(fn ($l) => ($l->quantite > 1 ? $l->quantite.'× ' : '').$l->libelle)->implode(', ') }}</td>
            <td>{{ $vente->user?->name }}</td>
            <td><span class="badge badge-bleu">{{ $vente->libelleModePaiement() }}</span></td>
            <td class="right nowrap" data-tri="{{ $vente->total }}" data-export="{{ $vente->total }}"><b class="barre-texte">{{ Fcfa::format($vente->total) }}</b></td>
            <td>
                @if($vente->estAnnulee())
                    <span class="badge badge-rouge" title="{{ $vente->motif_annulation }}">Annulé</span>
                @else
                    <span class="badge badge-vert">Validé</span>
                @endif
            </td>
            <td>
                <div class="actions">
                    <a class="btn-ico btn-voir" href="{{ route('tickets.show', $vente) }}" title="Voir / réimprimer le ticket">@include('partials.icone', ['nom' => 'oeil'])</a>
                    @if(! $vente->estAnnulee())
                        <form method="POST" action="{{ route('ventes.annuler', $vente) }}"
                              onsubmit="const m = prompt('Motif de l\'annulation du ticket {{ $vente->numero }} ?'); if (!m) return false; this.motif.value = m;">
                            @csrf
                            <input type="hidden" name="motif">
                            <button class="btn-ico btn-supprimer" type="submit" title="Annuler le ticket">@include('partials.icone', ['nom' => 'interdit'])</button>
                        </form>
                    @endif
                </div>
            </td>
        </tr>
    @endforeach
    </tbody>
</table>
</div>
@endif
