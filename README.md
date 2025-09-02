# UFSC – Clubs & Licences (SQL) Plugin

Plugin WordPress complet pour la gestion des clubs et licences sportives de l'Union Française du Sport Cycliste (UFSC). Ce plugin offre une solution complète pour la gestion des clubs, des licences, des paiements et des documents officiels.

## 🎯 Présentation

Le plugin UFSC Clubs & Licences est une solution complète et moderne pour la gestion fédérale des clubs sportifs et de leurs licenciés. Il s'interface avec les tables SQL existantes de l'UFSC tout en proposant une expérience utilisateur moderne et intuitive.

### Objectifs et périmètre

- **Gestion complète des clubs** : création, validation, suivi des documents
- **Gestion des licences** : inscription, validation, paiements, conformité
- **Interface moderne** : tableaux de bord intuitifs pour tous les niveaux d'utilisateurs
- **Sécurité renforcée** : permissions granulaires et accès contrôlé
- **Conformité réglementaire** : respect des règles fédérales et saisons sportives

## ✨ Fonctionnalités

### 🏢 Gestion des Clubs

#### Fonctionnalités actuelles
- **Création et édition** de clubs avec formulaires complets
- **Validation administrative** par les administrateurs fédéraux/régionaux
- **Gestion des documents** : statuts, récépissé, Journal Officiel, PV AG, CER
- **Suivi des quotas** de licences autorisées
- **Badges de statut** harmonisés avec design tokens

#### Fonctionnalités avancées
- **Attestations PDF** : génération et téléchargement sécurisés
- **Tableau de bord club** avec KPI en temps réel
- **Actions rapides** : nouvelle licence, import/export, configuration
- **Notifications** automatiques pour expirations et documents manquants

### 🎫 Gestion des Licences

#### Fonctionnalités actuelles
- **Inscription complète** avec validation des données
- **Gestion des paiements** intégrée WooCommerce
- **Statuts normalisés** : en attente, validée, à régler, désactivée
- **Unicité par personne/saison** et détection de doublons
- **Certificats médicaux** avec upload sécurisé

#### Fonctionnalités avancées
- **Workflow de validation** avec restrictions post-validation
- **Import/Export CSV** avec prévisualisation et validation
- **Notifications email** automatiques à chaque étape
- **Journal d'audit** complet des modifications

### 💰 Gestion des Paiements

- **Intégration WooCommerce** native
- **Commandes automatiques** pour quotas dépassés
- **Suivi des états** : à payer, payé, litige, remboursé
- **Rapprochement bancaire** et export comptable
- **Relances automatiques** configurables

### 📊 Tableaux de Bord et Statistiques

#### Dashboard Administrateur
- **KPI en temps réel** : clubs total/actifs, licences total/actives
- **Actions rapides** : création, gestion, exports
- **Vue d'ensemble** des performances par région

#### Dashboard Club
- **Informations club** : nom, région, affiliation, statut
- **KPI détaillés** : licences, paiements, quota, documents
- **Graphiques interactifs** : répartition sexe/âge, évolution paiements
- **Actions intégrées** : gestion licences, import/export, configuration
- **Notifications contextuelles** et journal d'activité

### 📋 Rôles et Permissions

#### Niveaux d'accès
- **Admin Fédéral** (`ufsc_admin_federal`) : accès complet, toutes régions
- **Admin Régional** (`ufsc_admin_regional`) : accès limité à sa région
- **Admin Club** (`ufsc_admin_club`) : accès limité à son club

#### Mappage des capacités
- **manage_options** : Super administrateurs
- **ufsc_admin_federal** : Gestion nationale complète
- **ufsc_admin_regional** : Validation clubs/licences de sa région
- **ufsc_admin_club** : Gestion de son club uniquement

### 📄 Documents et Attestations

#### Types de documents
- **Attestations de licence** : générées après validation
- **Attestations d'affiliation** : fournies par l'administration
- **Certificats médicaux** : uploadés par les clubs

#### Gestion sécurisée
- **Upload par niveau** : général, régional, ou club spécifique
- **Téléchargement sécurisé** avec nonce et expiration
- **Assignation par saison** et archivage automatique

## 🔧 Installation

### Prérequis
- WordPress 5.8+
- PHP 8.0+
- WooCommerce 6.0+ (recommandé pour les paiements)
- Tables SQL existantes UFSC

### Installation standard
1. **Télécharger** le plugin depuis le repository
2. **Uploader** dans `/wp-content/plugins/`
3. **Activer** via l'interface d'administration
4. **Configurer** les paramètres de base

