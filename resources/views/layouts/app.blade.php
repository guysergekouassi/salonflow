@php
    $lien = fn (string $route, string $icone, string $texte, ?string $motif = null, bool $verrou = false) =>
        '<a href="'.route($route).'" class="'.(request()->routeIs($motif ?? $route) ? 'actif' : '').'">'
        .view('partials.icone', ['nom' => $icone])->render().e($texte)
        .($verrou ? '<span class="verrou" title="Code PIN gérante">'.view('partials.icone', ['nom' => 'cle'])->render().'</span>' : '')
        .'</a>';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titre', 'SalonFlow') — {{ config('salon.nom') }}</title>
    @include('partials.app-meta')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body class="@yield('classe-body')">
<div class="app">
    <aside class="sidebar">
        <div class="marque">
            <img class="logo-image" src="{{ asset('images/logo.jpg') }}" alt="">
            <div><strong>SalonFlow</strong><span>Caisse salon de coiffure</span></div>
        </div>
        <div class="salon">
            <small>Salon</small>
            <b>{{ config('salon.nom') }}</b>
        </div>
        <nav>
            <div class="titre">Ventes</div>
            {!! $lien('caisse.index', 'caisse', 'Nouvelle vente') !!}
            {!! $lien('ventes.index', 'liste', 'Historique des ventes') !!}
            <div class="titre">Tableau de bord</div>
            {!! $lien('dashboard', 'tableau', 'KPI & états de vente') !!}
            <div class="titre">Catalogue</div>
            {!! $lien('services.index', 'ciseaux', 'Services & prix', 'services.*') !!}
            <div class="titre">Gérante</div>
            {!! $lien('parametres.index', 'utilisateurs', 'Vendeuses & code PIN', 'parametres.*', ! $modeGerante) !!}
        </nav>
        <div class="pied">Fonctionne hors ligne · v1.0</div>
    </aside>

    <div class="principal">
        <header class="entete">
            <button class="burger" type="button" onclick="basculerMenu()" aria-label="Menu">@include('partials.icone', ['nom' => 'menu'])</button>
            <h1>@yield('titre', 'SalonFlow')</h1>
            <span class="fil">/ {{ config('salon.nom') }}</span>
            <div class="espace"></div>
            <div class="horloge" id="horloge">{{ now()->format('H:i:s') }}</div>
            @if($modeGerante)
                <form method="POST" action="{{ route('gerante.destroy') }}">
                    @csrf
                    <button class="btn btn-gerante ouvert" type="submit" title="Fermer le mode gérante">
                        @include('partials.icone', ['nom' => 'cle']) Mode gérante · Fermer
                    </button>
                </form>
            @else
                <a class="btn btn-gerante" href="{{ route('gerante.create', ['retour' => url()->full()]) }}" title="Prix, annulations, vendeuses">
                    @include('partials.icone', ['nom' => 'cle']) Mode gérante
                </a>
            @endif
        </header>

        @if($demo)
            <div class="bandeau-demo">
                <b>Version de démonstration</b> — les ventes sont fictives et remises à zéro chaque nuit.
                Code PIN gérante : <b>1234</b>
            </div>
        @endif

        <main class="contenu">
            @unless(View::hasSection('sans-alertes'))
                @if(session('succes'))<div class="alerte alerte-succes">{{ session('succes') }}</div>@endif
                @if(session('erreur'))<div class="alerte alerte-erreur">{{ session('erreur') }}</div>@endif
                @if(session('info'))<div class="alerte alerte-info">{{ session('info') }}</div>@endif
            @endunless
            @yield('contenu')
        </main>
    </div>
</div>

<script>
    function basculerMenu() {
        document.body.classList.toggle(window.innerWidth > 1100 ? 'menu-replie' : 'menu-ouvert');
    }
    setInterval(() => {
        document.getElementById('horloge').textContent = new Date().toLocaleTimeString('fr-FR');
    }, 1000);
</script>
<script src="{{ asset('js/tableau.js') }}?v={{ filemtime(public_path('js/tableau.js')) }}"></script>
@stack('scripts')
</body>
</html>
