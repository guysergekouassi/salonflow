@php use App\Support\Fcfa; @endphp
@extends('layouts.app')

@section('titre', 'Services & prix')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'ciseaux']) Catalogue des services</h2>
        <p>{{ $services->where('actif', true)->count() }} service(s) actif(s) affiché(s) en caisse</p>
    </div>
    <a class="btn btn-primaire" href="{{ route('services.create') }}">@include('partials.icone', ['nom' => 'plus']) Ajouter un service</a>
</div>

@if($errors->any())<div class="alerte alerte-erreur">{{ $errors->first() }}</div>@endif

<div class="grille-dash">
    <div>
        <table class="tableau" data-tableau data-titre="Liste des services" data-fichier="services" data-par-page="25">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Service</th>
                    <th>Catégorie</th>
                    <th class="right">Prix</th>
                    <th>Statut</th>
                    <th data-tri="non" data-export="non">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($services as $service)
                <tr>
                    <td class="mono small nowrap">{{ $service->code }}</td>
                    <td><b>{{ $service->nom }}</b></td>
                    <td><span class="badge" style="background: {{ $service->categorie->couleur }}1f; color: {{ $service->categorie->couleur }}">{{ $service->categorie->nom }}</span></td>
                    <td class="right nowrap" style="color:var(--vert);font-weight:800" data-tri="{{ $service->prix }}" data-export="{{ $service->prix }}">@if($service->prix_variable)<span class="muted small" style="font-weight:600">à partir de</span> @endif{{ Fcfa::format($service->prix) }}</td>
                    <td>@if($service->actif)<span class="badge badge-vert">Visible</span>@else<span class="badge badge-gris">Masqué</span>@endif</td>
                    <td>
                        <div class="actions">
                            <a class="btn-ico btn-modifier" href="{{ route('services.edit', $service) }}" title="Modifier">@include('partials.icone', ['nom' => 'modifier'])</a>
                            @if($modeGerante)
                            <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Supprimer « {{ addslashes($service->nom) }} » ? Les anciens tickets restent intacts.')">
                                @csrf @method('DELETE')
                                <button class="btn-ico btn-supprimer" type="submit" title="Supprimer">@include('partials.icone', ['nom' => 'poubelle'])</button>
                            </form>
                            @else
                            <a class="btn-ico btn-supprimer" href="{{ route('gerante.create', ['retour' => url()->full()]) }}" title="Supprimer (code PIN gérante)">@include('partials.icone', ['nom' => 'poubelle'])</a>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>Catégories</h3></div>
        <div class="carte-corps">
        @if($modeGerante)
            @foreach($categories as $c)
                <form method="POST" action="{{ route('categories.update', $c) }}" class="filtres-dates" style="margin-bottom:10px;flex-wrap:nowrap">
                    @csrf @method('PUT')
                    <input type="color" name="couleur" value="{{ $c->couleur }}" aria-label="Couleur">
                    <input type="text" name="nom" value="{{ $c->nom }}" required>
                    <input type="number" name="ordre" value="{{ $c->ordre }}" style="width:64px" title="Ordre d'affichage">
                    <button class="btn btn-petit" type="submit" title="Enregistrer">✓</button>
                    <button class="btn btn-petit btn-rouge" type="submit" form="suppr-cat-{{ $c->id }}" title="Supprimer" @disabled($c->services_count > 0)>✕</button>
                </form>
                <form id="suppr-cat-{{ $c->id }}" method="POST" action="{{ route('categories.destroy', $c) }}" onsubmit="return confirm('Supprimer la catégorie ?')">@csrf @method('DELETE')</form>
            @endforeach

            <h3 style="font-size:15px;margin:20px 0 10px">Nouvelle catégorie</h3>
            <form method="POST" action="{{ route('categories.store') }}" class="filtres-dates" style="flex-wrap:nowrap">
                @csrf
                <input type="color" name="couleur" value="#0ea5e9" aria-label="Couleur">
                <input type="text" name="nom" placeholder="Ex. Maquillage" required>
                <button class="btn btn-petit btn-primaire" type="submit">Ajouter</button>
            </form>
        @else
            <div class="liste-barres">
                @foreach($categories as $c)
                    <div class="ligne-haut"><span><span class="pastille" style="background:{{ $c->couleur }}"></span>{{ $c->nom }}</span><span class="muted small">{{ $c->services_count }} service(s)</span></div>
                @endforeach
            </div>
            <a class="btn btn-petit" style="margin-top:16px" href="{{ route('gerante.create', ['retour' => url()->full()]) }}">@include('partials.icone', ['nom' => 'cle']) Modifier les catégories</a>
        @endif
        </div>
    </div>
</div>
@endsection
