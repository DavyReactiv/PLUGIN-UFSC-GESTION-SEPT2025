# UFSC Frontend Layer - Documentation

## Vue d'ensemble

Cette couche frontend complète les fonctionnalités existantes du plugin UFSC avec un tableau de bord club moderne, des API REST sécurisées, et des outils d'import/export avancés.

## Nouvelles Fonctionnalités

### 1. Shortcodes Frontend

#### `[ufsc_club_dashboard]`
Tableau de bord principal avec navigation par onglets.

**Utilisation:**
```php
[ufsc_club_dashboard]
[ufsc_club_dashboard show_sections="licences,stats,profile"]
```

**Sections incluses:**
- **Mes Licences**: Liste avec pagination, filtres, et export
- **Statistiques**: KPI de saison avec cache
- **Mon Club**: Édition du profil (restreinte après validation)
- **Ajouter une Licence**: Création avec gestion quota

#### Shortcodes Individuels

```php
[ufsc_club_licences]        // Liste des licences
[ufsc_club_stats]           // Statistiques
[ufsc_club_profile]         // Profil du club
[ufsc_add_licence]          // Formulaire d'ajout
```

### 2. Restrictions Post-Validation

Une fois validés par l'admin:
- **Licences**: Non modifiables (lecture seule)
- **Clubs**: Seuls email et téléphone éditables

Seules les licences en statut `brouillon` peuvent être modifiées depuis le tableau de bord.

**Fonctions de contrôle:**
```php
ufsc_is_validated_club($club_id)      // Vérifie si club validé
ufsc_is_validated_licence($licence_id) // Vérifie si licence validée
```

### 3. Upload de Logo Club

- Formats supportés: JPG, PNG, SVG
- Taille max: 2MB (configurable)
- Stockage: Media Library WordPress
- Association: Option `ufsc_club_logo_{club_id}`

### 4. Import/Export Avancé

#### Export CSV/Excel
```php
// Via URL
/?ufsc_export=csv&_wpnonce=...
/?ufsc_export=xlsx&_wpnonce=...

// Via classe
UFSC_Import_Export::export_licences_csv($club_id);
UFSC_Import_Export::export_licences_xlsx($club_id);
```

#### Import CSV
1. **Étape 1**: Upload + prévisualisation + validation
2. **Étape 2**: Insertion des brouillons + commande si quota dépassé

**Format CSV attendu:**
```csv
nom,prenom,email,telephone,date_naissance,sexe,adresse,ville,code_postal
Dupont,Jean,jean.dupont@email.com,0123456789,1990-01-01,M,123 rue Test,Paris,75001
```

### 5. API REST Sécurisée

Base URL: `/wp-json/ufsc/v1/`

#### Endpoints Disponibles

```php
GET    /licences                    // Liste paginée
POST   /licences                    // Création
PUT    /licences/{id}               // Modification (si non validée)
GET    /club                        // Infos club
PUT    /club                        // Modification (limitée si validé)
POST   /club/logo                   // Upload logo
GET    /stats                       // Statistiques avec cache
GET    /export/{csv|xlsx}           // Export direct
POST   /import                      // Prévisualisation import
POST   /import/commit               // Confirmation import
```

**Sécurité:**
- Permission: Utilisateur connecté avec club associé
- Validation: Club/licence appartenance
- Nonces: Vérification côté frontend

### 6. Notifications Email

**Événements déclencheurs:**
- Création de licence
- Validation de licence
- Dépassement de quota
- Création de commande
- Paiement confirmé

**Templates HTML personnalisables** dans `/templates/emails/`

**Hooks de personnalisation:**
```php
add_filter('ufsc_email_recipient', function($email) { return $email; });
add_filter('ufsc_email_subject', function($subject) { return $subject; });
add_filter('ufsc_email_message', function($message) { return $message; });
```

### 7. Journal d'Audit

**Implémentation:** Custom Post Type `ufsc_audit`

**Fonctions d'utilisation:**
```php
ufsc_audit_log('action_name', array(
    'club_id' => 5,
    'licence_id' => 123,
    'details' => 'Information supplémentaire'
));
```

**Administration:** Menu UFSC Gestion → Audit

**Nettoyage automatique:** 365 jours par défaut

### 8. Système de Cache

**Cache des statistiques:**
- Clé: `ufsc_stats_{club_id}_{season}`
- Durée: 1 heure
- Invalidation automatique sur événements

**Invalidation manuelle:**
```php
ufsc_invalidate_stats_cache($club_id, $season);
```

### 9. Commandes WP-CLI

```bash
# Statistiques
wp ufsc stats --club-id=5 --format=json

# Cache
wp ufsc cache purge --club-id=5
wp ufsc cache info

# Audit
wp ufsc audit cleanup --days=365
wp ufsc audit stats
wp ufsc audit list --limit=20

# Clubs
wp ufsc club list --format=table
wp ufsc club info --club-id=5
```

