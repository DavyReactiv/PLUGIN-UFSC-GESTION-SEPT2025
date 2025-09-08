# UFSC Clubs & Licences Plugin - Changelog

## Version 1.5.8 - Tests et navigation (Octobre 2025)

- Ajout de tests d'intégration pour la redirection "Ajouter un club"
- Couverture de la transition de licences brouillon vers validées et mise à jour du quota
- Test de sortie du shortcode [ufsc_add_licence] avec ou sans ID de produit configuré
- Validation du fallback de navigation des onglets du tableau de bord via ?tab=

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
