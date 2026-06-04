# Diagnostic court — écarts licences admin / espace club front-end

## Périmètre

- Diagnostic uniquement : aucune correction PHP, aucune modification de données, aucune migration.
- Plugin compétition non modifié et non audité fonctionnellement ; seuls les points de confusion visibles dans ce dépôt ont été relevés.

## Fichiers trouvés

### Rendu admin des licences UFSC Gestion

- `includes/admin/class-admin-menu.php`
  - `UFSC_CL_Admin_Menu::register()` ajoute le menu principal `ufsc-dashboard` et le sous-menu `Licences` avec le slug `ufsc_lc_licences`.
  - Ce sous-menu appelle `UFSC_SQL_Admin::render_licences()`.
- `includes/admin/class-sql-admin.php`
  - `UFSC_SQL_Admin::register_hidden_pages()` enregistre aussi des pages cachées de licences : `ufsc_lc_licences`, `ufsc-sql-licences`, `ufsc-sql-licenses`.
  - `UFSC_SQL_Admin::get_licences_admin_page_slugs()` accepte aussi `ufsc-gestion-licences` et `ufsc-licences` comme slugs historiques.
  - `UFSC_SQL_Admin::render_licences()` construit le rendu admin principal : filtres, pagination, requête SQL, tableau HTML, formulaires d'action.
- `inc/admin/menu.php`
  - Ancien menu UFSC Gestion avec slug `ufsc-gestion-licences` et callback `ufsc_render_licences_page()`.
  - Dans le bootstrap principal, ce fichier est commenté et indiqué comme remplacé par le menu unifié.
- `inc/admin/class-ufsc-gestion-licences-list-table.php`
  - Ancien `WP_List_Table` utilisé par `ufsc_render_licences_page()` si l'ancien fichier `inc/admin/menu.php` est chargé par ailleurs.
- `includes/admin/class-ufsc-simplified-admin.php`
  - Ajoute des alias autorisés de pages licences, dont `ufsc_lc_licences`, `ufsc-gestion-licences`, `ufsc_licences`, `ufsc-licence`, `ufsc_licence`, `ufsc-licence-documents`, `ufsc-licences-dashboard`.

### Rendu front-end des licences dans l'espace club

- `includes/frontend/class-frontend-shortcodes.php`
  - `UFSC_Frontend_Shortcodes::register()` déclare notamment `[ufsc_club_dashboard]`, `[ufsc_club_licences]`, `[ufsc_add_licence]` et `[ufsc_licences]`.
  - `UFSC_Frontend_Shortcodes::render_club_licences()` rend la liste des licences du club connecté.
  - `UFSC_Frontend_Shortcodes::get_club_licences()` récupère les lignes affichées.
  - `UFSC_Frontend_Shortcodes::get_club_licences_count()` calcule la pagination avec les mêmes filtres.
- `templates/frontend/licences-list.php`
  - Template de cartes licences ; il affiche les licences reçues via la variable `$licences`.
- `templates/frontend/club-dashboard.php`
  - Tableau de bord front plus ancien/alternatif avec filtres visuels, à vérifier ensuite si la page front l'utilise réellement.

## Fonctions / classes importantes

- `UFSC_CL_Admin_Menu::register()` : menu admin unifié.
- `UFSC_SQL_Admin::register_hidden_pages()` : alias cachés pour les pages admin licences.
- `UFSC_SQL_Admin::render_licences()` : rendu admin principal.
- `UFSC_SQL_Admin::build_licence_where_conditions()` : filtres admin.
- `UFSC_SQL_Admin::build_licence_visibility_condition()` : exclusion/inclusion de la corbeille via `deleted_at`.
- `UFSC_Gestion_Licences_List_Table::prepare_items()` : ancien tableau admin, si l'ancien menu est rechargé.
- `UFSC_Frontend_Shortcodes::render_club_licences()` : entrée front `[ufsc_club_licences]`.
- `UFSC_Frontend_Shortcodes::get_club_licences()` : requête front des licences.
- `UFSC_Frontend_Shortcodes::get_club_licences_count()` : comptage front.
- `UFSC_Frontend_Shortcodes::get_user_club_id()` et `UFSC_User_Club_Mapping::get_user_club_id()` : liaison utilisateur connecté → club.
- `ufsc_get_licences_table()` / `ufsc_get_clubs_table()` : tables front issues de `ufsc_gestion_settings`.
- `UFSC_SQL::get_settings()` : tables admin SQL issues de `ufsc_sql_settings`.

