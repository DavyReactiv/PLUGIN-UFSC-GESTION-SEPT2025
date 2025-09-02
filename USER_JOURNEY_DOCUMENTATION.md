# Parcours Utilisateur UFSC - Documentation Complète

## Vue d'ensemble

Cette documentation décrit le parcours complet d'un utilisateur dans le système UFSC, de la connexion à la déconnexion, en passant par toutes les actions possibles.

## 1. Connexion (Login)

### Shortcode de connexion

```php
[ufsc_login_form]
[ufsc_login_form redirect_club="/mon-club/" redirect_admin="/admin/" title="Connexion Club"]
```

### Fonctionnalités

- **Redirection intelligente** : Les utilisateurs sont redirigés selon leur rôle
  - Administrateurs → Interface d'administration UFSC
  - Responsables de club → Tableau de bord club
  - Utilisateurs standards → Page d'accueil

- **Sécurité** : Nonces WordPress intégrées
- **UX** : Interface responsive avec validation côté client

### Processus de connexion

1. L'utilisateur saisit ses identifiants
2. WordPress valide les données
3. Le système détermine le rôle utilisateur
4. Redirection automatique vers le bon tableau de bord

## 2. Tableau de Bord Club

### Shortcode principal

```php
[ufsc_club_dashboard]
[ufsc_club_dashboard show_sections="licences,stats,profile,add_licence"]
```

### Sections disponibles

#### 2.1 Mes Licences
- **Affichage** : Liste paginée avec filtres
- **Actions** : Consulter, modifier (si non validée), exporter
- **Recherche** : Par nom, prénom, email
- **Filtres** : Par statut (en_attente, validé, à_régler)

#### 2.2 Statistiques
- **KPIs** : Total licences, licences payées, licences validées
- **Quota** : Utilisation du quota de licences
- **Cache** : Mise en cache d'1 heure pour les performances
- **Saison** : Données filtrées par saison

#### 2.3 Mon Club
- **Affichage** : Informations du club avec badges de statut
- **Modification** : Limitée selon la validation du club
- **Badges** : Statut, région avec code couleur harmonisé

#### 2.4 Ajouter une Licence
- **Formulaire** : Création de nouvelle licence
- **Validation** : Côté client et serveur
- **Quota** : Vérification automatique
- **Paiement** : Intégration WooCommerce si quota dépassé

## 3. API REST Sécurisée

### Base URL
```
/wp-json/ufsc/v1/
```

### Endpoints disponibles

#### 3.1 Licences
```http
GET    /licences                    # Liste paginée
POST   /licences                    # Création
PUT    /licences/{id}               # Modification (si non validée)
```

#### 3.2 Club
```http
GET    /club                        # Informations club avec cache
PUT    /club                        # Modification (limitée si validé)
POST   /club/logo                   # Upload logo
```

#### 3.3 Statistiques
```http
GET    /stats                       # Statistiques avec cache
```

#### 3.4 Export/Import
```http
GET    /export/{csv|xlsx}           # Export direct
POST   /import                      # Prévisualisation import
POST   /import/commit               # Confirmation import
```

#### 3.5 Attestations
```http
GET    /attestation/{type}/{nonce}  # Téléchargement sécurisé
```

### Sécurité API

- **Authentification** : Utilisateur connecté requis
- **Permissions** : Club associé obligatoire
- **Validation** : Appartenance club/licence vérifiée
- **Nonces** : Protection CSRF
- **Expiration** : Attestations avec délai (24h par défaut)

## 4. Système d'Attestations

### Génération de liens sécurisés

```php
$nonce = UFSC_REST_API::generate_attestation_nonce(
    'affiliation',  // Type d'attestation
    $club_id,       // ID du club
    $user_id,       // ID utilisateur
    24              // Expiration en heures
);

$url = home_url("/wp-json/ufsc/v1/attestation/affiliation/{$nonce}");
```

### Types d'attestations

- **Affiliation** : Attestation d'affiliation club
- **Licence** : Attestation individuelle de licence
- **Saison** : Récapitulatif de saison

### Sécurité

- **Nonce unique** : Généré avec hash WordPress
- **Expiration** : Liens temporaires (défaut 24h)
- **Traçabilité** : Logs d'audit complets
- **Validation** : Vérification type + expiration

## 5. Badges et Statuts

### Système centralisé

