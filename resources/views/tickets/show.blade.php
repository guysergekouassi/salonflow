@php
    use App\Support\Fcfa;
    $largeur = config('salon.ticket.largeur_mm') === 58 ? 58 : 80;
    $a4 = config('salon.ticket.papier') === 'a4';
    $cadre = request()->boolean('cadre');
    $autoImpression = request()->boolean('imprimer') && config('salon.ticket.driver') === 'navigateur';
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $vente->numero }}</title>
    <style>
        {{-- Thermique : le format du rouleau est donné par l'imprimante. A4 : ticket en haut de la feuille. --}}
        @page { margin: {{ $a4 ? '10mm' : '0' }}; @if($a4) size: A4 portrait; @endif }
        body { font-family: 'Courier New', Consolas, monospace; font-size: {{ $largeur === 58 ? 11 : 12 }}px; margin: 0; background: #f1f5f9; color: #000; }
        .ticket { width: {{ $largeur - 8 }}mm; margin: 12px auto; padding: 3mm 4mm; background: #fff; position: relative; overflow: hidden; }
        /* Logo et filigrane : images en niveaux de gris préparées pour l'impression thermique */
        .logo { display: block; width: {{ $largeur === 58 ? 18 : 22 }}mm; height: auto; margin: 0 auto 2mm; }
        .filigrane {
            position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
            width: {{ $largeur === 58 ? 40 : 56 }}mm; height: auto; pointer-events: none; z-index: 0;
        }
        .ticket > *:not(.filigrane) { position: relative; z-index: 1; }
        .centre { text-align: center; }
        .gras { font-weight: bold; }
        .nom-salon { font-size: {{ $largeur === 58 ? 14 : 16 }}px; font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        td.m { text-align: right; white-space: nowrap; padding-left: 6px; }
        .detail { color: #333; font-size: .92em; }
        .total td { font-size: {{ $largeur === 58 ? 15 : 18 }}px; font-weight: bold; padding: 4px 0; }
        .annule { border: 2px solid #000; text-align: center; font-weight: bold; padding: 4px; margin: 6px 0; }
        .actions { text-align: center; margin: 12px; font-family: 'Segoe UI', sans-serif; }
        .actions a, .actions button { display: inline-block; margin: 0 4px; padding: 10px 16px; border-radius: 8px; border: 0; cursor: pointer; font-size: 14px; font-weight: 600; text-decoration: none; font-family: inherit; }
        .imprimer { background: #059669; color: #fff; }
        .retour { background: #e2e8f0; color: #0f172a; }
        .alerte { max-width: 72mm; margin: 12px auto 0; padding: 8px; border-radius: 6px; font-family: 'Segoe UI', sans-serif; font-size: 13px; }
        @media print {
            body { background: #fff; }
            /* Le ticket garde sa largeur de ticket, même sur une feuille A4 */
            .ticket { margin: 0 auto; width: {{ $largeur - 8 }}mm; }
            .actions, .alerte { display: none; }
            @if($a4)
            .ticket { border: 1px dashed #999; margin-top: 0; }
            @endif
        }
    </style>
</head>
<body>
@if(session('succes'))<div class="alerte" style="background:#d1fae5;color:#065f46">{{ session('succes') }}</div>@endif
@if(session('erreur'))<div class="alerte" style="background:#fee2e2;color:#991b1b">{{ session('erreur') }}</div>@endif

<div class="ticket">
    @if(config('salon.ticket.filigrane'))
        <img class="filigrane" src="{{ asset('images/filigrane-ticket.png') }}" alt="">
    @endif
    @if(config('salon.ticket.logo'))
        <img class="logo" src="{{ asset('images/logo-ticket.png') }}" alt="{{ config('salon.nom') }}">
    @endif
    <div class="centre nom-salon">{{ config('salon.nom') }}</div>
    <div class="centre">{{ config('salon.adresse') }}</div>
    @if(config('salon.telephone'))<div class="centre">Tél : {{ config('salon.telephone') }}</div>@endif
    <div class="sep"></div>

    <table>
        <tr><td>Ticket</td><td class="m gras">{{ $vente->numero }}</td></tr>
        <tr><td>Date</td><td class="m">{{ $vente->created_at->format('d/m/Y H:i') }}</td></tr>
    </table>
    @if($vente->estAnnulee())<div class="annule">TICKET ANNULÉ</div>@endif
    <div class="sep"></div>

    <table>
        @foreach($vente->lignes as $ligne)
            <tr><td colspan="2" class="gras">{{ $ligne->libelle }}</td></tr>
            <tr class="detail">
                <td>&nbsp;&nbsp;{{ $ligne->quantite }} x {{ Fcfa::format($ligne->prix_unitaire) }}</td>
                <td class="m">{{ Fcfa::format($ligne->total) }}</td>
            </tr>
        @endforeach
    </table>
    <div class="sep"></div>

    <table>
        <tr class="total"><td>TOTAL</td><td class="m">{{ Fcfa::format($vente->total) }}</td></tr>
        <tr><td>Paiement</td><td class="m">{{ $vente->libelleModePaiement() }}</td></tr>
        @if($vente->montant_recu !== null)
            <tr><td>Reçu</td><td class="m">{{ Fcfa::format($vente->montant_recu) }}</td></tr>
            <tr><td>Monnaie rendue</td><td class="m">{{ Fcfa::format($vente->monnaie_rendue) }}</td></tr>
        @endif
    </table>
    <div class="sep"></div>
    @if($vente->vendeuse)<div class="centre">Servi par : {{ $vente->vendeuse->nom }}</div>@endif
    <div class="centre" style="margin-top:4px">{{ config('salon.message_ticket') }}</div>
</div>

@unless($cadre)
<div class="actions">
    @if(config('salon.ticket.driver') === 'escpos')
        <form method="POST" action="{{ route('tickets.imprimer', $vente) }}" style="display:inline">
            @csrf
            <button class="imprimer" type="submit">Réimprimer</button>
        </form>
    @else
        <button class="imprimer" onclick="window.print()">Imprimer</button>
    @endif
    <a class="retour" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('caisse.index') }}">Retour</a>
</div>
@endunless

@if($autoImpression)
<script>
    window.addEventListener('load', () => window.print());
    @unless($cadre)
    window.addEventListener('afterprint', () => { window.location.href = @json(route('caisse.index')); });
    @endunless
</script>
@endif
</body>
</html>
