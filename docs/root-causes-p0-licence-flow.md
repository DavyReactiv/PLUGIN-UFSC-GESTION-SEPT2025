# Causes racines P0 — parcours licences UFSC

## Périmètre

Rapport établi avant correction métier. Le but est d'expliquer les symptômes observés sans modifier les données de production ni réécrire le plugin.

## RC-P0-01 — intention de finalisation non canonique

### Symptôme
Un formulaire peut afficher ou envoyer `submit_for_validation`, mais la demande est enregistrée sans déclencher la finalisation attendue.

### Cause
`UFSC_Unified_Handlers::normalize_licence_intent()` ne reconnaît que `save_draft`, `continue`, `verify`, `add_to_cart`. Une intention `submit_for_validation` est normalisée en `continue`.

`process_licence_request()` n'alloue le quota que lorsque `should_add_licence_to_cart()` retourne vrai. Une intention perdue devient donc un simple enregistrement.

### Responsable
`includes/core/class-unified-handlers.php`

### Reproduction
`tests/repro/submit-intent-repro.php`

### Correction minimale attendue
Faire de `submit_for_validation` une intention canonique de finalisation. La décision incluse/payante reste serveur : l'intention utilisateur ne doit jamais imposer elle-même un produit ou un prix.

---

## RC-P0-02 — réservation impossible pour des lignes historiques `is_included = NULL`

### Symptôme
L'écran indique qu'une licence est incluse et qu'il reste du quota, mais le compteur ne progresse pas ou la licence n'est pas réellement réservée dans le pack.

### Cause
`ufsc_allocate_pack_credit()` décide correctement qu'une place incluse reste disponible, puis exécute une mise à jour limitée à `is_included = 0`. Une ligne historique avec `NULL` ne correspond pas à cette condition. La fonction peut alors retourner `included=true` mais `reserved=false`.

### Responsable
`inc/common/compliance.php`

### Reproduction
`tests/repro/pack-null-reservation-repro.php`

### Correction minimale attendue
Autoriser la réservation lorsque `is_included` vaut `0` ou `NULL`, conserver le verrou saison/club et relire/valider la réservation avant de considérer l'opération réussie.

---

## RC-P0-03 — une colonne legacy peut annuler l'écriture canonique du statut

### Symptôme
Après `Envoyer pour validation`, la page recharge mais la licence reste `Brouillon` côté club et admin.

### Cause
`UFSC_Licence_Status::update_status_columns()` construit une seule opération SQL qui écrit la valeur canonique, par exemple `en_attente`, à la fois dans `statut` et dans l'ancienne colonne `status` lorsqu'elles existent toutes les deux. Une contrainte ou un vocabulaire legacy incompatible sur `status` peut faire échouer toute l'opération et empêcher aussi `statut` d'être écrit.

### Responsable
`inc/common/licence-status.php`

### Reproduction
`tests/repro/status-dual-column-repro.php`

### Correction minimale attendue
`statut` doit être l'écriture canonique prioritaire et vérifiée. La compatibilité `status` doit être mise à jour séparément avec un mapping legacy et ne doit jamais pouvoir annuler l'écriture canonique.

---

## RC-P0-04 — le handler réellement utilisé par le bouton Journey duplique la finalisation

### Symptôme
Le bouton visible `Envoyer pour validation` peut avoir un comportement différent du formulaire canonique nouvelle licence.

### Cause
`ufsc_journey_render_finalize_form()` poste vers `admin_post_ufsc_journey_finalize_licence`. `ufsc_journey_finalize_licence()` recalcule lui-même le quota, écrit lui-même statut/paiement, trace puis redirige. Il ne passe pas par la même finalisation que `UFSC_Unified_Handlers::process_licence_request()`.

De plus, après l'écriture, il déclenche `ufsc_licence_updated`; la couche `licence-workflow-structural.php` écoute ce hook et peut tenter une nouvelle finalisation. La même intention peut donc être traitée par plusieurs couches.