Tous les badges utilisent `UFSC_Badge_Helper` pour une cohérence visuelle.

#### Types de badges

- **Statut** : validé, à_régler, en_attente, désactivé
- **Région** : Badges régionaux colorés
- **Documents** : Complet, partiel, manquant

#### CSS Tokens

```css
.ufsc-badge-success    /* Vert - Validé */
.ufsc-badge-warning    /* Orange - À régler */
.ufsc-badge-info       /* Bleu - En attente */
.ufsc-badge-danger     /* Rouge - Désactivé */
.ufsc-badge-region     /* Bleu UFSC - Régions */
```

## 6. Administration

### Interface de mapping utilisateur-club

**Page** : `/wp-admin/admin.php?page=ufsc-user-club-mapping`

#### Onglets disponibles

1. **Associations** : Créer/gérer associations utilisateur↔club
2. **Régions** : Modifier les régions des clubs
3. **Orphelins** : Clubs sans responsable

#### Fonctionnalités

- **Recherche AJAX** : Utilisateurs et clubs
- **Validation** : Prévention associations multiples
- **Audit** : Traçabilité complète des changements

## 7. Shortcodes d'Authentification

### Statut utilisateur

```php
[ufsc_user_status]
[ufsc_user_status show_avatar="true" show_club="true" show_logout="true"]
```

### Bouton de déconnexion

```php
[ufsc_logout_button]
[ufsc_logout_button text="Se déconnecter" confirm="true"]
```

## 8. Déconnexion (Logout)

### Processus

1. Clic sur le bouton de déconnexion
2. Confirmation utilisateur (optionnelle)
3. Invalidation de session WordPress
4. Redirection vers page d'accueil ou URL spécifiée

### Sécurité

- **Nonce WordPress** : Protection CSRF
- **Session cleanup** : Nettoyage automatique
- **Redirection sécurisée** : URLs validées

## 9. Cache et Performance

### Stratégie de cache

- **Statistiques club** : 1 heure (transients WordPress)
- **Informations club** : 1 heure avec invalidation sur modification
- **Attestations** : Nonces temporaires avec TTL

### Invalidation

```php
// Invalidation automatique
ufsc_invalidate_stats_cache( $club_id, $season );

// Invalidation manuelle
delete_transient( "ufsc_club_info_{$club_id}" );
```

## 10. Sécurité Globale

### Mesures implémentées

- **Nonces** : Tous les formulaires protégés
- **Permissions** : Vérification stricte des droits
- **Validation** : Sanitisation complète des données
- **Audit** : Logs complets des actions
- **HTTPS** : Recommandé pour production

### Bonnes pratiques

- **Mots de passe** : Politique WordPress standard
- **Sessions** : Expiration automatique
- **Données** : Chiffrement recommandé en base
- **Backup** : Sauvegarde régulière

## 11. Intégration WooCommerce

### Processus de paiement

1. Dépassement de quota détecté
2. Création automatique de commande
3. Redirection vers checkout WooCommerce
4. Traitement du paiement
5. Activation automatique des licences

### Hooks disponibles

```php
// Après paiement validé
add_action( 'woocommerce_order_status_completed', 'my_custom_handler' );

// Traitement des licences
add_filter( 'ufsc_process_paid_licenses', 'my_license_processor' );
```

## 12. Extensibilité

### Hooks WordPress

```php
// Personnaliser les champs club
add_filter( 'ufsc_club_fields', function( $fields ) {
    $fields['custom_field'] = array( 'Mon Champ', 'text' );
    return $fields;
} );

// Ajouter des sections au dashboard
add_action( 'ufsc_club_dashboard_sections', function( $club, $sections ) {
    echo '<div>Ma section personnalisée</div>';
} );

// Modifier les régions
add_filter( 'ufsc_regions_list', function( $regions ) {
    $regions[] = 'MA_REGION_CUSTOM';
    return $regions;
} );
```

### API Extensions

Les endpoints REST peuvent être étendus via la classe `UFSC_REST_API` pour ajouter de nouvelles fonctionnalités.

---

## Support et Maintenance

- **Logs** : Consultables via l'interface admin
- **Debug** : Mode debug WordPress recommandé
- **Monitoring** : Surveillance des performances
- **Updates** : Mises à jour sécuritaires régulières

Cette documentation couvre l'ensemble du parcours utilisateur et fournit les bases pour l'extension et la maintenance du système UFSC.