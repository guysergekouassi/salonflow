@extends('layouts.app')

@section('titre', 'Vendeuses & code PIN')

@section('contenu')
<div class="page-titre">
    <div>
        <h2>@include('partials.icone', ['nom' => 'utilisateurs']) Vendeuses & code PIN</h2>
        <p>Les vendeuses actives apparaissent sur la caisse : on touche son nom avant d'encaisser, sans mot de passe.</p>
    </div>
</div>

@if($errors->has('nom'))<div class="alerte alerte-erreur">{{ $errors->first('nom') }}</div>@endif

<div class="grille-dash">
    <div>
        <table class="tableau" data-tableau data-titre="Liste des vendeuses" data-fichier="vendeuses">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th class="right">Tickets encaissés</th>
                    <th>Statut</th>
                    <th data-tri="non" data-export="non">Actions</th>
                </tr>
            </thead>
            <tbody>
            @foreach($vendeuses as $v)
                <tr>
                    <td><b>{{ $v->nom }}</b></td>
                    <td class="right">{{ $v->ventes_count }}</td>
                    <td>@if($v->actif)<span class="badge badge-vert">Sur la caisse</span>@else<span class="badge badge-gris">Masquée</span>@endif</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('vendeuses.update', $v) }}"
                                  onsubmit="const n = prompt('Nouveau nom :', this.nom.value); if (!n || n === this.nom.value) return false; this.nom.value = n;">
                                @csrf @method('PUT')
                                <input type="hidden" name="nom" value="{{ $v->nom }}">
                                <button class="btn-ico btn-modifier" type="submit" title="Renommer">@include('partials.icone', ['nom' => 'modifier'])</button>
                            </form>
                            <form method="POST" action="{{ route('vendeuses.activation', $v) }}">
                                @csrf @method('PATCH')
                                @if($v->actif)
                                    <button class="btn-ico btn-supprimer" type="submit" title="Retirer de la caisse">@include('partials.icone', ['nom' => 'interdit'])</button>
                                @else
                                    <button class="btn-ico btn-activer" type="submit" title="Remettre sur la caisse">@include('partials.icone', ['nom' => 'valider'])</button>
                                @endif
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div>
        <div class="carte" style="margin-bottom:16px">
            <div class="carte-titre"><h3>Nouvelle vendeuse</h3></div>
            <form method="POST" action="{{ route('vendeuses.store') }}" class="carte-corps">
                @csrf
                <div class="champ">
                    <label for="nom">Nom affiché sur la caisse</label>
                    <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="60" placeholder="Ex. Awa">
                </div>
                <button class="btn btn-primaire" type="submit">@include('partials.icone', ['nom' => 'plus']) Ajouter</button>
            </form>
        </div>

        <div class="carte">
            <div class="carte-titre"><h3>@include('partials.icone', ['nom' => 'cle']) Changer le code PIN</h3></div>
            @if($demo)
            <div class="carte-corps">
                <p class="muted" style="margin:0">Version de démonstration : le code PIN reste <b>1234</b> pour tous les visiteurs.</p>
            </div>
            @else
            <form method="POST" action="{{ route('parametres.pin') }}" class="carte-corps">
                @csrf @method('PUT')
                <div class="champ">
                    <label for="pin">Nouveau code (4 à 8 chiffres)</label>
                    <input type="password" id="pin" name="pin" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" required autocomplete="new-password">
                    @error('pin')<div class="erreur">{{ $message }}</div>@enderror
                </div>
                <div class="champ">
                    <label for="pin_confirmation">Confirmer le code</label>
                    <input type="password" id="pin_confirmation" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4,8}" maxlength="8" required autocomplete="new-password">
                </div>
                <button class="btn btn-primaire" type="submit">Enregistrer le code</button>
                <p class="muted small" style="margin:12px 0 0">Code oublié ? Sur le PC : <code>php artisan salon:pin 1234</code></p>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
