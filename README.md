# UFSC – Gestion Clubs, Licences & Compétitions

## Introduction

**UFSC – Gestion Clubs, Licences & Compétitions** est un plugin WordPress professionnel développé sur mesure pour l’**UFSC — Union Française des Sports de Combat**.

Il a pour objectif de centraliser, fiabiliser et sécuriser la gestion fédérale des clubs, licences, affiliations, compétitions, documents administratifs, communications et outils associés, dans un environnement WordPress maintenable et adapté aux besoins métier de l’UFSC.

Ce plugin est conçu comme un outil métier personnalisé : il accompagne les équipes administratives, les clubs affiliés et les mainteneurs techniques dans la gestion quotidienne des données fédérales.

## Présentation

Le plugin accompagne la gestion opérationnelle de l’UFSC en regroupant dans WordPress les principaux processus liés aux clubs, aux licences, aux affiliations, aux documents, aux compétitions et aux communications internes.

Il s’appuie sur une approche modulaire afin de limiter les régressions, de préserver les données existantes et de faciliter les évolutions futures. Les fonctionnalités sont pensées pour être utilisées par des administrateurs fédéraux, des responsables de clubs et des développeurs amenés à maintenir ou faire évoluer la solution.

## Objectifs

Le plugin vise à :

- centraliser les données fédérales ;
- simplifier les démarches des clubs ;
- fiabiliser les inscriptions et les suivis administratifs ;
- améliorer le contrôle des licences, affiliations et documents ;
- renforcer la sécurité des accès et des actions sensibles ;
- faciliter les exports, contrôles et diagnostics ;
- moderniser les outils de gestion de l’UFSC ;
- fournir une base technique maintenable pour les évolutions futures.

## Fonctionnalités principales

### Gestion des clubs

- Création et édition des clubs.
- Suivi administratif des structures.
- Gestion des statuts.
- Suivi des coordonnées et informations de contact.
- Gestion des responsables et référents.
- Association et suivi des documents administratifs.

### Gestion des licences

- Création et suivi des licences.
- Rattachement des licenciés à leur club.
- Gestion des informations licenciés.
- Contrôles administratifs.
- Exports de données.
- Suivi des statuts selon les règles du plugin.

### Affiliations

- Suivi des clubs affiliés.
- Gestion de la saison active lorsque l’information est disponible.
- Identification des clubs à jour selon la logique métier du plugin.
- Validation et contrôle administratif.

### Documents administratifs

- Dépôt et suivi des documents.
- Validation administrative.
- Gestion des statuts.
- Consultation des pièces liées aux clubs ou dossiers suivis.

### Compétitions

- Création et gestion d’événements sportifs.
- Inscriptions aux compétitions.
- Contrôle des participants.
- Suivi des catégories et informations associées.
- Gestion des statuts d’inscription.

### Exports, diagnostics et tableaux de bord

- Exports CSV et outils de contrôle.
- Tableaux de bord administratifs.
- Outils de diagnostic pour faciliter le suivi et la maintenance.
- Espaces clubs et interfaces adaptées aux usages front-office lorsque les modules correspondants sont activés.

### Gestion des rôles et permissions

- Utilisation des droits et permissions WordPress.
- Capacités dédiées pour les actions sensibles.
- Contrôle d’accès aux écrans d’administration et aux opérations critiques.

### Communication UFSC

Le module **Communication UFSC** permet à l’administration de préparer, prévisualiser, mettre en file et suivre des campagnes email depuis le back-office WordPress.

Fonctionnalités principales :

- campagnes email ;
- sources multiples de destinataires ;
- clubs affiliés ;
- clubs à jour ;
- licenciés ;
- responsables de ligues ;
- emails saisis manuellement ;
- carnet d’adresses ;
- listes personnalisées ;
- prévisualisation avancée des destinataires ;
- diagnostic destinataires ;
- file d’attente email ;
- envoi progressif par lots ;
- historique des campagnes ;
- suivi des erreurs ;
- relance des échecs ;
- exports CSV ;
- compatibilité avec FluentSMTP / Brevo via `wp_mail()`.

