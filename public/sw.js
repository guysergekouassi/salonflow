// Service worker minimal : il permet à Chrome d'installer SalonFlow comme une application.
// Il ne met rien en cache : les pages viennent toujours du serveur local (ventes, jetons CSRF à jour).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
