<?php

return [

    'nom' => env('SALON_NOM', 'SalonFlow'),
    'adresse' => env('SALON_ADRESSE', "Abidjan, Côte d'Ivoire"),
    'telephone' => env('SALON_TELEPHONE', ''),
    'message_ticket' => env('SALON_MESSAGE_TICKET', 'Merci de votre visite, à bientôt !'),

    // Après le code PIN, le mode gérante (prix, annulations, vendeuses) reste ouvert X minutes
    'mode_gerante_minutes' => (int) env('SALON_MODE_GERANTE_MINUTES', 10),

    // Modes de paiement acceptés à la caisse (le salon n'encaisse qu'en espèces)
    'modes_paiement' => [
        'especes' => 'Espèces',
    ],

    'ticket' => [
        // Largeur du papier de l'imprimante thermique : 80 ou 58 (mm)
        'largeur_mm' => (int) env('TICKET_LARGEUR_MM', 80),
        // Logo du salon en haut du ticket, et en filigrane derrière le texte (mettre false pour les retirer)
        'logo' => (bool) env('TICKET_LOGO', true),
        'filigrane' => (bool) env('TICKET_FILIGRANE', true),
        // navigateur : ticket HTML imprimé par Chrome (fonctionne partout)
        // escpos     : impression directe sur l'imprimante (composer require mike42/escpos-php)
        'driver' => env('TICKET_DRIVER', 'navigateur'),
        'connecteur' => env('TICKET_CONNECTEUR', 'windows'), // windows | network | fichier
        'cible' => env('TICKET_CIBLE', 'TICKET'),           // nom de partage Windows, IP, ou /dev/usb/lp0
        'port' => (int) env('TICKET_PORT', 9100),
    ],

];
