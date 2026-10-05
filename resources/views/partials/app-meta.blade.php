{{-- Icônes et manifeste : logo dans l'onglet, la barre des tâches, et installation comme application --}}
<link rel="icon" href="{{ asset('icone-salon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('images/icone-192.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#0b2a4a">
<meta name="application-name" content="SalonFlow">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {}));
    }
</script>
