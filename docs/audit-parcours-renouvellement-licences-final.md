# Audit final du parcours de renouvellement des licences

## Périmètre réellement exécuté

Le portail est produit par `UFSC_Frontend_Shortcodes` (`ufsc_club_dashboard`, `ufsc_club_licences`) et la liste est rendue par `render_club_licences()`. Les mutations passent par `UFSC_Unified_Handlers` (création, édition et poids) et par les actions `ufsc_add_to_cart` / `ufsc_bulk_renew_licences`. L'administration active est `UFSC_SQL_Admin`; elle fournit liste, fiche, filtre saison canonique, filtres niveau/poids et vue de renouvellement.

WooCommerce est raccordé par `inc/woocommerce/cart-integration.php` (POST sécurisé, panier, restauration, validation checkout, métadonnées de ligne) et `inc/woocommerce/hooks.php` (transitions payées et création annuelle). Le produit licence provient de `ufsc_get_licence_product_id()`; l'affiliation conserve son réglage et son défaut 4823.

## Champs et services

La saison est résolue dans l'ordre `season`, `saison`, `paid_season`, `season_end_year`; la saison active vient de `UFSC_Season_Service::get_current_season()`. `UFSC_Renewal_Service::season_context_status()` conserve le statut historique et expose état, filiation, commande payable et action. `previous_licence_id`, `person_identifier` et `numero_licence_ufsc` relient les années; ASPTT reste annuel et n'est pas copié.

Le niveau est centralisé par `ufsc_get_sport_level_options()` et filtrable. Le poids déclaratif `poids` est distinct de toute pesée officielle; son handler contrôle propriétaire, nonce, saison courante, plage et journalise auteur/date avant recalcul par `UFSC_Category_Repository`.

## Flux avant / après

Avant, Archives dépendait essentiellement d'une ancre et l'affichage en lecture seule masquait les actions de renouvellement. Après, l'URL métier `ufsc_section=licences-archives` rend la section puis déplace focus et scroll. Une zone dédiée présente uniquement la saison immédiatement précédente, un assistant trois étapes et un POST groupé; la table Archives demeure strictement consultative.

Chaque source éligible devient une ligne panier quantité 1 avec identité unique, personne, source, saison, niveau et poids. Les contrôles sont rejoués à la restauration et au checkout. Une transition réellement payée crée une nouvelle ligne `pending_validation`, conserve UFSC/filiation/personne, mais pas ASPTT, ancien paiement, statut validé, commande ou documents expirés. `on-hold` n'emprunte pas ce chemin payé.

## Risques, corrections et limites

Corrections: navigation Archives stable, assistant front, exigences niveau/poids, lignes nominatives non fusionnées, métadonnées, statut post-paiement et identité permanente. Les protections affiliation, propriété, doublon panier/commande et archives intactes sont conservées.

Limites: la matrice documentaire et les catégories restent celles configurées dans le référentiel existant; les notifications dépendent des hooks/mails déjà configurés. Une recette WordPress/WooCommerce avec passerelles réelles, thème Astra/Elementor, e-mails et navigateurs reste obligatoire. Aucun benchmark base réelle ni modification de données n'a été exécuté.
