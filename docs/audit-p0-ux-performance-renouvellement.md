# Audit P0 — UX, performance et renouvellement

## Périmètre audité

Le shortcode `UFSC_Frontend_Shortcodes`, l'assistant rendu par `render_renewal_assistant()`, `frontend-dashboard.js`, les styles réellement chargés, le handler `admin_post_ufsc_bulk_renew_licences`, l'intégration panier et la liste SQL d'administration ont été suivis de bout en bout.

| Anomalie | Cause exacte / méthode | Impact | Correction et preuve | État / test |
|---|---|---|---|---|
| Case incomplète non sélectionnable | L'ancien rendu confondait éligibilité panier et sélection. | Impossible de compléter un dossier. | `selectable` dépend désormais uniquement des blocages métier et du produit; `complete`/`cart_eligible` restent distincts. Chaque input a un id, label et `aria-describedby`; journal WP_DEBUG explicite les cinq états. | Corrigé; test statique P0. |
| Bouton individuel mort | Le contrôle était exclusivement un `button` JavaScript, sans destination serveur. | Aucun parcours en cas d'asset absent ou d'erreur JS. | Le contrôle est un lien réel vers `ufsc_section=licences-renouvellement&renew_source_id=…&target_season=…`; le serveur présélectionne la source et ouvre l'étape 2, tandis que JS améliore focus/défilement. | Corrigé; lint JS et test statique. |
| Trois étapes/actions visibles | Les thèmes pouvaient surcharger l'attribut `hidden`. | Tunnel illisible. | Règle isolée `.ufsc-club-portal [hidden]{display:none!important}` et état initial serveur/JS unique. | Corrigé; test statique. |
| Ajout panier silencieux | Le chemin existait mais n'était pas accessible depuis un tunnel fiable. | Pas de ligne réelle. | Le submit POST appelle `ufsc_handle_bulk_renew_licences()` puis `ufsc_add_renewal_sources_to_cart()`; nonce, propriétaire, saison, affiliation, doublons et quantité 1 sont contrôlés; WooCommerce affiche une notice puis redirige. | Couvert par tests runtime existants; recette DEV requise. |
| Actions d'archive admin | Le renderer affichait `Modifier` selon la capability sans examiner la saison de la ligne. | Mutation historique possible depuis l'UI. | Chaque ligne appelle `ufsc_get_licence_season_context_status()`; une archive n'offre que Consulter et l'action contextuelle (renouveler, nouvelle licence, demande ou paiement payable). | Corrigé; test statique P0. |
| 996 lignes admin | Paramètre autorisant 100 et défaut historique non conforme; l'ancien constat correspondait à un rendu non borné. | DOM et catégories coûteux. | Requête SQL `COUNT` séparée, puis `LIMIT/OFFSET`; 25 par défaut, 50 maximum. | Corrigé côté code; Query Monitor DEV requis. |
| Boutons documents peu lisibles | Héritage Astra/Elementor et absence de contrat visuel isolé. | Contraste et cible insuffisants. | `.ufsc-document-button` impose bleu foncé, blanc, 44 px, hover et focus visible sous `.ufsc-club-portal`. | Corrigé côté code; contrôle navigateur requis. |

## HTML avant/après (synthèse)

Avant: checkbox sans identifiant/label associé et bouton sans URL. Après: `input#ufsc-renewal-source-ID` décrit par `ufsc-renewal-reason-ID`, puis un `<a href="…renew_source_id=ID…" data-ufsc-renew-one="ID">` utilisable sans JavaScript.

## Sécurité et non-destruction

Le handler est POST-only, vérifie nonce, session, club connecté et saison canonique. Le service lit la source puis place les modifications autorisées exclusivement en métadonnées panier; aucune mise à jour de l'archive n'est effectuée. Les identifiants permanents et la filiation restent transmis à quantité 1. Aucun changement de schéma ni donnée réelle n'a été exécuté.

## Risques résiduels

Les chiffres Query Monitor, les captures Astra/Elementor, le checkout et l'état CI distant exigent une instance WordPress/WooCommerce DEV authentifiée. Ils ne sont donc pas déclarés validés dans cet audit local.

## Score de sortie (avant recette DEV)

| Domaine | Avant | Après local | Preuve | Risque restant |
|---|---:|---:|---|---|
| Métier | 6/10 | 8/10 | contexte partagé, filiation et handler runtime | checkout DEV |
| Sécurité | 7/10 | 9/10 | POST, nonce, propriété, saison, listes blanches | test d'intrusion DEV |
| Admin UI / UX | 5/10 | 8/10 | archives contextuelles, pagination bornée | fiche et captures réelles |
| Front UI / UX | 4/10 | 8/10 | fallback, étapes, contrôles isolés | Astra/Elementor réel |
| Responsive | 5/10 | 8/10 | cartes et actions pleine largeur | matrice navigateurs |
| Accessibilité | 5/10 | 8/10 | labels, descriptions, focus, cible 44 px | audit lecteur d'écran |
| Panier | 4/10 | 8/10 | tests runtime nominatif/quantité 1 | commande réelle de test |
| Multi-saison | 7/10 | 9/10 | saison canonique et contexte par ligne | bascule future DEV |
| Performance | 4/10 | 8/10 | `COUNT` + `LIMIT/OFFSET`, 25/50 | mesures Query Monitor |
| Tests | 6/10 | 9/10 | suite autonome complète | PHPUnit indisponible |
| Documentation | 5/10 | 9/10 | audit, performance, recette | compléter avec captures |
| Recette DEV | 3/10 | 3/10 | checklist fournie | non exécutée ici |

**Décision : prêt pour recette DEV, mais No-Go production tant que les mesures, captures, checkout et scénarios DEV ne sont pas validés.**
