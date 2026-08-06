# Audit final — identifiants et renouvellements

## Périmètre réellement raccordé

Le plugin démarre dans `ufsc-clubs-licences-sql.php`. Les fiches canoniques club et licence sont rendues par `UFSC_SQL_Admin::render_clubs()` / `render_licences()` puis leurs formulaires dans `includes/admin/class-sql-admin.php`. Les mutations d’identifiants passent par `admin-post.php`, les actions `ufsc_generate_identifier` et `ufsc_save_asptt_identifier`, un nonce lié au type et à l’ID, et la capacité `manage_options`.

Le portail club actif est `UFSC_Frontend_Shortcodes` (`includes/frontend/class-frontend-shortcodes.php`). Il sépare saison active et archives, n’affiche les archives qu’à la demande et transmet chaque source nominative au panier. Les traitements panier, restauration, checkout et commande sont dans `inc/woocommerce/cart-integration.php` et `inc/woocommerce/hooks.php`. Le produit d’affiliation par défaut reste 4823; le produit de licence est configuré séparément.

## Modèle canonique

| Entité | Identité permanente UFSC | Identifiant externe ASPTT | Ligne annuelle |
|---|---|---|---|
| Club | `clubs.numero_affiliation_ufsc` + registre central | `clubs.numero_affiliation_asptt` | `ufsc_affiliations_seasons(club_id, season)` |
| Personne | `licences.numero_licence_ufsc` + `person_identifier` | `licences.numero_licence_asptt` | licence avec saison et `previous_licence_id` |

`UFSC_Identifier_Service` est l’unique autorité d’attribution. Deux séquences atomiques produisent `UFSC-C-…` et `UFSC-L-…`; le registre impose l’unicité de la valeur et de l’entité. L’ASPTT reste facultatif, administrateur, unique lorsqu’il existe et refuse tout préfixe `UFSC-`.

## Alias legacy et classification

* **UFSC confirmé** : `numero_affiliation_ufsc`, `numero_licence_ufsc`.
* **ASPTT confirmé** : `numero_affiliation_asptt`, `numero_licence_asptt`.
* **Ambigus, lecture de compatibilité UFSC seulement** : `num_affiliation`, `numero_affiliation`, `numero_licence`, `num_licence`, `licence_number`.
* **Ambigus, jamais présentés ni copiés en ASPTT** : `numero_licence_delegataire`, `source_licence_number`.
* **Annuels** : `season`, `saison`, `paid_season`, `season_end_year`, et les anciens numéros annuels éventuellement présents.

Aucun alias ambigu n’est écrit dans un champ canonique. Le diagnostic administrateur est en lecture seule et expose les doublons sans donnée médicale. Une analyse de formats sur une copie anonymisée de production reste manuelle avant toute classification supplémentaire.

## Parcours avant / après

Avant, plusieurs libellés historiques pouvaient laisser croire qu’un numéro délégataire était ASPTT et la fiche ne proposait pas l’autorité centrale. Après, chaque fiche affiche un bloc distinct, UFSC en lecture seule avec génération volontaire, et ASPTT dans un formulaire administrateur séparé. Le renouvellement crée une ligne et conserve la filiation; il ne modifie pas l’archive.

La porte `ufsc_club_can_manage_licences_for_season()` reste fail-closed. Elle est appelée dans les points d’entrée front/WooCommerce. Seuls `active` et `validated` permettent de continuer. Les lignes panier sont nominatives, non fusionnées et de quantité 1. `on-hold` n’active jamais une licence; le paiement conduit à `pending_validation`.

## Risques et points manuels

* Le schéma legacy varie selon l’installation : vérifier le diagnostic avant de poser un index historique.
* XLSX dépend de PhpSpreadsheet; l’aperçu/import doit être testé avec un export réel anonymisé.
* La concurrence MySQL et les transitions de passerelle doivent être recettées sur un clone WordPress/WooCommerce réel.
* Les documents réutilisables doivent suivre leur règle explicite de validité; aucun document expiré n’est copié par défaut.

## Migration et rollback

La migration 1.4.0 est additive et relançable : tables de séquence, registre, audit, colonnes canoniques et filiation. Elle ne génère aucun numéro, ne déplace aucun alias, ne fusionne et ne supprime rien. En cas de doublons historiques, les contraintes risquées sont omises et diagnostiquées. Pour rollback applicatif : sauvegarder la base, remettre la version précédente du plugin; laisser les nouvelles tables/colonnes en place. Ne les supprimer qu’après audit séparé, jamais dans le rollback courant.
