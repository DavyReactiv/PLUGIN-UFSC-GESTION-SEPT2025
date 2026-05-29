# UFSC Gestion — rôles, droits et accès régionaux

UFSC Gestion fournit un module centralisé de permissions pour l'écosystème UFSC. Les autres plugins UFSC peuvent consommer ces droits via les fonctions globales `ufsc_*` chargées par le plugin.

## Rôles créés

Les rôles sont créés de manière idempotente à l'activation et vérifiés en administration sans supprimer de droits ajoutés manuellement.

| Rôle | Libellé | Droits par défaut |
| --- | --- | --- |
| `ufsc_region_viewer` | Lecture régionale | `read`, `ufsc_gestion_read`, `ufsc_licences_read` |
| `ufsc_region_manager` | Gestion régionale | `read`, `ufsc_gestion_read`, `ufsc_gestion_manage`, `ufsc_licences_read`, `ufsc_licences_manage` |
| `ufsc_competition_manager` | Gestion compétitions | `read`, `ufsc_gestion_read`, `ufsc_licences_read`, `ufsc_competitions_read`, `ufsc_competitions_manage` |
| `ufsc_admin_limited` | Admin UFSC limité | `read`, `ufsc_gestion_read`, `ufsc_licences_read`, `ufsc_competitions_read`, `ufsc_competitions_manage` |

Le rôle WordPress `administrator` reçoit toutes les capabilities UFSC sans retrait de ses droits WordPress existants.

## Capabilities UFSC

- `ufsc_gestion_read` : accès de consultation à UFSC Gestion.
- `ufsc_gestion_manage` : actions de modification UFSC Gestion.
- `ufsc_licences_read` : lecture des licences UFSC.
- `ufsc_licences_manage` : modification des licences UFSC.
- `ufsc_competitions_read` : lecture des compétitions.
- `ufsc_competitions_manage` : gestion des compétitions.
- `ufsc_settings_manage` : réglages sensibles UFSC. Cette capability est conservée pour les pages de réglages et les évolutions futures, mais ne donne pas accès au menu **Droits & accès**.
- `ufsc_regions_manage` : gestion des accès régionaux pour évolutions futures. Cette capability ne donne pas accès au menu **Droits & accès** dans la version actuelle.
- `ufsc_all_regions_access` : accès national à toutes les régions.

## Lecture vs gestion

Un utilisateur avec seulement `ufsc_gestion_read` peut voir les pages de consultation UFSC Gestion. Les actions de création, modification, suppression, import, validation et réglages doivent vérifier une capability de gestion côté serveur, principalement `ufsc_gestion_manage` ou `ufsc_settings_manage` selon la sensibilité de la page.

Masquer un bouton n'est pas une protection suffisante : les handlers `POST`, AJAX et `admin_post` doivent également vérifier les droits.

## Régions

La liste des régions est exposée par :

```php
$regions = ufsc_get_regions();
```

Elle contient les régions françaises métropolitaines et ultramarines utilisées par l'UFSC et reste filtrable :

```php
add_filter( 'ufsc_regions_list', function( array $regions ) {
    return $regions;
} );
```

Les régions autorisées d'un utilisateur sont stockées dans la meta `_ufsc_allowed_regions`. L'accès national est accordé si l'utilisateur est administrateur WordPress, possède `ufsc_all_regions_access` ou a la meta `_ufsc_all_regions_access` à `1`.

## Menu “Droits & accès”

Le sous-menu **UFSC Gestion > Droits & accès** (`ufsc-permissions`) est réservé exclusivement aux vrais administrateurs WordPress disposant de `manage_options`. Les capabilities UFSC `ufsc_settings_manage` et `ufsc_regions_manage` restent disponibles pour évolution future, mais ne permettent pas d'ouvrir cette page ni de gérer les droits d'autres utilisateurs.

Ce menu permet aux administrateurs WordPress de :

- voir les utilisateurs possédant un rôle UFSC ou une capability UFSC ;
- attribuer ou retirer les capabilities UFSC autorisées ;
- choisir les régions autorisées ;
- activer l'accès toutes régions ;
- sauvegarder avec nonce et whitelist stricte.

Protections importantes :

- la visibilité du menu et l'accès serveur vérifient `current_user_can( 'manage_options' )` ;
- aucun rôle UFSC limité ne peut gérer les droits d'autres utilisateurs ;
- un non-administrateur ne peut pas modifier un compte administrateur WordPress ;
- un non-administrateur ne peut pas s'ajouter lui-même `ufsc_settings_manage`, `ufsc_regions_manage` ou `ufsc_all_regions_access` ;
- aucune capability arbitraire envoyée depuis le navigateur n'est acceptée.

## API pour autres plugins UFSC

Vérifier une capability :

```php
if ( function_exists( 'ufsc_user_can' ) && ufsc_user_can( 'ufsc_competitions_manage' ) ) {
    // Autoriser l'action compétition.
}
```

Vérifier l'accès régional :

```php
if ( function_exists( 'ufsc_user_can_access_region' ) && ufsc_user_can_access_region( 'Bretagne' ) ) {
    // Afficher ou modifier la donnée régionale.
}
```

Obtenir les régions autorisées de l'utilisateur connecté :

```php
$regions = ufsc_current_user_allowed_regions();
```

Préparer prudemment un filtrage par régions :

```php
$args = ufsc_filter_query_by_allowed_regions( $args, 'region' );
```

Pour `WP_Query`, le helper ajoute une `meta_query`. Pour les requêtes SQL custom, le helper fournit `ufsc_allowed_regions` et `ufsc_region_field` afin que l'appelant construise explicitement le `WHERE` avec `$wpdb->prepare()`.
