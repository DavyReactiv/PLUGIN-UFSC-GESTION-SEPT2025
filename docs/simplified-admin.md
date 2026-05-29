# Interface admin simplifiée UFSC

## Principe

UFSC Gestion fournit une interface d’administration simplifiée pour les utilisateurs WordPress non administrateurs qui disposent de droits UFSC opérationnels.

Cette interface est un **confort d’usage** : elle masque les menus WordPress et les menus de plugins qui ne sont pas nécessaires à la mission de l’utilisateur, puis redirige l’utilisateur vers le premier module UFSC auquel il a droit.

## Important : masquage UI ≠ sécurité serveur

Le masquage des menus ne remplace jamais les contrôles serveur.

Les actions sensibles doivent continuer à être protégées par :

- `current_user_can()` ;
- `ufsc_user_can()` ;
- les nonces WordPress ;
- les contrôles régionaux UFSC ;
- les protections `POST`, `AJAX` et `admin_post` existantes.

Aucune capability WordPress classique n’est accordée par ce mécanisme. Les utilisateurs UFSC limités ne reçoivent pas de droits tels que `manage_options`, `edit_posts`, `upload_files`, `edit_pages`, `manage_woocommerce`, `install_plugins`, `activate_plugins`, `edit_theme_options`, `list_users`, `create_users` ou `promote_users`.

## Utilisateurs concernés

L’interface simplifiée s’applique uniquement si toutes les conditions suivantes sont vraies :

1. l’utilisateur est connecté ;
2. l’utilisateur n’a pas `manage_options` ;
3. l’utilisateur n’est pas un véritable administrateur WordPress ;
4. l’utilisateur possède au moins une des capabilities UFSC suivantes :
   - `ufsc_gestion_read` ;
   - `ufsc_gestion_manage` ;
   - `ufsc_licences_read` ;
   - `ufsc_licences_manage` ;
   - `ufsc_competitions_read` ;
   - `ufsc_competitions_manage`.

Les administrateurs WordPress réels, notamment ceux qui disposent de `manage_options`, ne sont jamais placés dans ce mode simplifié et conservent l’administration WordPress complète.

## Option globale

L’option globale est stockée dans :

```text
ufsc_enable_simplified_admin
```

Elle est activée par défaut.

Elle peut être modifiée dans **UFSC Gestion > Droits & accès** par un administrateur WordPress disposant de `manage_options` via la case :

> Activer l’interface admin simplifiée pour les utilisateurs UFSC limités

Si l’option est désactivée, UFSC Gestion ne masque plus les menus et ne force plus les redirections d’interface simplifiée. Les protections serveur restent inchangées.

## Menus conservés

Pour un utilisateur UFSC limité, les seuls menus conservés sont :

- **UFSC Gestion**, si l’utilisateur possède `ufsc_gestion_read` ou `ufsc_gestion_manage` ;
- **UFSC Licences**, si l’utilisateur possède `ufsc_licences_read` ou `ufsc_licences_manage` ;
- **Compétitions**, si l’utilisateur possède `ufsc_competitions_read` ou `ufsc_competitions_manage` ;
- **Profil**, afin de laisser l’utilisateur gérer son compte si nécessaire.

UFSC Gestion ajoute également une petite page **Accueil UFSC** qui rappelle que l’interface est limitée aux outils nécessaires et affiche les modules disponibles selon les droits de l’utilisateur.

## Menus masqués

Tous les menus non explicitement autorisés sont retirés pour les utilisateurs UFSC limités, par exemple :

- Tableau de bord WordPress ;
- Articles ;
- Médias ;
- Pages ;
- Commentaires ;
- Apparence ;
- Extensions ;
- Utilisateurs ;
- Outils ;
- Réglages ;
- WooCommerce ;
- Produits ;
- Paiements ;
- Marketing ;
- Elementor ;
- Astra ;
- Rank Math SEO ;
- Contact Form 7 ;
- Flamingo ;
- Événements ;
- Monetico Paiement ;
- tout autre menu qui n’est pas reconnu comme un module UFSC autorisé.

Le filtrage est volontairement robuste : il inspecte les menus WordPress enregistrés et ne conserve que les slugs/titres UFSC explicitement autorisés par les capabilities de l’utilisateur.

## Redirections et accès directs

Après connexion, ou lors d’une arrivée sur `/wp-admin/`, un utilisateur UFSC limité est redirigé vers le premier écran autorisé selon la priorité suivante :

