# Audit front club — stabilisation finale

## Shortcodes et méthodes

- `/tableau-de-bord-club/` utilise le shortcode `[ufsc_club_dashboard]`, enregistré sur `UFSC_Frontend_Shortcodes::render_club_dashboard()`.
- `/compte-club/` utilise le shortcode `[ufsc_club_profile]`, enregistré sur `UFSC_Frontend_Shortcodes::render_club_profile()`.
- Les sous-rendus réellement utilisés restent `render_club_licences()`, `render_club_stats()`, `render_add_licence()`, `render_archived_licences_section()` et `render_club_documents_list()` selon les sections activées.

## CSS chargé et ordre

1. `assets/css/ufsc-front.css`, handle WordPress `ufsc-front`, enqueued directement par les shortcodes front club.
2. Les shortcodes licences plus anciens peuvent aussi charger `assets/css/ufsc-frontend.css` via le handle `ufsc-licence-form`; ce fichier reste hors scope du portail final.
3. Les styles de thème Astra/Elementor s'appliquent avant/au niveau des wrappers de page; le correctif cible uniquement `.ufsc-club-portal` pour éviter un hack global du thème.

## Scopes historiques observés

- `.ufsc-club-account` : conservé comme hook de compatibilité markup/tests.
- `.ufsc-premium-v3` : conservé comme hook de compatibilité, mais ne définit plus la source de vérité du layout club.
- `.ufsc-club-dashboard` et `.ufsc-club-profile` : conservés pour les shortcodes existants.
- `.ufsc-club-portal` : nouveau scope canonique commun au tableau de bord et au compte club.

## Causes racines

- Flash de mise en page : les images du logo/badge et de la photo club n'avaient pas toutes des dimensions HTML stables; leur taille initiale pouvait dépendre du chargement tardif du CSS.
- KPI coupés : le hero dashboard combinait d'anciens scopes et une colonne droite insuffisamment bornée; les KPI n'étaient pas systématiquement en `minmax(0, 1fr)`.
- Colonne étroite compte club : d'anciens `max-width: 400px`/`280px` et un layout profil secondaire limitaient des blocs desktop et donnaient un rendu mobile centré.
- Ancres inactives : plusieurs liens pointaient vers `#ufsc-section-profile` ou `#ufsc-profile-documents` au lieu de cibles métier uniques.
- Archives licences : le rendu existait seulement derrière `ufsc_show_archives=1`, donc le lien `#ufsc-licences-archives` pouvait pointer vers une section absente.
- Affiliation : la variable `$affiliation_pending` était utilisée dans le rendu CTA sans initialisation locale explicite et le statut `validated` devait être traité comme `active`.

## Nettoyage CSS réalisé

- Fusion des règles de largeur dans `.ufsc-club-portal` avec `width: min(100% - 32px, 1280px)`.
- Remplacement des petits `max-width: 280px/400px` incompatibles avec le desktop.
- Centralisation des grilles KPI, formulaires, dirigeants, documents et savebar dans le scope canonique.
- Conservation des anciens scopes uniquement pour compatibilité markup; aucun nouveau scope versionné n'a été créé.
- Aucune règle `zoom` ni `transform: scale()` n'est utilisée.

## Recette visuelle

La recette navigateur sur le site dev réel reste à exécuter avant fusion, faute d'URL/dev WordPress authentifié disponible dans cet environnement CLI. Les points à valider sont les résolutions 1440×900, 1280×800, 1024×768, 768×1024 et 375×812, avec captures dashboard et compte club.

## Audit complémentaire PR #492 — largeur réelle Astra/Elementor

Les captures de recette PR #492 montrent que la règle `width: min(100% - 32px, 1280px)` était calculée dans le bloc contenant du shortcode au lieu de neutraliser le wrapper de shortcode lui-même. Le premier verrou de largeur à corriger côté code est donc le couple Elementor `elementor-widget-shortcode` / `elementor-widget-container` qui contient directement `.ufsc-club-portal`, puis les wrappers Astra standards `.site-content > .ast-container`, `.content-area`, `.site-main` et `.entry-content`.