### Configuration initiale
1. **Aller à** UFSC → Réglages
2. **Configurer** les tables SQL existantes
3. **Définir** la saison courante (2025-2026)
4. **Assigner** les rôles aux utilisateurs
5. **Tester** la connectivité WooCommerce

### Mise à jour
- **Sauvegarde** recommandée avant mise à jour
- **Migration automatique** des données
- **Vérification** de compatibilité WooCommerce

## 🔐 Rôles et Permissions

### Capacités personnalisées

```php
// Capacités fédérales
ufsc_admin_federal
ufsc_manage_all_clubs
ufsc_manage_all_licences
ufsc_manage_attestations

// Capacités régionales  
ufsc_admin_regional
ufsc_manage_region_clubs
ufsc_validate_licences

// Capacités club
ufsc_admin_club
ufsc_manage_own_club
ufsc_create_licences
```

### Attribution des rôles

Les rôles sont attribués via `user_meta` pour plus de flexibilité :

```php
// Attribution région/club à un utilisateur
update_user_meta( $user_id, 'ufsc_region', 'Île-de-France' );
update_user_meta( $user_id, 'ufsc_club_id', 123 );
```

## 📊 Statuts et Badges

### Statuts de clubs normalisés

| Statut Base | Label | Badge | Description |
|-------------|-------|--------|-------------|
| `valide` | Actif | `success` | Club validé et opérationnel |
| `en_attente` | En attente | `info` | En cours de validation |
| `a_regler` | À régler | `warning` | Documents/paiements manquants |
| `desactive` | Désactivé | `danger` | Club suspendu ou archivé |

### Statuts de licences normalisés

| Statut Base | Label | Badge | Description |
|-------------|-------|--------|-------------|
| `valide` | Validée | `success` | Licence active et complète |
| `en_attente` | En attente | `info` | Validation en cours |
| `a_regler` | À régler | `warning` | Paiement ou documents requis |
| `desactive` | Désactivée | `danger` | Licence expirée ou annulée |

### Classes CSS communes

```css
.u-badge--success  /* Vert : validé/actif */
.u-badge--warning  /* Orange : attention/action requise */
.u-badge--info     /* Bleu : information/en cours */
.u-badge--danger   /* Rouge : erreur/problème */
.u-badge--neutral  /* Gris : neutre/inactif */
```

## 🚀 Shortcodes et Endpoints

### Shortcodes Frontend

#### Dashboard Club
```php
[ufsc_club_dashboard]
// Affiche le tableau de bord complet du club

[ufsc_club_dashboard show_sections="header,kpi,actions"]
// Affiche seulement certaines sections
```

#### Composants individuels
```php
[ufsc_club_profile]      // Profil du club
[ufsc_club_licences]     // Liste des licences
[ufsc_club_stats]        // Statistiques visuelles
[ufsc_add_licence]       // Formulaire nouvelle licence
```

### Endpoints REST API

Base URL : `/wp-json/ufsc/v1/`

#### Endpoints Club
```php
GET  /club/{id}/stats          // Statistiques club
POST /club/{id}/licences       // Créer licence
GET  /club/{id}/licences       // Lister licences
PUT  /licence/{id}             // Modifier licence
```

#### Sécurité des endpoints
- **Nonce validation** pour toutes les requêtes AJAX
- **Permission checks** basés sur les capacités utilisateur
- **Rate limiting** et cache pour les performances
- **Scope restriction** par club/région selon les droits

## 💾 Modèle de Données

### Tables principales

#### Clubs (`ufsc_clubs`)
```sql
- id (PK)
- nom, region, adresse, email, telephone
- num_affiliation, quota_licences
- statut, date_creation
- responsable_id (link to WP user)
- documents (statuts, recepisse, jo, etc.)
```

#### Licences (`ufsc_licences`) 
```sql
- id (PK)
- club_id (FK), prenom, nom, email
- statut, saison, numero_licence
- certificat_url, date_naissance
- payment_status, order_id
```

#### Attestations (`ufsc_attestations`)
```sql
- id (PK)
- type (licence|affiliation)
- target_type (general|region|club)
- target_id, saison, filename
- created_at, created_by
```

### Relations
- **Club → Licences** : 1-N (un club a plusieurs licences)
- **User → Club** : N-1 (un utilisateur gère un club via user_meta)
- **Licence → Order** : 1-1 (intégration WooCommerce)

## 🎨 Design Tokens

### Couleurs principales
```css
--ufsc-primary: #2271b1;      /* Bleu UFSC */
--ufsc-secondary: #135e96;    /* Bleu foncé */
--ufsc-success: #059669;      /* Vert validation */
--ufsc-warning: #f59e0b;      /* Orange attention */
--ufsc-danger: #ef4444;       /* Rouge erreur */
--ufsc-info: #3b82f6;         /* Bleu information */
--ufsc-neutral: #6b7280;      /* Gris neutre */
```

