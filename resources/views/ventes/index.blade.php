@extends('layouts.app')

@section('titre', 'Historique des ventes')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'liste']) Historique des ventes</h2>
        <p>{{ $periode->libelle() }} · {{ $ventes->count() }} ticket(s)</p>
    </div>
    @include('partials.selecteur-periode', ['periode' => $periode, 'route' => 'ventes.index', 'extra' => array_filter(request()->only('user_id', 'statut'))])
</div>

@error('motif')<div class="alerte alerte-erreur">{{ $message }}</div>@enderror
@if($ventes->count() >= \App\Http\Controllers\DashboardController::MAX_LIGNES)
    <div class="alerte alerte-info">Seuls les {{ \App\Http\Controllers\DashboardController::MAX_LIGNES }} tickets les plus récents sont affichés : choisissez une période plus courte.</div>
@endif

<form method="GET" class="filtres-dates" style="margin-bottom:16px">
    @foreach(request()->only('periode', 'du', 'au') as $cle => $valeur)<input type="hidden" name="{{ $cle }}" value="{{ $valeur }}">@endforeach
    <select name="user_id" style="width:auto">
        <option value="">Toutes les personnes</option>
        @foreach($personnes as $p)<option value="{{ $p->id }}" @selected(request('user_id') == $p->id)>{{ $p->name }}</option>@endforeach
    </select>
    <select name="statut" style="width:auto">
        <option value="">Tous les tickets</option>
        <option value="annulees" @selected(request('statut') === 'annulees')>Annulés uniquement</option>
    </select>
    <button class="btn btn-petit" type="submit">Filtrer</button>
</form>

@include('ventes.tableau', ['ventes' => $ventes, 'datatable' => true, 'titre' => 'Liste des ventes'])
@endsection