## Tables utilisées

Deux familles de réglages coexistent :

1. Réglages SQL historiques : option WordPress `ufsc_sql_settings`.
   - Par défaut : `clubs` et `licences`.
   - Utilisés par `UFSC_SQL::get_settings()`.
   - Utilisés par le rendu admin principal `UFSC_SQL_Admin::render_licences()`.
2. Réglages UFSC Gestion : option WordPress `ufsc_gestion_settings`.
   - Par défaut : `$wpdb->prefix . 'ufsc_clubs'` et `$wpdb->prefix . 'ufsc_licences'`.
   - Utilisés par `ufsc_get_clubs_table()` et `ufsc_get_licences_table()`.
   - Utilisés par le front `get_club_licences()` et par l'ancien `WP_List_Table`.

Conclusion table : le front utilise bien une table du plugin UFSC Gestion via `ufsc_get_licences_table()`, mais ce n'est pas forcément la même table que l'admin principal, qui utilise `UFSC_SQL::get_settings()`. Si `ufsc_sql_settings.table_licences` et `ufsc_gestion_settings.licences_table` divergent, l'admin et le front ne lisent pas la même source.

## Cause probable du double tableau admin

Cause la plus probable : coexistence de deux systèmes de rendu admin licences.

- Rendu actuel : menu unifié `UFSC_CL_Admin_Menu::register()` → slug `ufsc_lc_licences` → `UFSC_SQL_Admin::render_licences()`.
- Rendu ancien : `inc/admin/menu.php` → slug `ufsc-gestion-licences` → `ufsc_render_licences_page()` → `UFSC_Gestion_Licences_List_Table::display()`.
- Le bootstrap principal commente explicitement l'inclusion de `inc/admin/menu.php`, donc le double tableau ne devrait pas venir de ce fichier dans un chargement normal de ce plugin seul.
- Cependant `UFSC_Simplified_Admin` et `UFSC_SQL_Admin` conservent plusieurs alias de slugs licences, notamment `ufsc-gestion-licences`. Si un autre morceau de code, un mu-plugin, un ancien plugin actif ou une inclusion résiduelle recharge `inc/admin/menu.php`, le slug historique peut afficher l'ancien `WP_List_Table` en plus ou à côté du rendu `UFSC_SQL_Admin`.

Diagnostic court : le double tableau ressemble à un chevauchement entre l'ancien menu/rendu `inc/admin/menu.php` et le rendu unifié `UFSC_SQL_Admin::render_licences()`, aggravé par la conservation d'alias historiques de pages licences.

## Requête qui récupère les licences du club connecté

Dans le front :

1. `render_club_licences()` détermine le club : si aucun `club_id` n'est fourni au shortcode et si l'utilisateur est connecté, il appelle `self::get_user_club_id( get_current_user_id() )`.
2. `get_user_club_id()` délègue à `ufsc_get_user_club_id()` si disponible.
3. `ufsc_get_user_club_id()` appelle `UFSC_User_Club_Mapping::get_user_club_id()`.
4. `UFSC_User_Club_Mapping::get_user_club_id()` cherche le club dans la table clubs SQL par `responsable_id = user_id`.
5. `get_club_licences()` exécute ensuite une requête de ce type :

```sql
SELECT *, `statut` AS licence_statut
FROM `{ufsc_get_licences_table()}`
WHERE club_id = %d
  AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00') -- si la colonne existe
  -- + recherche éventuelle
  -- + statut éventuel
  -- + saison éventuelle
ORDER BY id DESC
LIMIT %d OFFSET %d
```

## Filtres pouvant masquer une licence pourtant valide

Sur le front :

