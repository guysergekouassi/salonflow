@extends('layouts.app')

@section('titre', 'Mode gérante')

@section('contenu')
<div class="pin-page">
    <form class="carte pin-boite" method="POST" action="{{ route('gerante.store') }}" id="form-pin">
        @csrf
        <input type="hidden" name="retour" value="{{ old('retour', $retour) }}">
        <img class="logo-image" src="{{ asset('images/logo.jpg') }}" alt="" width="64" height="64">
        <h2>Code PIN de la gérante</h2>
        <p class="muted small">Pour modifier les prix et services, annuler un ticket ou gérer les vendeuses.</p>
        @if($demo)<p class="alerte alerte-info small">Démo : le code est <b>1234</b></p>@endif

        <input type="password" name="pin" id="pin" inputmode="numeric" autocomplete="off" maxlength="8"
               class="pin-saisie" placeholder="••••" autofocus required>
        @error('pin')<div class="erreur">{{ $message }}</div>@enderror

        <div class="pave" id="pave">
            @foreach([1, 2, 3, 4, 5, 6, 7, 8, 9] as $chiffre)
                <button type="button" data-touche="{{ $chiffre }}">{{ $chiffre }}</button>
            @endforeach
            <button type="button" data-touche="effacer" aria-label="Effacer">⌫</button>
            <button type="button" data-touche="0">0</button>
            <button type="submit" class="valider" aria-label="Valider">OK</button>
        </div>

        <a class="small" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('caisse.index') }}">← Retour</a>
    </form>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('pave').addEventListener('click', (e) => {
        const touche = e.target.closest('button[data-touche]');
        if (!touche) return;
        const champ = document.getElementById('pin');
        champ.value = touche.dataset.touche === 'effacer' ? champ.value.slice(0, -1) : (champ.value + touche.dataset.touche).slice(0, 8);
        champ.focus();
    });
</script>
@endpush
