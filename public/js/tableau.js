// Tableaux de données (recherche, tri, pagination, export) sans librairie externe : tout fonctionne hors ligne.
// Usage : <table class="tableau" data-tableau data-titre="Liste des ventes" data-fichier="ventes">
//   <th data-tri="non">           colonne non triable
//   <th data-export="non">        colonne exclue de l'export et de la recherche (ex. Actions)
//   <td data-tri="1696150000">    valeur utilisée pour trier (dates, montants)
//   <td data-export="3000">       valeur exportée à la place du texte affiché
(function () {
    const normaliser = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    const propre = (t) => t.replace(/\s+/g, ' ').trim();
    const nombre = (v) => (v !== '' && !isNaN(Number(v)) ? Number(v) : null);

    function comparer(a, b) {
        const na = nombre(a), nb = nombre(b);
        if (na !== null && nb !== null) return na - nb;
        return a.localeCompare(b, 'fr', { numeric: true, sensitivity: 'base' });
    }

    function icone(chemin) {
        return `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${chemin}</svg>`;
    }

    function creer(table) {
        const entetes = [...table.tHead.rows[0].cells];
        const colonnes = entetes.map((th) => ({
            libelle: propre(th.textContent),
            triable: th.dataset.tri !== 'non',
            exportable: th.dataset.export !== 'non',
        }));
        const lignes = [...table.tBodies[0].rows].map((tr) => ({
            tr,
            recherche: normaliser(colonnes.map((c, i) => (c.exportable && tr.cells[i] ? tr.cells[i].textContent : '')).join(' ')),
            tri: [...tr.cells].map((td) => td.dataset.tri ?? propre(td.textContent)),
        }));

        const etat = {
            recherche: '',
            parPage: Number(table.dataset.parPage || 10),
            page: 1,
            colonne: table.dataset.triColonne !== undefined ? Number(table.dataset.triColonne) : null,
            ordre: table.dataset.triOrdre || 'asc',
        };

        // ---------- Structure autour du tableau ----------
        const carte = document.createElement('div');
        carte.className = 'dt';
        table.parentNode.insertBefore(carte, table);
        carte.innerHTML = `
            <div class="dt-entete">
                <h3>${table.dataset.titre || ''}</h3>
                <button type="button" class="dt-exporter">${icone('<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5M12 15V3"/>')} Exporter</button>
            </div>
            <div class="dt-controles">
                <label class="dt-longueur">Afficher
                    <select>${[10, 25, 50, 100].map((n) => `<option value="${n}">${n}</option>`).join('')}</select>
                    éléments</label>
                <label class="dt-recherche">Rechercher : <input type="search" autocomplete="off"></label>
            </div>
            <div class="dt-defilement"></div>
            <div class="dt-pied">
                <div class="dt-info"></div>
                <div class="dt-pages"></div>
            </div>`;
        carte.querySelector('.dt-defilement').appendChild(table);

        const select = carte.querySelector('.dt-longueur select');
        if (![...select.options].some((o) => Number(o.value) === etat.parPage)) {
            select.insertAdjacentHTML('afterbegin', `<option value="${etat.parPage}">${etat.parPage}</option>`);
        }
        select.value = etat.parPage;

        entetes.forEach((th, i) => {
            if (!colonnes[i].triable) return;
            th.classList.add('dt-triable');
            th.tabIndex = 0;
            const trier = () => {
                etat.ordre = etat.colonne === i && etat.ordre === 'asc' ? 'desc' : 'asc';
                etat.colonne = i;
                etat.page = 1;
                afficher();
            };
            th.addEventListener('click', trier);
            th.addEventListener('keydown', (e) => { if (e.key === 'Enter') trier(); });
        });

        const tbody = table.tBodies[0];
        const vide = document.createElement('tr');
        vide.innerHTML = `<td class="dt-vide" colspan="${colonnes.length}"></td>`;

        function resultat() {
            const termes = normaliser(etat.recherche).split(' ').filter(Boolean);
            const filtrees = lignes.filter((l) => termes.every((t) => l.recherche.includes(t)));
            if (etat.colonne !== null) {
                const sens = etat.ordre === 'asc' ? 1 : -1;
                filtrees.sort((a, b) => sens * comparer(a.tri[etat.colonne] ?? '', b.tri[etat.colonne] ?? ''));
            }
            return filtrees;
        }

        function afficher() {
            const filtrees = resultat();
            const pages = Math.max(1, Math.ceil(filtrees.length / etat.parPage));
            etat.page = Math.min(etat.page, pages);
            const debut = (etat.page - 1) * etat.parPage;
            const visibles = filtrees.slice(debut, debut + etat.parPage);

            tbody.replaceChildren(...visibles.map((l) => l.tr));
            if (!visibles.length) {
                vide.firstChild.textContent = lignes.length ? 'Aucun élément ne correspond à la recherche.' : 'Aucune donnée disponible.';
                tbody.appendChild(vide);
            }

            entetes.forEach((th, i) => {
                th.classList.toggle('dt-asc', etat.colonne === i && etat.ordre === 'asc');
                th.classList.toggle('dt-desc', etat.colonne === i && etat.ordre === 'desc');
            });

            const info = carte.querySelector('.dt-info');
            info.textContent = filtrees.length
                ? `Affichage de l'élément ${debut + 1} à ${debut + visibles.length} sur ${filtrees.length} élément${filtrees.length > 1 ? 's' : ''}`
                : 'Affichage de 0 élément';
            if (filtrees.length !== lignes.length) info.textContent += ` (filtré de ${lignes.length} au total)`;

            afficherPages(pages);
        }

        function afficherPages(pages) {
            const zone = carte.querySelector('.dt-pages');
            const numeros = [];
            for (let p = 1; p <= pages; p++) {
                if (p === 1 || p === pages || Math.abs(p - etat.page) <= 1) numeros.push(p);
                else if (numeros[numeros.length - 1] !== '…') numeros.push('…');
            }
            zone.innerHTML = `<button type="button" data-page="${etat.page - 1}" ${etat.page === 1 ? 'disabled' : ''}>Précédent</button>`
                + numeros.map((p) => (p === '…'
                    ? '<span>…</span>'
                    : `<button type="button" data-page="${p}" class="${p === etat.page ? 'actif' : ''}">${p}</button>`)).join('')
                + `<button type="button" data-page="${etat.page + 1}" ${etat.page === pages ? 'disabled' : ''}>Suivant</button>`;
        }

        carte.querySelector('.dt-pages').addEventListener('click', (e) => {
            const bouton = e.target.closest('button[data-page]');
            if (!bouton || bouton.disabled) return;
            etat.page = Number(bouton.dataset.page);
            afficher();
        });
        select.addEventListener('change', () => { etat.parPage = Number(select.value); etat.page = 1; afficher(); });
        carte.querySelector('.dt-recherche input').addEventListener('input', (e) => { etat.recherche = e.target.value; etat.page = 1; afficher(); });

        // Export CSV lisible par Excel (point-virgule + BOM UTF-8), avec la recherche et le tri en cours
        const exporter = carte.querySelector('.dt-exporter');
        exporter.disabled = lignes.length === 0;
        exporter.addEventListener('click', () => {
            const cellule = (t) => `"${String(t).replace(/"/g, '""')}"`;
            const indices = colonnes.map((c, i) => (c.exportable ? i : null)).filter((i) => i !== null);
            const csv = [indices.map((i) => cellule(colonnes[i].libelle)).join(';')]
                .concat(resultat().map((l) => indices.map((i) => {
                    const td = l.tr.cells[i];
                    return cellule(td ? (td.dataset.export ?? propre(td.textContent)) : '');
                }).join(';')))
                .join('\r\n');
            const lien = document.createElement('a');
            lien.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
            lien.download = `${table.dataset.fichier || 'export'}-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(lien);
            lien.click();
            setTimeout(() => { URL.revokeObjectURL(lien.href); lien.remove(); }, 0);
        });

        afficher();
    }

    document.querySelectorAll('table[data-tableau]').forEach(creer);
})();
