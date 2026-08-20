# UFSC Clubs & Licences Plugin - Changelog

## Version 082026 - Stabilisation production 2026-2027 (20 août 2026)

### Parcours licences

- Stabilisation complète du renouvellement de licence.
- Stabilisation complète de la création d’une nouvelle licence.
- Finalisation quota-first : les licences incluses ne passent plus inutilement par WooCommerce.
- Gestion canonique des **10 licences incluses par club et par saison**.
- À partir de la 11e licence, nouvelle licence ou renouvellement, passage au vrai panier WooCommerce.
- Conservation de la licence source et des saisons historiques lors d’un renouvellement.
- Protection contre les doubles clics, rechargements et doublons de finalisation.
- Correction des faux doublons d’identité entre saisons différentes.
- Assistant de renouvellement stabilisé : sélection → vérification → finalisation.
- Pagination serveur et filtres conservant saison, recherche, statut et taille de page.

### WooCommerce

- Initialisation fiable des fonctions panier sur les routes `admin-post.php`.
- Persistance du panier renforcée.
- Produit licence canonique utilisé pour les demandes payantes.
- Métadonnées panier corrigées : **« Nouvelle licence »** et **« Renouvellement de licence »**.
- Suppression du recalcul legacy du quota dans le panier afin d’éviter de transformer une licence payante en ligne à 0 €.
- Renouvellement d’affiliation ajouté au panier en quantité 1 avec les métadonnées de saison et de club.

### Affiliations

- Distinction claire entre première affiliation et renouvellement annuel.
- Un club existant conserve sa fiche club et renouvelle uniquement son affiliation annuelle.
- Sécurisation du parcours : connexion → renouvellement → panier → paiement → attente de validation → affiliation active.
- Première affiliation d’un nouveau club sécurisée sans double formulaire HTML.
- Conservation de l’historique des affiliations annuelles.

### Portail Club / UX

- Refonte de la couche CSS finale du Compte Club sans modification du moteur métier.
- Correction des collapses de largeur sous Astra / Elementor.
- Vue d’ensemble, informations du club, suivi affiliation, navigation et cartes de profil rendus responsive.
- Suppression des débordements horizontaux desktop sur les vues principales.
- Amélioration des listes licences, archives, filtres et pagination.

### Données historiques / MySQL

- Compatibilité `dbDelta()` renforcée pour les tables `ufsc_identifier*`.
- Préflight avant contraintes uniques : les identifiants optionnels absents `numero_licence_delegataire` et `num_affiliation` sont normalisés de chaîne vide vers `NULL`.
- Aucune valeur métier non vide n’est modifiée par cette normalisation.
- Compatibilité MySQL strict pour les anciennes comparaisons de dates vides.
- Suppression des erreurs `Incorrect DATETIME value: ''` sur les lectures historiques ciblées.
- Aucune suppression de club, licence ou saison.
- Aucune réécriture massive de l’historique.

### Qualité / recette

- Validation DEV des parcours critiques avant fusion de la PR #547.
- UFSC quality gate vert avant fusion : lint PHP, syntaxe JavaScript, tests runtime, tests P0 licences, PHPUnit, PHPStan et WordPress Coding Standards.
- Absence de fatal PHP et d’épuisement mémoire sur la recette finale communiquée.
- README mis à jour avec les parcours utilisateurs, règles métier et procédure de mise en production.

## Version 082026 - Portail Club et panier P0 (Août 2026)

- Dossiers de licence canoniques et saisonniers pour le président, le secrétaire et le trésorier.
- Confirmation d’honorabilité persistée et contrôlée côté serveur pour les seules fonctions réglementées.
- Chaîne WooCommerce durcie : produit canonique, panier natif, métadonnées nominatives, persistance et anti-doublon.
- Réconciliation des KPI avec la liste canonique par club, saison et statut.
- Portail Club et administration rendus fluides, accessibles et adaptatifs.

## Version 042026 - Correctifs ciblés (Avril 2026)

