<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — {{ config('salon.nom') }}</title>
    <link rel="icon" href="{{ asset('images/logo.jpg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="connexion">
    <div class="visuel-salon" role="img" aria-label="Mains aux ongles vernis tenant une rose">
        <div class="legende">
            <h2>{{ config('salon.nom') }}</h2>
            <p>{{ config('salon.adresse') }}@if(config('salon.telephone')) · {{ config('salon.telephone') }}@endif</p>
        </div>
    </div>

    <div class="cote-formulaire">
        <form class="boite" method="POST" action="{{ url('/login') }}">
            @csrf
            <div class="marque">
                <img class="logo-image" src="{{ asset('images/logo.jpg') }}" alt="">
                <div>
                    <h2 style="font-size:22px">Connexion</h2>
                    <div class="muted small">Caisse SalonFlow</div>
                </div>
            </div>

            <div class="champ">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email')<div class="erreur">{{ $message }}</div>@enderror
            </div>
            <div class="champ">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div class="champ">
                <label class="coche"><input type="checkbox" name="remember" value="1"> Rester connectée</label>
            </div>
            <button class="btn btn-primaire" style="width:100%;padding:13px" type="submit">Se connecter</button>
        </form>
    </div>
</div>
</body>
</html>
