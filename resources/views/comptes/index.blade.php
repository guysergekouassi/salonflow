@extends('layouts.app')

@section('titre', 'Comptes assistantes')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'utilisateurs']) Comptes assistantes</h2>
        <p>{{ $actives }} / {{ $maximum }} comptes actifs · une assistante accède uniquement à la caisse et à ses propres KPI.</p>
    </div>
</div>

<div class="grille-dash">
    <div>
        @error('password')<div class="alerte alerte-erreur">{{ $message }}</div>@enderror
        <table class="tableau" data-tableau data-titre="Liste des assistantes" data-fichier="assistantes">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>E-mail</th>
                    <th class="right">Tickets</th>
                    <th>Statut</th>
                    <th data-tri="non" data-export="non">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($assistantes as $a)
                <tr>
                    <td><b>{{ $a->name }}</b></td>
                    <td>{{ $a->email }}</td>
                    <td class="right">{{ $a->ventes_count }}</td>
                    <td>@if($a->actif)<span class="badge badge-vert">Actif</span>@else<span class="badge badge-gris">Désactivé</span>@endif</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('comptes.mot-de-passe', $a) }}"
                                  onsubmit="const m = prompt('Nouveau mot de passe pour {{ addslashes($a->name) }} (6 caractères minimum) :'); if (!m) return false; this.password.value = m;">
                                @csrf @method('PUT')
                                <input type="hidden" name="password">
                                <button class="btn-ico btn-modifier" type="submit" title="Changer le mot de passe">@include('partials.icone', ['nom' => 'cle'])</button>
                            </form>
                            <form method="POST" action="{{ route('comptes.activation', $a) }}">
                                @csrf @method('PATCH')
                                @if($a->actif)
                                    <button class="btn-ico btn-supprimer" type="submit" title="Désactiver le compte">@include('partials.icone', ['nom' => 'interdit'])</button>
                                @else
                                    <button class="btn-ico btn-activer" type="submit" title="Réactiver le compte">@include('partials.icone', ['nom' => 'valider'])</button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="carte">
        <div class="carte-titre"><h3>Nouvelle assistante</h3></div>
        @if($peutAjouter)
            <form method="POST" action="{{ route('comptes.store') }}" class="carte-corps">
                @csrf
                <div class="champ">
                    <label for="name">Nom</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')<div class="erreur">{{ $message }}</div>@enderror
                </div>
                <div class="champ">
                    <label for="email">E-mail de connexion</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required placeholder="awa@salon.local">
                    @error('email')<div class="erreur">{{ $message }}</div>@enderror
                </div>
                <div class="champ">
                    <label for="password_new">Mot de passe</label>
                    <input type="password" id="password_new" name="password" required minlength="6">
                </div>
                <div class="champ">
                    <label for="password_confirmation">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="6">
                </div>
                <button class="btn btn-primaire" type="submit">@include('partials.icone', ['nom' => 'plus']) Créer le compte</button>
            </form>
        @else
            <div class="carte-corps">
                @error('name')<div class="alerte alerte-erreur">{{ $message }}</div>@enderror
                <div class="alerte alerte-info" style="margin:0">
                    Limite atteinte : {{ $maximum }} comptes assistantes actifs au maximum.
                    Désactivez un compte pour en créer un nouveau.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