- Correctif des actions groupées sur listes licences/clubs (multi-sélection, scope, nonce, redirection stable).
- Clarification des libellés du filtre de visibilité sans changement des valeurs techniques.
- Stabilisation du bouton retour sur écrans licence avec fallback déterministe.
- Identification visuelle des licences de bureau (Président / Secrétaire / Trésorier) et alerte non bloquante en cas de rôles manquants.
- Harmonisation de la version canonique du plugin (`UFSC_CL_VERSION` + en-tête plugin).

## Version 1.5.7 - Mise à jour mineure (Septembre 2025)

- Mise à jour du numéro de version du plugin.
- Harmonisation de la constante `UFSC_CL_VERSION`.

## Version 1.5.3ff - Refactoring Majeur (Septembre 2024)

### 🎯 Objectifs
- Réorganiser la structure du plugin pour une meilleure maintenabilité
- Consolider les menus d'administration
- Améliorer l'expérience utilisateur et l'interface
- Ajouter des validations et une meilleure gestion d'erreurs

### ✅ Améliorations Réalisées

#### 📁 **Réorganisation de la Structure**
- **Avant**: Tous les fichiers dans `/includes/`
- **Après**: Structure modulaire organisée
  ```
  includes/
  ├── core/          # Classes utilitaires et SQL
  ├── admin/         # Interface d'administration  
  └── frontend/      # Shortcodes et frontend
  
  assets/
  ├── admin/         # CSS/JS pour l'admin
  └── frontend/      # CSS/JS pour le frontend
  ```

#### 🎛️ **Menu d'Administration Unifié**
- **Avant**: 2 menus séparés confus
  - "UFSC – Tableau de bord" (basique)
  - "UFSC – Données (SQL)" (complet)
- **Après**: Menu unique "UFSC – Gestion" avec:
  - Tableau de bord (dashboard amélioré)
  - Clubs (gestion des clubs)
  - Licences (gestion des licences)
  - Réglages (configuration)

#### 🎨 **Interface Moderne**
- Header avec gradient professionnel
- Cartes KPI avec animations hover
- Section "Actions rapides" pour navigation
- CSS responsive et moderne
- Messages d'erreur/succès stylisés

#### 🔧 **Validations & Sécurité**
- Validation des données côté serveur
- Vérification des formats email
- Validation des dates
- Gestion d'erreurs avec try-catch
- Logs sécurisés pour debug
- Messages utilisateur clairs

#### 🛠️ **Fonctionnalités Techniques**
- Hooks pour extensibilité (`ufsc_club_fields`, `ufsc_licence_fields`)
- JavaScript pour UX améliorée
- Confirmation avant suppressions
- Validation temps réel des formulaires
- Auto-masquage des messages de succès

#### 📊 **Dashboard Amélioré**
- Détection automatique de tables manquantes
- 4 KPI au lieu de 2 (total + actifs)
- Actions rapides accessibles
- Gestion d'erreurs gracieuse

### 🚀 **Nouvelles Fonctionnalités**

#### Pour les Développeurs
```php
// Personnaliser les champs de club
add_filter('ufsc_club_fields', function($fields) {
    $fields['custom_field'] = array('Mon Champ', 'text');
    return $fields;
});

// Personnaliser les régions
add_filter('ufsc_regions_list', function($regions) {
    $regions[] = 'MA_REGION_CUSTOM';
    return $regions;
});
```

#### Pour les Utilisateurs
- Messages d'erreur explicites en français
- Interface plus intuitive et moderne
- Validation temps réel des formulaires
- Navigation simplifiée

### 🐛 **Corrections**
- Consolidation des URLs de menu
- Ajout des champs `page` manquants dans les formulaires
- Harmonisation des chemins d'assets
- Validation des données utilisateur

### 📋 **Migration**
- ✅ Rétrocompatible avec les données existantes
- ✅ Aucune perte de fonctionnalité
- ✅ Migration automatique des assets
- ✅ Désactivation propre de l'ancien menu

### 🔮 **Prochaines Étapes Suggérées**
- Tests d'intégration WordPress
- Documentation utilisateur
- Tests de charge avec grosses bases de données
- Optimisations de requêtes SQL
- Cache pour les KPI du dashboard

---

**Développé par**: Davy – Studio REACTIV pour l'UFSC  
**Date**: Septembre 2024  
**Compatibilité**: WordPress 6.0+