- `club_id = %d` : une licence valide rattachée à un autre `club_id`, ou un compte club mal relié, ne sera pas visible.
- `deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00'` : toute licence avec `deleted_at` renseigné est masquée, même si son `statut` est valide.
- Filtre URL `ufsc_status` : si présent, il ajoute `statut IN (...)` après normalisation ; une valeur de statut non couverte ou mal normalisée peut masquer la licence.
- Filtre URL `ufsc_search` : limite aux champs existants `nom`, `nom_licence`, `prenom`, `email`.
- Attribut ou argument `season` : si renseigné, filtre sur la première colonne disponible parmi `season`, `saison`, `paid_season`.
- Pagination `per_page` / `ufsc_page` : la licence peut exister mais être sur une autre page.

Sur l'admin principal :

- Filtre région imposé par le scope utilisateur (`UFSC_Scope::get_user_scope_region()`).
- Filtre club.
- Filtre statut.
- Filtre paiement.
- Filtre doublon.
- Filtre visibilité `active`, `trash`, `all` basé sur `deleted_at`.

## Liaison du compte club connecté au `club_id`

La liaison n'utilise pas une table de relation séparée dans le chemin front principal. Elle repose sur la table clubs configurée par `UFSC_SQL::get_settings()` et sur une colonne responsable :

```sql
SELECT `{pk_col}`
FROM `{clubs_table}`
WHERE `{responsable_col}` = %d
LIMIT 1
```

- `pk_col` vient de `ufsc_club_col('id')` ou vaut `id`.
- `responsable_col` vient de `ufsc_club_col('responsable_id')` ou vaut `responsable_id`.
- Si le compte WordPress connecté n'est pas dans `clubs.responsable_id`, le front affiche « Club non trouvé » ou récupère un mauvais club.

Point important : cette liaison utilise `UFSC_SQL::get_settings()` pour la table clubs, alors que la requête front des licences utilise `ufsc_get_licences_table()` donc `ufsc_gestion_settings`. Cela peut créer une liaison club depuis une table et une lecture licences depuis une autre famille de tables.

## Confusion possible avec UFSC Licences Compétitions

- Le dépôt mentionne explicitement un plugin séparé **UFSC Licences Compétitions** dans `README.md`.
- `UFSC_Simplified_Admin` contient des slugs et capacités pour des pages compétitions, mais les pages de licences gestion (`ufsc_lc_licences`, `ufsc-gestion-licences`, etc.) sont routées vers `UFSC_SQL_Admin::render_licences()` dans ce dépôt.
- Les colonnes `competition` présentes dans les licences UFSC Gestion semblent décrire le type de pratique « Loisir / Compétition », pas une table du plugin compétition.

Conclusion : la cause principale ne semble pas être une lecture directe des tables du plugin compétition. La confusion probable vient plutôt des slugs historiques `ufsc_lc*`, des alias admin et de la coexistence des capacités/pages compétition dans l'admin simplifié.

## Cause probable des licences manquantes en front

Cause la plus probable : incohérence de source de données entre admin et front.

- L'admin principal lit les licences via `UFSC_SQL::get_settings()` → option `ufsc_sql_settings` → table par défaut `licences`.
- Le front lit les licences via `ufsc_get_licences_table()` → option `ufsc_gestion_settings` → table par défaut `wp_ufsc_licences`.
- Le `club_id` du compte connecté est résolu depuis `UFSC_SQL::get_settings()` côté mapping utilisateur, puis les licences sont lues depuis `ufsc_get_licences_table()`. Si ces deux familles de réglages pointent vers des tables différentes, le front peut chercher `club_id = X` dans une autre table de licences que celle affichée en admin.

Causes secondaires possibles :

- `deleted_at` renseigné sur des licences valides : masquées en front.
- `responsable_id` incorrect ou absent sur le club du compte connecté.
- `club_id` de la licence incorrect ou désynchronisé.
- Filtre URL `ufsc_status`, `ufsc_search`, `season` ou pagination.
- Statuts historiques non couverts par la normalisation si un filtre de statut est actif.

## Fichiers à corriger ensuite, sans les modifier maintenant

1. `inc/common/tables.php`
   - Harmoniser la source de vérité des tables avec `UFSC_SQL::get_settings()` ou créer une compatibilité explicite entre `ufsc_gestion_settings` et `ufsc_sql_settings`.
2. `includes/core/class-user-club-mapping.php`
   - Vérifier que le mapping utilisateur → club utilise la même source de tables que les requêtes front licences.