Le module est conçu pour éviter les envois massifs en une seule requête PHP. Les emails sont placés dans une file d’attente et envoyés progressivement afin de limiter les risques de surcharge serveur et de faciliter le suivi des campagnes.

## Sécurité

Le plugin applique les principes de sécurité WordPress pour les opérations sensibles :

- contrôle des droits et permissions WordPress ;
- capability dédiée pour certains modules, notamment la communication ;
- vérification de nonces pour les actions administratives ;
- sanitization des entrées utilisateur ;
- escaping des sorties affichées dans l’interface ;
- requêtes SQL préparées lorsque des paramètres dynamiques sont utilisés ;
- logique de migration additive et non destructive ;
- préservation des données existantes ;
- séparation des modules afin de réduire les risques de régression.

Aucune action sensible ne doit être exposée sans contrôle de permission approprié. Toute évolution doit conserver ces principes.

## Architecture

Le plugin est une solution WordPress personnalisée construite autour d’une structure modulaire.

Principes généraux :

- intégration dans l’écosystème WordPress ;
- fichiers et classes organisés par domaines fonctionnels ;
- tables dédiées lorsque les fonctionnalités nécessitent un stockage spécifique ;
- compatibilité avec les mécanismes standards WordPress ;
- utilisation de `dbDelta()` pour les créations ou évolutions de tables prévues par le plugin ;
- approche maintenable pour les futures évolutions.

Le plugin peut s’appuyer sur des tables métiers existantes et sur des tables dédiées aux modules ajoutés, notamment pour les campagnes email, la file d’attente, les contacts et les listes personnalisées.

## Compatibilité email

Le module Communication UFSC utilise la fonction WordPress `wp_mail()` pour l’envoi des emails.

Cela permet au site WordPress d’utiliser un service SMTP externe configuré par un plugin spécialisé, par exemple **FluentSMTP** avec **Brevo** ou un service équivalent.

Le plugin UFSC ne stocke pas de clé API Brevo et ne dépend pas directement d’un fournisseur SMTP. La configuration de délivrabilité, d’authentification de domaine, SPF, DKIM et DMARC reste à gérer dans l’outil SMTP ou le service email utilisé par le site.

## Développement & personnalisation

Ce plugin a été conçu, développé et personnalisé spécifiquement pour l’**UFSC — Union Française des Sports de Combat**.

Il est développé par l’agence **Studio Reactiv**, spécialisée dans la création de solutions web, WordPress, outils métiers, communication digitale et accompagnement technique sur mesure.

Site : [https://studioreactiv.fr](https://studioreactiv.fr)

## Maintenance

Ce plugin étant un outil métier personnalisé, sa maintenance doit être réalisée avec prudence.

Recommandations :

- effectuer une sauvegarde complète avant toute mise à jour ;
- tester les évolutions en environnement de préproduction ;
- vérifier les parcours critiques avant mise en production ;
- documenter les modifications fonctionnelles et techniques ;
- éviter les modifications destructives de tables ou de données ;
- contrôler les impacts sur les clubs, licences, affiliations, compétitions et communications ;
- conserver une attention particulière à la non-régression.

## Avertissement

Ce plugin est un outil métier personnalisé. Toute modification doit être réalisée avec prudence, après sauvegarde complète et idéalement en environnement de préproduction.

Les données gérées peuvent être sensibles sur le plan administratif et fédéral. Les évolutions doivent respecter les règles de sécurité WordPress, la logique métier existante et les contraintes de conservation des données.

## Évolutions récentes

- Ajout du module Communication UFSC.
- Ajout des campagnes email.
- Ajout du carnet d’adresses.
- Ajout des listes personnalisées.
- Ajout de l’historique des campagnes.
- Ajout de la prévisualisation avancée.
- Ajout du diagnostic destinataires.
- Ajout de la file d’attente email.
- Ajout des exports CSV.
- Renforcement des aides d’utilisation.
- Amélioration du suivi des campagnes et des compteurs d’envoi.

## Crédit

Développé par **Studio Reactiv** pour l’**UFSC — Union Française des Sports de Combat**.

Site : [https://studioreactiv.fr](https://studioreactiv.fr)
