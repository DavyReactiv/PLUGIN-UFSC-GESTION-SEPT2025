# UFSC Clubs & Licences (SQL)

Plugin WordPress pour la gestion des clubs et licences UFSC via tables SQL dédiées.

## Description

Ce plugin offre une solution complète pour la gestion des clubs et licences UFSC avec :

- **Interface d'administration** : CRUD complet pour les clubs et licences
- **Formulaires front-end** : Soumission publique de demandes de licence
- **Profils clubs** : Interface pour les responsables de clubs
- **Exports CSV** : Export des données clubs et licences
- **Shortcodes** : Intégration facile dans les pages/articles

## Fonctionnalités

### Administration
- Gestion complète des clubs (création, modification, suppression)
- Gestion des licences avec upload de certificats médicaux
- Interface de réglages pour configuration des tables
- Exports CSV des données
- Tableau de bord avec KPIs

### Front-end
- Formulaire de demande de licence : `[ufsc_sql_licence_form]`
- Profil club pour les responsables : `[ufsc_sql_my_club]`
- Gestion des uploads de fichiers (certificats médicaux)

### Technique
- Tables SQL dédiées avec préfixe WordPress
- Migration automatique de schéma
- Support InnoDB et charset du site
- Sécurisation par nonces et sanitisation
- Internationalisation (i18n) prête

## Installation

1. Télécharger le plugin
2. Le déposer dans `/wp-content/plugins/`
3. Activer le plugin depuis l'administration WordPress
4. Les tables seront créées automatiquement lors de l'activation

## Configuration

### Tables créées
- `{prefix}ufsc_clubs` : Données des clubs
- `{prefix}ufsc_licences` : Données des licences

### Réglages
Accéder aux réglages via le menu "UFSC → Réglages (SQL)" pour configurer :
- Noms des tables
- Champs personnalisés

## Shortcodes

### Formulaire de licence
```
[ufsc_sql_licence_form]
```
Affiche un formulaire de demande de licence pour les visiteurs.

### Profil club
```
[ufsc_sql_my_club]
```
Affiche le profil du club pour les utilisateurs connectés (responsables).

## Statuts

### Clubs et licences
- **En attente** : Nouveau club/licence en attente de validation
- **Validé** : Club/licence approuvé et actif
- **À régler** : Problème à résoudre
- **Désactivé** : Club/licence désactivé

## Développement

### Structure des fichiers
```
ufsc-clubs-licences-sql/
├── includes/
│   ├── class-sql.php          # Configuration et migration
│   ├── class-sql-admin.php    # Interface admin
│   ├── class-sql-public.php   # Interface front-end
│   ├── class-sql-shortcodes.php # Shortcodes
│   ├── class-admin-menu.php   # Menu admin
│   └── class-utils.php        # Utilitaires
├── assets/
│   ├── css/
│   └── js/
├── languages/                 # Fichiers de traduction
├── uninstall.php             # Nettoyage à la désinstallation
└── ufsc-clubs-licences-sql.php # Fichier principal
```

### Standards de code
- WordPress Coding Standards
- PHP 7.4+ requis
- Code documenté et testé

## Désinstallation

La désinstallation supprime :
- Les options du plugin
- La version de schéma stockée

Pour supprimer également les tables de données, définir la constante :
```php
define('UFSC_FORCE_DELETE_TABLES', true);
```

## Support

Pour le support technique, contacter l'équipe de développement UFSC.

## Version

**Version actuelle** : 1.5.3f

## Licence

Plugin développé spécifiquement pour l'UFSC.