1. Compétitions ;
2. UFSC Licences ;
3. UFSC Gestion / Accueil UFSC ;
4. Profil utilisateur.

Les accès directs aux pages admin non autorisées sont redirigés vers ce même premier écran autorisé. Les endpoints techniques nécessaires ne sont pas bloqués :

- `admin-ajax.php` ;
- `admin-post.php` ;
- `async-upload.php` ;
- `profile.php`.

## Barre d’administration

La barre d’administration est simplifiée pour les utilisateurs UFSC limités. Les raccourcis inutiles comme “Nouveau”, “Commentaires”, “Personnaliser”, Elementor, Rank Math, WooCommerce ou les mises à jour sont supprimés.

Le compte utilisateur, l’accès au profil et la déconnexion sont conservés.

## Tests à effectuer

### Administrateur WordPress

- Vérifier que l’administrateur voit tous les menus WordPress habituels.
- Vérifier qu’il voit UFSC Gestion, UFSC Licences, Compétitions et Droits & accès.
- Vérifier qu’il n’est pas redirigé vers l’interface simplifiée.

### Utilisateur UFSC licences lecture seule

Capabilities :

- `ufsc_licences_read`

Résultat attendu :

- l’utilisateur voit uniquement UFSC Licences et Profil ;
- il ne voit pas les menus WordPress ou plugins non nécessaires ;
- il ne peut pas modifier les licences ;
- l’accès direct à `/wp-admin/plugins.php` est redirigé.

### Utilisateur UFSC compétition manager

Capabilities :

- `ufsc_competitions_read`
- `ufsc_competitions_manage`

Résultat attendu :

- l’utilisateur voit uniquement Compétitions et Profil ;
- il peut modifier les compétitions selon les protections serveur existantes ;
- il ne voit aucun menu non nécessaire.

### Utilisateur UFSC combiné

Capabilities :

- `ufsc_gestion_read`
- `ufsc_licences_read`
- `ufsc_competitions_read`
- `ufsc_competitions_manage`

Résultat attendu :

- l’utilisateur voit uniquement UFSC Gestion, UFSC Licences, Compétitions et Profil ;
- il ne voit pas Droits & accès ;
- il ne voit pas les menus WordPress ou plugins non nécessaires.

### Accès direct

Tester les URL suivantes pour un utilisateur UFSC limité et pour un administrateur WordPress :

- `/wp-admin/edit.php` ;
- `/wp-admin/upload.php` ;
- `/wp-admin/plugins.php` ;
- `/wp-admin/users.php` ;
- `/wp-admin/options-general.php` ;
- `/wp-admin/admin.php?page=woocommerce` ;
- `/wp-admin/admin.php?page=rank-math`.

Résultat attendu : l’utilisateur UFSC limité est redirigé vers son espace UFSC autorisé, tandis que l’administrateur WordPress reste autorisé.

## Diagnostic administrateur

La page **UFSC Gestion > Droits & accès** affiche aussi un diagnostic réservé aux administrateurs WordPress pour chaque utilisateur UFSC :

- rôle(s) WordPress ;
- présence de la capability native `read` ;
- résultat de la détection “utilisateur UFSC limité” ;
- première URL admin calculée pour la redirection ;
- régions autorisées.

Ce diagnostic permet notamment de vérifier qu’un compte comme `HichamUFSC`, avec le rôle `ufsc_admin_limited`, possède bien `read` et est envoyé vers une URL `admin_url()` de l’administration simplifiée plutôt que vers le site public.

## Slugs de redirection pris en charge

Les redirections utilisent toujours `admin_url()` et testent des listes de slugs possibles afin de rester compatibles avec les variations entre plugins UFSC.

### Compétitions

- `admin.php?page=ufsc-competitions`
- `admin.php?page=ufsc_competitions`
- `admin.php?page=ufsc-competition`
- `admin.php?page=ufsc_competition`
- `admin.php?page=ufsc-competition-dashboard`
- `admin.php?page=ufsc_competition_dashboard`

### UFSC Licences

- `admin.php?page=ufsc-licences`
- `admin.php?page=ufsc_licences`
- `admin.php?page=ufsc-licence`
- `admin.php?page=ufsc_licence`
- `admin.php?page=ufsc-licences-dashboard`

### UFSC Gestion

- `admin.php?page=ufsc-gestion`
- `admin.php?page=ufsc_gestion`
- `admin.php?page=ufsc-clubs`
- `admin.php?page=ufsc_clubs`
- `admin.php?page=ufsc-dashboard`
