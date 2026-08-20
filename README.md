# UFSC – Gestion Clubs & Licences

## Introduction

**UFSC – Gestion Clubs & Licences** est un plugin WordPress professionnel développé sur mesure pour l’**UFSC — Union Française des Sports de Combat**.

Il centralise la gestion des clubs, licences, affiliations annuelles, documents administratifs, communications et outils de suivi dans un environnement WordPress maintenable, sécurisé et adapté aux règles métier de l’UFSC.

Le plugin est conçu comme un outil métier personnalisé destiné aux équipes administratives, aux clubs affiliés et aux mainteneurs techniques.

## Statut de la version actuelle

- **Version plugin : `082026`**.
- **Saison active de référence : 2026-2027**.
- Saison métier : **du 1er août au 31 juillet**.
- La stabilisation production issue de la **PR #547** a été fusionnée dans `main` le **20 août 2026**.
- Les parcours critiques licences, renouvellements, affiliations, quota et panier WooCommerce ont été validés sur l’environnement DEV avant fusion.
- Les corrections de cette stabilisation ont été conçues pour préserver les licences, clubs et saisons historiques.

## Règles métier principales

### Affiliation annuelle

Un club est une entité permanente. Son affiliation, elle, est annuelle.

- Un club déjà enregistré la saison précédente **ne recrée jamais son club**.
- Il se connecte avec son compte existant et renouvelle uniquement son affiliation pour la nouvelle saison.
- Un club jamais enregistré suit le parcours de première création puis première affiliation.
- Une affiliation payée passe en **attente de validation UFSC** avant de devenir active.
- L’historique des saisons précédentes est conservé.

### Pack de 10 licences incluses

L’affiliation donne accès à **10 licences incluses par club et par saison**.

- Licences 1 à 10 : incluses dans le pack, sans ajout au panier.
- À partir de la 11e : licence payante via WooCommerce.
- La règle s’applique aussi bien aux **nouvelles licences** qu’aux **renouvellements**.
- Une licence incluse finalisée est enregistrée avec le statut métier prévu, notamment `en_attente` lorsqu’elle doit être contrôlée par l’administration.
- Une licence payante est transmise au panier WooCommerce avec ses métadonnées UFSC.

### Niveaux sportifs

Le niveau sportif du licencié est géré dans les formulaires et doit rester disponible côté club et administration :

- Débutant ;
- Assaut ;
- Classe C ;
- Classe B ;
- Classe A ;
- Pro ;
- Vétéran.

Les valeurs historiques sont conservées lors des renouvellements.

## Parcours utilisateur

### Club existant — renouvellement d’affiliation

1. Le responsable se connecte à son compte existant.
2. Le plugin retrouve le même club et son historique.
3. Si aucune affiliation active n’existe pour la saison courante, le portail propose **« Renouveler mon affiliation 2026-2027 »**.
4. Le club vérifie ou actualise ses informations.
5. L’affiliation est ajoutée au panier WooCommerce en quantité 1.
6. Après paiement, l’affiliation annuelle passe en attente de validation UFSC.
7. Après validation, l’affiliation devient active pour 2026-2027.
8. Les 10 licences incluses de la saison sont alors utilisables.
9. Le club peut renouveler ses anciennes licences ou créer de nouvelles licences.

### Nouveau club — première affiliation

1. Création du compte utilisateur.
2. Création du club une seule fois.
3. Enregistrement initial du club avec protection contre les doubles soumissions.
4. Paiement de la première affiliation annuelle.
5. Attente de validation UFSC.
6. Activation de l’affiliation.
7. Ouverture du quota de 10 licences incluses pour la saison.

### Nouvelle licence

1. Le club saisit le licencié.
2. Le plugin vérifie la saison, le quota et l’éligibilité du dossier.
3. S’il reste une place dans le pack, la licence est finalisée sans WooCommerce.
4. Si le quota est atteint, la licence est ajoutée au panier WooCommerce.
5. Le panier affiche clairement **« Nouvelle licence »**.
6. Les doubles clics et rechargements ne doivent jamais créer de doublon.

### Renouvellement d’une licence

1. Le club choisit une licence d’une saison précédente.
2. Le dossier historique reste intact.
3. Le club vérifie et complète le nouveau dossier de saison.
4. Le service canonique décide si la licence est incluse ou payante.
5. Si elle est incluse, elle est finalisée sans panier.
6. Si elle est payante, elle est ajoutée au panier avec le libellé **« Renouvellement de licence »**.
7. La nouvelle licence conserve la traçabilité avec la licence source sans modifier cette dernière.