3. `includes/frontend/class-frontend-shortcodes.php`
   - Harmoniser `get_club_licences()` et `get_club_licences_count()` avec la source de vérité retenue ; rendre les filtres visibles/diagnosticables si besoin.
4. `includes/admin/class-sql-admin.php`
   - Conserver un seul slug canonique de licences admin ou rediriger clairement les alias historiques.
5. `includes/admin/class-ufsc-simplified-admin.php`
   - Réduire ou documenter les alias historiques qui peuvent entretenir la confusion avec les pages licences/compétitions.
6. `inc/admin/menu.php` et `inc/admin/class-ufsc-gestion-licences-list-table.php`
   - Décider si ces anciens fichiers doivent rester uniquement comme compatibilité morte, être supprimés, ou rediriger vers le rendu unifié.

## Plan de correction en 3 petites étapes

1. **Unifier la source des tables**
   - Choisir une source canonique (`UFSC_SQL::get_settings()` ou `ufsc_gestion_settings`) et faire pointer admin, front et mapping club vers les mêmes `clubs_table` / `licences_table`.
2. **Stabiliser le routage admin licences**
   - Garder un slug canonique (`ufsc_lc_licences` ou autre), puis rediriger les anciens slugs vers ce slug au lieu de maintenir plusieurs rendus possibles.
3. **Ajouter un diagnostic non destructif front/admin**
   - Afficher ou logger temporairement table utilisée, club_id résolu, nombre total avant/après filtres, et filtres actifs pour confirmer la correction.

## Risques de régression

- Changer la source des tables peut inverser le problème si une installation réelle utilise encore `ufsc_sql_settings` sans `ufsc_gestion_settings`, ou l'inverse.
- Les alias admin peuvent être utilisés dans des favoris, emails ou liens WooCommerce ; une suppression brutale casserait ces liens.
- Le filtre `deleted_at` est une protection utile contre l'affichage de licences en corbeille ; le retirer sans règle claire pourrait réafficher des licences annulées.
- Modifier le mapping `responsable_id` peut impacter le tableau de bord club, les exports, imports et actions de paiement.
- Les slugs `ufsc_lc*` peuvent être perçus comme liés au plugin compétition ; une correction de nommage doit préserver les droits/capacités existants.

## Tests à faire

1. Vérifier les options sans modifier les données :
   - Lire `ufsc_sql_settings.table_clubs` / `ufsc_sql_settings.table_licences`.
   - Lire `ufsc_gestion_settings.clubs_table` / `ufsc_gestion_settings.licences_table`.
   - Confirmer si les quatre valeurs pointent vers les mêmes tables physiques.
2. Pour un compte club concerné :
   - Résoudre son `user_id`.
   - Vérifier le club trouvé via `responsable_id`.
   - Vérifier que ce `club_id` existe dans la table licences lue par le front.
3. Comparer les comptages SQL non destructifs :
   - Nombre de licences admin pour le club dans la table admin.
   - Nombre de licences front pour le même club dans la table front.
   - Nombre de licences front exclues par `deleted_at`.
   - Nombre de licences exclues par statut/saison/recherche si filtres actifs.
4. Vérifier les slugs admin :
   - `admin.php?page=ufsc_lc_licences`.
   - `admin.php?page=ufsc-gestion-licences`.
   - `admin.php?page=ufsc-sql-licences`.
   - Confirmer s'ils rendent tous le même écran ou si l'ancien `WP_List_Table` apparaît.
5. Vérifier qu'aucune page du plugin compétition n'est appelée pour afficher la liste de licences UFSC Gestion.

## Résumé du diagnostic

Les écarts admin/front proviennent très probablement d'une double source de configuration des tables : l'admin principal utilise `ufsc_sql_settings` via `UFSC_SQL::get_settings()`, tandis que le front utilise `ufsc_gestion_settings` via `ufsc_get_licences_table()`. Les licences peuvent aussi être masquées en front par `club_id`, `deleted_at`, statut, saison, recherche ou pagination. Le double tableau admin est probablement lié à la coexistence du rendu unifié `UFSC_SQL_Admin::render_licences()` et de l'ancien rendu `inc/admin/menu.php` / `UFSC_Gestion_Licences_List_Table`, avec plusieurs alias historiques conservés.