### Espacements
```css
--ufsc-spacing-xs: 8px;
--ufsc-spacing-sm: 16px;
--ufsc-spacing-md: 24px;
--ufsc-spacing-lg: 32px;
--ufsc-spacing-xl: 48px;
```

### Breakpoints responsive
```css
--mobile: 480px;
--tablet: 768px;
--desktop: 1024px;
--wide: 1200px;
```

## 📋 Roadmap Immédiate

### Issues prioritaires

#### 🔧 Bugs critiques
- [ ] **Correction édition** : Investigation complète du bug d'enregistrement
- [ ] **Validation données** : Renforcement sanitisation entrées utilisateur
- [ ] **Performance** : Optimisation requêtes sur gros volumes

#### 📈 Fonctionnalités en cours
- [ ] **REST API endpoints** : Implémentation complète des endpoints dashboard
- [ ] **Import CSV avancé** : Mapping intelligent et validation poussée
- [ ] **Notifications email** : Planificateur automatique et templates
- [ ] **Rapprochement paiements** : Intégration bancaire et export comptable

#### 🎯 Améliorations UX
- [ ] **Navigation améliorée** : Breadcrumbs et filtres persistants
- [ ] **Recherche globale** : Moteur de recherche cross-entités
- [ ] **Mobile app** : PWA pour accès mobile optimisé
- [ ] **Accessibilité** : Audit complet WCAG 2.1 AA

#### 🔒 Sécurité et conformité
- [ ] **Chiffrement données** : Protection informations sensibles
- [ ] **Audit trail** : Logs étendus et retention configurable
- [ ] **RGPD compliance** : Outils export/suppression données
- [ ] **Tests automatisés** : Suite complète tests unitaires/intégration

### Critères d'acceptation

#### Fonctionnalités livrées
- ✅ **README complet** avec toutes les sections demandées
- ✅ **Shortcode [ufsc_club_dashboard]** fonctionnel et sécurisé
- ✅ **Interface moderne** avec KPI, graphiques, actions rapides
- ✅ **Boutons "Consulter"** en lecture seule dans les listes admin
- ✅ **Badges harmonisés** avec design tokens cohérents
- ✅ **KPI "Clubs actifs"** corrigé et aligné avec les données réelles
- ✅ **Scaffolding PDF** pour attestations avec upload/téléchargement

#### Qualité technique
- ✅ **Architecture modulaire** avec séparation des responsabilités
- ✅ **Sécurité renforcée** avec nonces, permissions, sanitisation
- ✅ **Performance optimisée** avec cache, transients, lazy loading
- ✅ **Responsive design** mobile-first avec accessibilité
- ✅ **Documentation complète** code et fonctionnalités

## 📸 Screenshots

Captures d'écran de référence disponibles :

- **[Image 1](image1)** : Vue liste des clubs avec actions "Consulter"
- **[Image 2](image2)** : Gestion des licences avec filtres avancés  
- **[Image 3](image3)** : Dashboard administrateur avec KPI corrigés

## 📝 Changelog

### Version 1.5.3ff - 2025
- ✅ **Nouvelle interface** dashboard club avec KPI et graphiques
- ✅ **Système de badges** unifié avec design tokens
- ✅ **Boutons "Consulter"** en lecture seule
- ✅ **Correction KPI** "Clubs actifs" 
- ✅ **Scaffolding PDF** pour attestations
- ✅ **Architecture moderne** avec templates et assets optimisés
- ✅ **Documentation complète** et roadmap détaillée

### Versions précédentes
- **1.5.x** : Intégration frontend layer avec résolution conflits
- **1.4.x** : WooCommerce integration et système paiements
- **1.3.x** : Gestion documents et uploads sécurisés
- **1.2.x** : Formulaires clubs/licences avec validation
- **1.1.x** : Interface admin et list tables
- **1.0.x** : Base SQL et mapping tables existantes

## 📞 Support

### Ressources
- **Documentation** : Ce README et fichiers `/docs/`
- **Exemples** : Fichiers `/examples/` avec cas d'usage
- **Tests** : Suite de tests dans `/tests/`

### Contact
- **Développement** : Davy – Studio REACTIV pour l'UFSC
- **Version** : 1.5.3ff (Frontend Enhanced)
- **Domaine texte** : `ufsc-clubs`

---

*Plugin développé spécifiquement pour l'Union Française du Sport Cycliste avec conformité aux exigences fédérales et réglementaires.*