## Fonctionnalités principales

### Gestion des clubs

- Création et édition des clubs.
- Suivi administratif des structures.
- Gestion des statuts.
- Coordonnées, informations légales et contacts.
- Gestion des dirigeants et référents.
- Logo et profil du club.
- Association et suivi des documents administratifs.
- Espace Club responsive avec navigation Vue d’ensemble, Informations du club, Dirigeants, Documents, Archives et autres sections métier.

### Gestion des licences

- Création et renouvellement de licences.
- Rattachement des licenciés au club et à la saison.
- Gestion des informations personnelles et sportives.
- Gestion du niveau sportif.
- Gestion des rôles du club.
- Honorabilité pour les fonctions concernées.
- Quota des licences incluses.
- Licences supplémentaires payantes.
- Pagination, recherche, filtres et archives saisonnières.
- Contrôles administratifs.
- Exports de données.
- Traçabilité des créations, soumissions et validations lorsque les informations sont disponibles.

### Affiliations

- Première affiliation d’un nouveau club.
- Renouvellement annuel d’un club existant.
- Suivi de la saison active.
- États : à renouveler, attente de paiement, attente de validation, actif, correction demandée, refusé ou suspendu selon les écrans concernés.
- Paiement WooCommerce.
- Validation administrative.
- Conservation de l’historique annuel.

### WooCommerce

Le plugin utilise WooCommerce comme frontière de paiement pour les éléments réellement payants.

- Produit licence canonique configuré dans les réglages UFSC.
- Produit affiliation canonique configuré dans les réglages UFSC.
- Panier natif WooCommerce.
- Quantité 1 pour une demande nominative ou une affiliation annuelle.
- Métadonnées UFSC visibles dans le panier.
- Persistance du panier sur les parcours `admin-post.php`.
- Protection anti-doublon.
- Les licences incluses ne sont pas envoyées inutilement au panier.

### Documents administratifs

- Dépôt et suivi des documents.
- Validation administrative.
- Gestion des statuts.
- Consultation des pièces liées aux clubs ou dossiers suivis.
- Gestion des pièces d’honorabilité selon les rôles concernés.

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

## Compatibilité avec le module compétition

La gestion avancée des compétitions, inscriptions sportives, catégories, participants et statuts d’engagement est portée par un plugin dédié : **UFSC Licences Compétitions**.

UFSC Gestion conserve la responsabilité principale des clubs, licences administratives, affiliations, documents, paiements associés, communications, diagnostics et exports.

## Compatibilité des données historiques

Le plugin doit rester non destructif vis-à-vis des données existantes.

La version 082026 contient notamment :

- un résolveur de stockage compatible avec différentes structures historiques ;
- une détection des colonnes de saison existantes ;
- des migrations additives ;
- une compatibilité `dbDelta()` pour les tables techniques d’identifiants ;
- un préflight des identifiants optionnels historiques afin que les valeurs absentes de `numero_licence_delegataire` et `num_affiliation` soient représentées par `NULL` avant la création de contraintes `UNIQUE` ;
- une compatibilité MySQL strict pour éviter les comparaisons invalides avec des dates historiques vides ;
- une conservation des anciennes saisons sans réécriture massive.

Aucune migration ne doit supprimer une licence, un club ou une saison pour résoudre une incompatibilité de schéma.

## Sécurité et non-régression

Le plugin applique les principes WordPress pour les opérations sensibles :

- contrôle des capacités et permissions ;
- vérification des nonces ;
- sanitization des entrées ;
- escaping des sorties ;
- requêtes préparées lorsque des paramètres dynamiques sont utilisés ;
- finalisation métier côté serveur ;
- protection contre les doubles soumissions ;
- migrations additives et non destructives ;
- séparation entre logique métier et correctifs de présentation ;
- conservation des lignes historiques.

Les parcours licences, quota, panier et affiliations doivent être considérés comme des parcours critiques. Toute modification future doit être testée en préproduction avant fusion.

## Qualité et tests

La stabilisation d’août 2026 a été validée par le **UFSC quality gate** avec succès avant fusion.

Le pipeline couvre notamment :

- validation Composer ;
- lint PHP ;
- syntaxe JavaScript ;
- tests de régression standalone et runtime ;
- tests P0 licences ;
- PHPUnit ;
- PHPStan ;
- WordPress Coding Standards / sécurité.

