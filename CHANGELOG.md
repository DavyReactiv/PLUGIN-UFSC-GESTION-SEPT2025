# UFSC Clubs & Licences Plugin - Changelog

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
