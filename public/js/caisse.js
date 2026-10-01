// Caisse : le ticket vit dans le navigateur, le serveur recalcule les prix à l'encaissement.
(function () {
    const { services, url, csrf, vendeuses, jour, demo } = window.CAISSE;
    const parId = new Map(services.map((s) => [s.id, s]));
    // Lignes du ticket : clé "id" (prix fixe) ou "id@prix" (prix variable saisi) -> { id, prix, quantite }
    const panier = new Map();
    let categorie = '';
    let envoiEnCours = false;

    // ---------- Vendeuse : reste sélectionnée d'une vente à l'autre (et après rechargement) ----------
    let vendeuse = null;
    try {
        const memo = Number(localStorage.getItem('salonflow.vendeuse'));
        if (vendeuses.includes(memo)) vendeuse = memo;
    } catch (e) { /* stockage indisponible : on choisit à chaque ouverture */ }
    if (vendeuses.length === 1) vendeuse = vendeuses[0];

    const el = (id) => document.getElementById(id);
    const grille = el('grille');
    const fcfa = (n) => new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' FCFA';
    const echapper = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const sansAccents = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const initiales = (nom) => nom.split(/[\s'+&-]+/).filter((m) => m.length > 1 || /\d/.test(m)).slice(0, 2).map((m) => m[0]).join('').toUpperCase();
    const teinte = (hex, alpha) => {
        const n = parseInt(hex.slice(1), 16);
        return `rgba(${n >> 16}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`;
    };

    // ---------- Cartes de services ----------
    grille.innerHTML = services.map((s) => `
        <button type="button" class="service" data-id="${s.id}" data-categorie="${s.categorie_id}"
                data-recherche="${echapper(sansAccents(s.nom + ' ' + s.code + ' ' + s.categorie))}">
            <div class="visuel" style="background:${teinte(s.couleur, .14)};color:${s.couleur}">
                <span class="categorie-badge" style="color:${s.couleur}">${echapper(s.categorie)}</span>
                <span class="initiales">${echapper(initiales(s.nom))}</span>
                <span class="qte"></span>
            </div>
            <div class="infos">
                <span class="code">${echapper(s.code)}</span>
                <span class="nom">${echapper(s.nom)}</span>
                <span class="prix">${s.variable ? '<small>à partir de</small>' : ''}${fcfa(s.prix)}</span>
            </div>
        </button>`).join('');

    grille.addEventListener('click', (e) => {
        const carte = e.target.closest('.service');
        if (!carte) return;
        const service = parId.get(Number(carte.dataset.id));
        if (service.variable) {
            demanderPrix(service);
        } else {
            ajouter(String(service.id));
        }
        carte.classList.remove('ajout');
        void carte.offsetWidth; // relance l'animation
        carte.classList.add('ajout');
    });

    function filtrer() {
        const texte = sansAccents(el('recherche').value.trim());
        let visibles = 0;
        grille.querySelectorAll('.service').forEach((carte) => {
            const ok = (!categorie || carte.dataset.categorie === categorie)
                && (!texte || carte.dataset.recherche.includes(texte));
            carte.hidden = !ok;
            if (ok) visibles++;
        });
        el('aucun').hidden = visibles > 0 || services.length === 0;
    }

    el('recherche').addEventListener('input', filtrer);
    el('pastilles').addEventListener('click', (e) => {
        const bouton = e.target.closest('button');
        if (!bouton) return;
        el('pastilles').querySelectorAll('button').forEach((b) => b.classList.toggle('actif', b === bouton));
        categorie = bouton.dataset.categorie;
        filtrer();
    });

    // ---------- Prix variable (« à partir de ») : on demande le prix réel ----------
    const dialogue = el('dialogue-prix');
    let serviceEnCours = null;

    function demanderPrix(service) {
        serviceEnCours = service;
        el('dp-nom').textContent = service.nom;
        el('dp-minimum').textContent = fcfa(service.prix);
        el('dp-prix').min = service.prix;
        el('dp-prix').value = service.prix;
        el('dp-erreur').textContent = '';
        el('dp-rapides').innerHTML = [0, 2000, 5000, 10000]
            .map((plus) => `<button type="button" data-prix="${service.prix + plus}">${fcfa(service.prix + plus)}</button>`).join('');
        dialogue.showModal();
        el('dp-prix').select();
    }

    el('dp-rapides').addEventListener('click', (e) => {
        const bouton = e.target.closest('button[data-prix]');
        if (bouton) { el('dp-prix').value = bouton.dataset.prix; el('dp-erreur').textContent = ''; el('dp-prix').focus(); }
    });
    el('dp-prix').addEventListener('input', () => { el('dp-erreur').textContent = ''; });
    el('dp-annuler').addEventListener('click', () => dialogue.close());
    el('dp-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const prix = Math.round(Number(el('dp-prix').value));
        if (!prix || prix < serviceEnCours.prix) {
            el('dp-erreur').textContent = 'Le prix doit être d\'au moins ' + fcfa(serviceEnCours.prix) + '.';
            return;
        }
        dialogue.close();
        ajouter(serviceEnCours.id + '@' + prix, 1, prix);
    });

    // ---------- Ticket ----------
    function ajouter(cle, delta = 1, prixSaisi = null) {
        let ligne = panier.get(cle);
        if (!ligne) {
            const id = Number(cle.split('@')[0]);
            ligne = { id, prix: prixSaisi ?? parId.get(id).prix, quantite: 0 };
            panier.set(cle, ligne);
        }
        ligne.quantite = Math.min(ligne.quantite + delta, 99);
        if (ligne.quantite <= 0) panier.delete(cle);
        el('message').textContent = '';
        afficher();
    }

    function total() {
        let t = 0;
        panier.forEach((l) => { t += l.prix * l.quantite; });
        return t;
    }

    function modePaiement() {
        return document.querySelector('input[name=mode]:checked').value;
    }

    function afficher() {
        const lignes = el('lignes');
        lignes.querySelectorAll('.tligne').forEach((l) => l.remove());
        el('ticket-vide').hidden = panier.size > 0;
        el('vider').hidden = panier.size === 0;

        const parService = new Map();
        panier.forEach((l, cle) => {
            const s = parId.get(l.id);
            parService.set(l.id, (parService.get(l.id) || 0) + l.quantite);
            const ligne = document.createElement('div');
            ligne.className = 'tligne';
            ligne.innerHTML = `
                <div class="desc"><b>${echapper(s.nom)}</b><small>${fcfa(l.prix)}${s.variable ? ' · prix saisi' : ''}</small></div>
                <div class="quantite">
                    <button type="button" data-action="moins" aria-label="Retirer un">−</button>
                    <span>${l.quantite}</span>
                    <button type="button" data-action="plus" aria-label="Ajouter un">+</button>
                </div>
                <div class="montant">${fcfa(l.prix * l.quantite)}</div>
                <button type="button" class="suppr" data-action="suppr" aria-label="Supprimer la ligne">✕</button>`;
            ligne.dataset.cle = cle;
            lignes.appendChild(ligne);
        });

        grille.querySelectorAll('.service').forEach((carte) => {
            const q = parService.get(Number(carte.dataset.id)) || 0;
            carte.classList.toggle('dans-ticket', q > 0);
            carte.querySelector('.qte').textContent = q ? '×' + q : '';
        });

        el('total').textContent = fcfa(total());
        afficherMonnaie();
    }

    el('lignes').addEventListener('click', (e) => {
        const bouton = e.target.closest('button[data-action]');
        if (!bouton) return;
        const cle = bouton.closest('.tligne').dataset.cle;
        const action = bouton.dataset.action;
        if (action === 'plus') ajouter(cle, 1);
        if (action === 'moins') ajouter(cle, -1);
        if (action === 'suppr') { panier.delete(cle); afficher(); }
    });

    el('vider').addEventListener('click', () => {
        if (confirm('Vider le ticket ?')) { panier.clear(); vider(); }
    });

    // ---------- Paiement ----------
    function afficherMonnaie() {
        const especes = modePaiement() === 'especes';
        el('bloc-especes').hidden = !especes;
        const recu = el('recu').value === '' ? null : Number(el('recu').value);
        const monnaie = el('monnaie');
        const rendu = recu === null ? 0 : recu - total();
        monnaie.textContent = fcfa(Math.max(0, rendu));
        monnaie.classList.toggle('insuffisant', recu !== null && rendu < 0);
        if (recu !== null && rendu < 0) monnaie.textContent = 'Manque ' + fcfa(-rendu);

        el('encaisser').disabled = envoiEnCours || panier.size === 0 || (especes && recu !== null && rendu < 0);
    }

    document.querySelectorAll('input[name=mode]').forEach((r) => r.addEventListener('change', afficherMonnaie));
    el('recu').addEventListener('input', afficherMonnaie);
    el('billets').addEventListener('click', (e) => {
        const bouton = e.target.closest('button');
        if (!bouton) return;
        el('recu').value = bouton.dataset.billet === 'exact' ? total() : bouton.dataset.billet;
        afficherMonnaie();
    });

    const zoneVendeuses = el('vendeuses');
    function afficherVendeuse() {
        if (!zoneVendeuses) return;
        zoneVendeuses.querySelectorAll('button').forEach((b) => b.classList.toggle('actif', Number(b.dataset.id) === vendeuse));
    }
    if (zoneVendeuses) {
        zoneVendeuses.addEventListener('click', (e) => {
            const bouton = e.target.closest('button[data-id]');
            if (!bouton) return;
            vendeuse = Number(bouton.dataset.id);
            zoneVendeuses.classList.remove('a-choisir');
            try { localStorage.setItem('salonflow.vendeuse', vendeuse); } catch (err) { /* ignoré */ }
            el('message').textContent = '';
            afficherVendeuse();
        });
    }

    function majKpiJour(montant) {
        jour.ca += montant;
        jour.tickets += 1;
        el('kpi-ca').textContent = fcfa(jour.ca);
        el('kpi-tickets').textContent = jour.tickets;
        el('kpi-panier').textContent = fcfa(Math.round(jour.ca / jour.tickets));
    }

    function vider() {
        el('recu').value = '';
        document.querySelector('input[name=mode]').checked = true;
        afficher();
    }

    async function encaisser() {
        if (el('encaisser').disabled) return;
        if (vendeuses.length && !vendeuse) {
            zoneVendeuses.classList.remove('a-choisir');
            void zoneVendeuses.offsetWidth;
            zoneVendeuses.classList.add('a-choisir');
            el('message').className = 'ticket-message erreur-msg';
            el('message').textContent = 'Touchez d\'abord le nom de la vendeuse.';
            return;
        }
        envoiEnCours = true;
        afficherMonnaie();
        const message = el('message');
        message.className = 'ticket-message';
        message.textContent = 'Enregistrement…';

        const especes = modePaiement() === 'especes';
        try {
            const reponse = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({
                    vendeuse_id: vendeuse,
                    lignes: [...panier.values()].map((l) => ({
                        service_id: l.id,
                        quantite: l.quantite,
                        prix: parId.get(l.id).variable ? l.prix : null,
                    })),
                    mode_paiement: modePaiement(),
                    montant_recu: especes && el('recu').value !== '' ? Number(el('recu').value) : null,
                }),
            });
            const donnees = await reponse.json().catch(() => ({}));

            if (reponse.status === 419 || reponse.status === 401) {
                throw new Error('Session expirée : rechargez la page (F5) puis recommencez.');
            }
            if (!reponse.ok) {
                const erreurs = donnees.errors ? Object.values(donnees.errors).flat() : [];
                throw new Error(erreurs[0] || donnees.message || 'Erreur lors de l\'enregistrement.');
            }

            if (demo) {
                // Démo : aperçu du ticket à l'écran, sans fenêtre d'impression
                el('apercu-cadre').src = donnees.ticket_url.replace('imprimer=1', 'imprimer=0') + '&cadre=1';
                el('apercu-ticket').hidden = false;
            } else {
                // Impression dans un cadre caché : la caisse reste ouverte, prête pour le client suivant
                el('impression').src = donnees.ticket_url + '&cadre=1';
            }
            majKpiJour(donnees.total);
            panier.clear();
            vider();
            message.className = 'ticket-message';
            message.innerHTML = `✅ Ticket <b>${echapper(donnees.numero)}</b> enregistré. `
                + `<a href="${echapper(donnees.ticket_url.replace('imprimer=1', 'imprimer=0'))}">Voir / réimprimer</a>`;
        } catch (erreur) {
            message.className = 'ticket-message erreur-msg';
            message.textContent = erreur.message;
        } finally {
            envoiEnCours = false;
            afficherMonnaie();
        }
    }

    el('encaisser').addEventListener('click', encaisser);
    if (demo) el('apercu-fermer').addEventListener('click', () => { el('apercu-ticket').hidden = true; });

    // Raccourcis clavier : / pour rechercher, Entrée dans la recherche = ajoute le premier résultat
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && document.activeElement !== el('recherche') && document.activeElement !== el('recu')) {
            e.preventDefault();
            el('recherche').focus();
        }
    });
    el('recherche').addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const premier = grille.querySelector('.service:not([hidden])');
        if (premier) premier.click();
        el('recherche').select();
    });

    afficherVendeuse();
    afficher();
})();