Les tests automatisés complètent la recette fonctionnelle DEV mais ne remplacent pas les tests WooCommerce et WordPress réels.

## Mise en production

Procédure recommandée :

1. effectuer une sauvegarde complète de la base de données et de `wp-content` ;
2. disposer d’un point de restauration vérifié ;
3. installer la version issue de `main` ;
4. dans une fenêtre de maintenance, désactiver puis réactiver une fois le plugin afin d’exécuter les migrations d’activation prévues par la version ;
5. purger les caches WordPress, serveur, CDN et navigateur si nécessaire ;
6. vérifier l’administration UFSC Clubs et Licences ;
7. vérifier l’Espace Club sur desktop et mobile ;
8. effectuer un smoke-test nouvelle licence incluse ;
9. effectuer un smoke-test licence payante au-delà du quota ;
10. vérifier un renouvellement de licence ;
11. vérifier le parcours de renouvellement d’affiliation ;
12. vérifier les anciennes saisons et archives ;
13. contrôler le `debug.log` afin de confirmer l’absence d’erreur UFSC bloquante ;
14. laisser `WP_DEBUG_DISPLAY` désactivé en production.

## Points externes au plugin

Des installations WordPress peuvent encore produire des notices provenant d’autres plugins ou du thème, par exemple des handles enregistrés trop tôt, des appels conditionnels WordPress avant la requête principale ou des chargements de traductions précoces.

Ces notices ne doivent pas être masquées dans UFSC Gestion sans identifier précisément leur source.

## Architecture

Le plugin est construit autour d’une structure modulaire et peut s’appuyer sur des tables métiers historiques ainsi que sur des tables dédiées aux modules plus récents.

Principes :

- intégration WordPress native ;
- classes et fichiers organisés par domaine ;
- stockage saisonnier explicite ;
- compatibilité avec les installations historiques ;
- utilisation de `dbDelta()` lorsque cela est adapté ;
- services canoniques pour les parcours sensibles ;
- couches UX/CSS séparées de la logique métier ;
- maintenance incrémentale et non destructive.

## Compatibilité email

Le module Communication UFSC utilise `wp_mail()`.

Le site peut donc s’appuyer sur un service SMTP externe configuré par un plugin spécialisé, par exemple **FluentSMTP** avec **Brevo** ou un service équivalent.

Le plugin UFSC ne stocke pas directement de clé API Brevo. La configuration SPF, DKIM, DMARC et de délivrabilité reste à gérer dans le service email utilisé par WordPress.

## Maintenance

Recommandations pour les évolutions futures :

- toujours sauvegarder avant mise à jour ;
- développer les évolutions sur une branche dédiée ;
- éviter les modifications directes en production ;
- tester sur DEV/préproduction ;
- faire passer le quality gate ;
- limiter chaque PR à un périmètre clair ;
- ne pas mélanger une refonte CSS avec une modification du moteur licences sauf nécessité démontrée ;
- préserver le modèle saisonnier ;
- ne jamais modifier en masse les anciennes licences pour corriger un problème d’affichage ;
- vérifier WooCommerce après toute modification du parcours de paiement ;
- documenter les changements dans `CHANGELOG.md` et ce README.

## Évolutions récentes — août 2026

- Stabilisation des renouvellements de licences.
- Stabilisation des nouvelles licences.
- Finalisation quota-first des 10 licences incluses.
- Passage fiable de la 11e licence et des suivantes au panier WooCommerce.
- Correction des métadonnées panier « Nouvelle licence » et « Renouvellement de licence ».
- Renouvellement annuel d’affiliation sécurisé pour les clubs existants.
- Première affiliation sécurisée pour les nouveaux clubs.
- Protection contre les doubles soumissions et doublons inter-saisons.
- Amélioration du portail Compte Club et de son responsive.
- Pagination et filtres licences consolidés.
- Compatibilité `dbDelta()` renforcée.
- Compatibilité des anciennes valeurs d’identifiants avec les contraintes uniques.
- Compatibilité MySQL strict pour les données historiques.
- Conservation des anciennes saisons et des licences sources lors des renouvellements.

## Développement & crédit

Ce plugin a été conçu, développé et personnalisé spécifiquement pour l’**UFSC — Union Française des Sports de Combat**.

Développé par **Studio Reactiv**.

Site : [https://studioreactiv.fr](https://studioreactiv.fr)