### Responsables
- `inc/common/club-journey.php`
- `inc/common/licence-workflow-structural.php`
- `includes/core/class-unified-handlers.php`

### Correction minimale attendue
Créer une seule opération serveur de finalisation d'une licence déjà enregistrée et la faire appeler par les différents contrôleurs. Les contrôleurs gèrent auth/nonce/redirection ; le service gère décision quota, statut et résultat.

---

## RC-P0-05 — erreurs d'écriture ignorées dans Journey

### Symptôme
Le navigateur revient sur la page comme si l'envoi avait réussi, mais le statut reste inchangé.

### Cause
Dans la branche incluse de `ufsc_journey_finalize_licence()`, le résultat de `UFSC_Licence_Status::update_status_columns()` n'est pas contrôlé. Le code continue ensuite avec `payment_status=included`, la trace de soumission et la redirection.

### Responsable
`inc/common/club-journey.php`

### Correction minimale attendue
Toute écriture critique doit être contrôlée puis relue. En cas d'échec : aucune confirmation de succès, pas de progression silencieuse, message explicite et dossier conservé.

---

## RC-P1-01 — confirmation utilisateur non cohérente

### Symptôme
Après l'action, aucun message de confirmation n'est visible.

### Cause probable confirmée par recherche de code
Journey redirige avec `ufsc_message=licence_included`, mais le code `licence_included` n'est pas traité comme une confirmation front unique et explicite dans le parcours actuel. Les messages sont dispersés entre query args, notices Woo et helpers de redirection.

### Correction attendue
Une seule fonction de message flash front, basée sur codes non sensibles, avec succès/erreur et affichage accessible (`role=status` / `aria-live`).

---

## RC-P1-02 — dette de couches anciennes

Des fichiers `p0-quota-ui.php`, `p0-quota-cart-kpi.php`, etc. restent dans le dépôt. Les tests de consolidation confirment qu'ils ne sont plus chargés par `feature-flags.php`. Ils ne sont donc pas la cause runtime directe actuelle, mais rendent l'analyse plus difficile et augmentent le risque qu'une future correction les réactive accidentellement.

Aucune suppression n'est proposée avant inventaire complet des références/tests/documentation.

---

## RC-P0-06 — la frontière 11e licence doit être prouvée par exécution

Le code de renouvellement payant appelle réellement `WC()->cart->add_to_cart()` après décision de quota et construit une ligne quantité 1 avec métadonnées licence/club/saison. Cela est structurellement correct mais ne suffit pas à certifier un environnement WooCommerce réel.

### Reproduction isolée
`tests/repro/paid-renewal-cart-repro.php`

### Validation finale nécessaire
Test d'intégration WordPress/WooCommerce sur DEV : dix crédits consommés, 11e licence, vraie session Woo, vraie clé panier, persistance après refresh, checkout, échec produit/cart et idempotence.

---

## Ordre de correction P0 recommandé

1. Corriger la réservation `NULL` sans migration destructive.
2. Séparer écriture canonique `statut` et compatibilité legacy `status`.
3. Introduire une opération serveur unique de finalisation incluse/payante avec résultat explicite.
4. Faire reconnaître `submit_for_validation` comme intention canonique.
5. Faire appeler cette opération par Journey et Unified Handler ; neutraliser la double finalisation structurelle une fois les consommateurs migrés.
6. Ajouter confirmation front unique.
7. Transformer les reproductions en tests de non-régression bloquants une fois corrigées.
8. Exécuter la recette WooCommerce réelle de la 11e licence.

## Protection des données

Aucune correction ci-dessus ne nécessite de backfill massif. Les anciennes saisons et les valeurs historiques restent lisibles. Toute compatibilité legacy est conservée en lecture ; seule l'autorité d'écriture est clarifiée pour les nouvelles transitions.