Correctif appliqué :

- ajout de la classe body `ufsc-club-portal-page` uniquement aux pages contenant `[ufsc_club_dashboard]` ou `[ufsc_club_profile]` ;
- suppression de la règle large `body.ufsc-club-portal-page :is(...):has(.ufsc-club-portal)` qui boxait trop d'ancêtres imbriqués ;
- neutralisation explicite des wrappers Astra standards à `max-width: none` ;
- neutralisation ciblée du widget Elementor shortcode et de son conteneur uniquement lorsqu'ils contiennent `.ufsc-club-portal` ;
- le seul élément borné à `max-width: 1280px` reste `.ufsc-club-portal` ;
- aucune règle `100vw`, marge négative, zoom ou scale ;
- grille dashboard ramenée à `minmax(0, 1fr) 300px`, avec bascule en une colonne sous 1100 px.

La PR doit rester en brouillon jusqu'à capture dev réelle, avec le premier parent étroit à confirmer dans l'inspecteur si un réglage Elementor impose encore une colonne boxed non modifiable par CSS de thème.

## Audit complémentaire PR #492 — KPI admin métier

Les KPI techniques suivants ont été retirés du tableau principal :

- statut historique non déterminable ;
- licences avec saison prouvée / qualité de colonne ;
- licences historiques sans saison ;
- clubs sans numéro d'affiliation pour la saison.

Le tableau principal est limité à 7 cartes métier cliquables :

1. Clubs enregistrés : clubs permanents non supprimés.
2. Affiliations actives : affiliation annuelle `active/validated` pour la saison courante.
3. Renouvellements à traiter : clubs avec activité historique mais sans affiliation annuelle active/validated.
4. Affiliations en attente : `pending_payment`, `pending_validation`, `correction_required` et alias historiques pending.
5. Dossiers clubs incomplets : clubs distincts avec au moins un document permanent obligatoire manquant.
6. Licences actives : compteur canonique de licences du périmètre.
7. Numéros annuels à attribuer : affiliations annuelles active/validated avec numéro annuel vide uniquement.

Le helper `get_admin_kpi_filter_condition()` centralise les conditions utilisées par les cartes et la liste filtrée via `kpi_filter`, afin d'éviter deux définitions divergentes.


## Audit complémentaire PR #492 — recette visuelle échouée

Les captures de recette ont invalidé la correction précédente :

- sur le tableau de bord, la colonne KPI utilisait encore des minima cumulés trop larges (`minmax(520px, 1.14fr)` et KPI `minmax(220px, 1fr)` dans un panneau droit), ce qui poussait les cartes hors du hero ;
- sur le compte club, les règles historiques `.ufsc-club-account.ufsc-premium-v3` redéfinissaient encore `.ufsc-club-profile-layout`, `.ufsc-club-profile-main` et `.ufsc-profile-cards` en grilles imbriquées, ce qui créait des cartes intrinsèques de 120 à 250 px dans certains wrappers Elementor ;
- la règle parent large avec `:is(...):has(.ufsc-club-portal)` fixait trop d'ancêtres et ne garantissait pas le wrapper utile.

Nettoyage de cette itération :

- suppression de la logique d'élargissement multi-ancêtres ;
- passage du formulaire compte club à une grille simple à deux colonnes (`repeat(2, minmax(0, 1fr))`) ;
- sections dirigeants, distribution et documents marquées explicitement en pleine largeur avec `.ufsc-club-portal__section--full` ;
- grille dirigeants en `repeat(3, minmax(0, 1fr))` ;
- KPI compte club avec valeur et libellé séparés ;
- test statique `test-front-club-portal-layout-regression-static.php` pour bloquer le retour des règles qui ont produit les captures cassées.

La validation finale nécessite toujours une capture dev réelle, car l'environnement CLI n'a pas accès à la page WordPress authentifiée.
