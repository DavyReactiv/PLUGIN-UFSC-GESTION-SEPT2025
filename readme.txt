=== UFSC – Clubs & Licences (SQL) ===
Contributors: Davy – Studio REACTIV (pour l'UFSC)
Stable tag: 1.5.3ff
Requires at least: 6.0
Tested up to: 6.6
License: GPLv2 or later

Plugin SQL-first pour l'UFSC : mapping complet vers vos tables `clubs` et `licences`, formulaires complets (admin & front), badges de statut, exports CSV, mini-dashboard, intégration WooCommerce.

== Description ==

Plugin WordPress complet pour la gestion des clubs et licences UFSC - Saison 2025-2026.

**Fonctionnalités principales :**
* Gestion complète des clubs et licences avec mapping SQL
* Intégration WooCommerce pour quotas et commandes
* Import/Export CSV avec gestion des encodages
* Système de statuts avancé (en_attente, valide, a_regler, desactive)
* Notifications email automatiques
* Tableau de bord avec statistiques temps réel
* Shortcodes front-end riches
* Audit et traçabilité complète
* Cache optimisé pour les performances
* Multilingue (i18n) et accessible (a11y)

== Installation ==
1. Téléversez le ZIP dans Extensions > Ajouter > Téléverser
2. Activez le plugin
3. Allez dans **UFSC – Gestion > Paramètres** et configurez :
   - Tables SQL existantes (clubs et licences)
   - IDs produits WooCommerce
   - Quotas par région
   - Saison courante (2025-2026)

== Configuration ==

**Tables SQL requises :**
* Table clubs (par défaut : wp_ufsc_clubs)
* Table licences (par défaut : wp_ufsc_licences)
* Colonne 'status' ajoutée automatiquement à la table licences

**WooCommerce :**
Configurez les IDs des produits pour licences dans Paramètres > WooCommerce.

**Quotas :**
Définissez les quotas par région dans la configuration.

== Shortcodes ==
- [ufsc_sql_my_club] : tableau de bord du club lié au responsable connecté
- [ufsc_sql_licence_form] : formulaire complet de demande de licence (statut par défaut *en_attente*)
- [ufsc_clubs_region region="REGION" limite="10"] : liste des clubs par région

== Import/Export CSV ==

**Format supporté :**
* Séparateur : point-virgule (;)
* Encodages : UTF-8, ISO-8859-1, Windows-1252
* Région automatiquement reprise depuis le club associé

**Champs clubs :** nom;region;adresse;code_postal;ville;email;telephone
**Champs licences :** nom;prenom;email;telephone;club_id;statut;date_naissance

== Notifications ==

Notifications email automatiques :
* Nouvelle demande de licence
* Changement de statut
* Quota atteint par région
* Validation administrateur

== Statuts ==

Workflow complet de validation :
* **en_attente** : Demande soumise, en attente de validation
* **valide** : Licence validée et active  
* **a_regler** : Problème administratif à résoudre
* **desactive** : Licence suspendue ou expirée

Restrictions d'édition après validation admin pour maintenir l'intégrité.

== Cache et Performance ==

* Cache transient des statistiques (1 heure)
* Requêtes SQL optimisées avec index
* Lazy loading des assets front-end

== Audit ==

Traçabilité complète via Custom Post Type :
* Toutes les modifications importantes
* Logs sécurisés avec IP et utilisateur
* Interface d'administration dédiée

== Accessibilité ==

* Navigation clavier complète
* Labels ARIA appropriés  
* Contraste couleurs conforme WCAG 2.1
* Structure sémantique HTML5

== Migration Structurelle ==

Cette version inclut un aplatissement de la structure :
* **Avant** : Fichiers dans UFSC_Clubs_Licences_v1_5_3f_SQL/
* **Après** : Fichiers directement à la racine du plugin

Colonne SQL 'status' ajoutée automatiquement lors de l'activation.

== Notes ==
- Les booléens sont stockés **1 = oui / 0 = non**.
- Les statuts utilisés: *en_attente*, *valide*, *a_regler*, *desactive*.