### 10. Accessibilité & Internationalisation

**Accessibilité (a11y):**
- Sémantique HTML correcte
- Navigation clavier
- ARIA labels
- Focus management
- Messages d'erreur lisibles

**Internationalisation (i18n):**
- Text domain: `ufsc-clubs`
- Toutes les chaînes dans `__()`/`esc_html__()`
- Fichiers .pot générables

## Architecture Technique

### Structure des Fichiers

```
includes/
├── api/
│   └── class-rest-api.php          // Endpoints REST
├── cli/
│   └── class-wp-cli-commands.php   // Commandes CLI
├── core/
│   ├── class-audit-logger.php      // Journal d'audit
│   ├── class-email-notifications.php // Emails
│   └── class-import-export.php     // Import/Export
└── frontend/
    └── class-frontend-shortcodes.php // Shortcodes

assets/frontend/
├── css/
│   └── frontend.css                // Styles modernes
└── js/
    └── frontend.js                 // JavaScript interactif
```

### Intégration avec l'Existant

- **Aucune modification** des tables SQL existantes
- **Compatibilité** avec WooCommerce
- **Réutilisation** des systèmes de permissions
- **Extension** du système de régions unifié

### Fonctions Stub à Implémenter

Ces fonctions doivent être adaptées selon votre schéma de base de données:

```php
// Base de données
ufsc_get_user_club_id($user_id)
ufsc_get_club_responsible_user_id($club_id)

// Validation
ufsc_is_validated_club($club_id)
ufsc_is_validated_licence($licence_id)

// Quota
ufsc_get_club_quota_info($club_id)

// Données
ufsc_get_club_licences($club_id, $args)
ufsc_get_club_stats($club_id, $season)
```

## Configuration

### 1. Enqueue des Assets

Les assets sont automatiquement chargés sur les pages contenant les shortcodes UFSC.

### 2. Variables JavaScript

```javascript
// Disponible via ufsc_frontend_vars
{
    ajax_url: "...",
    rest_url: "...", 
    nonce: "...",
    strings: { /* Traductions */ }
}
```

### 3. Hooks d'Action

```php
// Événements d'audit
do_action('ufsc_licence_created', $licence_id, $club_id);
do_action('ufsc_licence_validated', $licence_id, $club_id);
do_action('ufsc_quota_exceeded', $club_id, $context);
```

## Tests

### PHPUnit
```bash
composer require --dev phpunit/phpunit
vendor/bin/phpunit tests/
```

### Tests Inclus
- Tests de structure des classes
- Tests de sécurité (sanitization, nonces)
- Tests d'accessibilité
- Tests de performance

## Installation & Activation

1. **Inclure** les nouveaux fichiers dans le plugin principal
2. **Vérifier** les dépendances (WooCommerce)
3. **Configurer** les réglages WooCommerce
4. **Tester** les shortcodes sur une page
5. **Vérifier** les permissions et la sécurité

## Personnalisation

### Styles CSS
Tous les éléments utilisent des classes CSS préfixées `ufsc-` pour éviter les conflits.

### Templates Email
Créer des fichiers dans `/templates/emails/` pour personnaliser:
- `licence-created.php`
- `licence-validated.php`
- `quota-exceeded.php`
- `order-created.php`
- `order-completed.php`

### Hooks de Filtrage
```php
// Personnaliser les champs de club
add_filter('ufsc_club_editable_fields', function($fields) {
    // Ajouter/retirer des champs éditables après validation
    return $fields;
});

// Personnaliser les exports
add_filter('ufsc_export_data', function($data, $club_id) {
    // Modifier les données avant export
    return $data;
}, 10, 2);
```

## Support & Maintenance

### Logs d'Erreur
- Audit automatique dans CPT
- Logs PHP via `error_log()`
- Intégration avec systèmes de monitoring existants

### Nettoyage Automatique
- Audit logs: 365 jours (configurable)
- Fichiers d'export temporaires: Suppression après téléchargement
- Cache stats: Invalidation intelligente

### Performance
- Cache transients pour statistiques
- Pagination côté serveur
- Chargement conditionnel des assets
- Optimisation des requêtes

## Roadmap

### Fonctionnalités Futures
- [ ] Graphiques interactifs (Chart.js)
- [ ] Export PDF des licences
- [ ] API webhook pour intégrations externes
- [ ] Notifications push navigateur
- [ ] Mode hors-ligne avec synchronisation

### Améliorations Techniques
- [ ] Tests d'intégration complets
- [ ] Documentation développeur étendue
- [ ] Outils de migration de données
- [ ] Monitoring de performance

Cette implémentation fournit une base solide et extensible pour la gestion frontend des clubs UFSC, respectant les meilleures pratiques WordPress et les standards de sécurité.