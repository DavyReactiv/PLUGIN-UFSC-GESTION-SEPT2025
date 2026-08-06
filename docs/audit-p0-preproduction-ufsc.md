# Audit P0 de préproduction UFSC

## 1. Résumé exécutif

Audit statique réalisé depuis `fd6ca410b8a0925b91e50f1546a3051c16506958`, fusion GitHub #496. La copie fournie ne contenait ni remote configuré ni objet/référence de la branche #497. Le remote `origin` a été rétabli, mais `git fetch origin --prune` échoue avec `CONNECT tunnel failed, response 403`. Par conséquent, **aucune identité binaire avec #497 n'est affirmée** et son SHA/merge-base/commit list restent invérifiables dans cet environnement.

Le contenu de `main` contient néanmoins 12 des 14 fichiers ciblés. Deux défauts P0 ont été corrigés : contournement du produit d'affiliation configuré et absence de contrôle de propriété dans un ancien endpoint d'affiliation. Les alias historiques restent lisibles à des fins diagnostiques sans être requalifiés ASPTT. La livraison est **No-Go production** et candidate à une **recette DEV** après revue de PR et installation du ZIP ; la recette WordPress/WooCommerce, visuelle, accessibilité et charge reste obligatoire.

## 2. SHA audité et état Git

| Élément | Valeur factuelle |
|---|---|
| Branche initiale | `work` |
| SHA initial / `main` fourni | `fd6ca410b8a0925b91e50f1546a3051c16506958` |
| Fusion #496 | `fd6ca41`, parent fonctionnel `1b1f90f` |
| Branche de travail | `codex/audit-p0-preproduction-ufsc` |
| SHA branche #497 | Indisponible localement |
| Merge-base #497/main | Indisponible sans l'objet #497 |
| Limitation | Fetch HTTPS refusé par le tunnel (403) |

## 3. Architecture

```text
Bootstrap
 ├─ UFSC_Season_Service ──> saison 01/08–31/07
 ├─ UFSC_Storage_Resolver ──> tables modern/legacy (lecture fail-closed)
 ├─ UFSC_Identifier_Resolver ──> canoniques + alias ambigus diagnostiques
 ├─ UFSC_Identifier_Service ──> séquence/registre/audit transactionnels
 ├─ UFSC_Renewal_Service ──> identité personne + unicité annuelle
 └─ Woo helpers ──> affiliation gate ──> panier nominatif ──> commande
       └─ validation admin ──> activation annuelle (jamais on-hold)
```

Le bootstrap charge SQL avant migrations, puis saison, stockage, identifiants et renouvellement. Les helpers historiques de saison délèguent au service central. Un helper ancien `UFSC_Utils::get_current_season($pivot_month = 9)` demeure : il doit être supprimé seulement après inventaire runtime de ses consommateurs (P1), pas par renommage risqué.

## 4. Delta #497

« Présent main » signifie présent au SHA initial fourni. « Comparaison » reste `non vérifiable` faute de référence #497 ; `intégré ici` indique un delta recréé/validé par contrat et non un cherry-pick supposé.

| Fichier | Présent main | État vs #497 | Risque | Action |
|---|---:|---|---|---|
| `.github/workflows/php-syntax.yml` | oui | non vérifiable, divergent corrigé | CI ne distinguait pas PHPUnit | runner unique intégré |
| `docs/audit-identifiants-renouvellements-final.md` | non | non vérifiable | documentation historique inaccessible | non dupliqué ; remplacé par ce rapport |
| `docs/checklist-identifiants-renouvellements-preproduction.md` | non | non vérifiable | checklist historique inaccessible | remplacé par checklist DEV exhaustive |
| `inc/woocommerce/cart-integration.php` | oui | non vérifiable, corrigé | P0 ownership + produit en dur | helper configuré + ownership |
| `inc/woocommerce/settings-woocommerce.php` | oui | non vérifiable, corrigé | configuration produit ignorée | défaut 4823, configuration explicite respectée |
| `includes/admin/class-sql-admin.php` | oui | non vérifiable | surface admin large | conserver, recette DEV |
| `includes/admin/list-tables/class-ufsc-clubs-list-table.php` | oui | non vérifiable, corrigé | libellé métier obsolète | « clubs enregistrés » |
| `includes/core/class-ufsc-identifier-resolver.php` | oui | non vérifiable, corrigé | alias ambigus classés ASPTT | canoniques séparés + lecture diagnostique |
| `includes/core/class-ufsc-identifier-service.php` | oui | non vérifiable, corrigé | entité fantôme/casse/rollback | existence, `stripos`, rollback écriture |
| `includes/frontend/class-frontend-shortcodes.php` | oui | non vérifiable | parcours réel non exécuté | recette multi-rôles |
| `tests/run-tests.sh` | non | intégré ici | tests/CI opaques | runner autonome/PHPUnit explicite |
| `tests/test-affiliation-product-4823-static.php` | oui | non vérifiable, corrigé | contrat contredisait configuration | contrat mis à jour |
| `tests/test-final-stabilization-static.php` | oui | non vérifiable, corrigé | même contradiction | contrat mis à jour |
| `tests/test-identifier-resolver-runtime.php` | non | intégré ici | requalification alias non couverte | test runtime sans WordPress |

## 5. Base de données et migrations

Migration `1.4.0`, déclenchée au boot et à l'activation. Les trois tables d'identifiants utilisent `$wpdb->prefix`; les ajouts de colonnes sont additifs et idempotents. Aucun `DELETE`, backfill massif ou copie d'alias n'est introduit. Les contraintes du registre neuf sont sûres ; les contraintes sur tables historiques nécessitent un diagnostic des doublons avant recette. Il n'existe pas de rollback destructif : retour code + restauration sauvegarde, en conservant les nouvelles tables/colonnes.

À valider sur clone DEV : droits `ALTER`, moteur transactionnel, résultat `dbDelta`, doublons case-insensitive et plans d'index avec plusieurs milliers de licences.

## 6. Identifiants UFSC / ASPTT

Canoniques : `numero_affiliation_ufsc`, `numero_licence_ufsc`, `numero_affiliation_asptt`, `numero_licence_asptt`. Ambigus : `num_affiliation`, `numero_affiliation`, `numero_licence`, `num_licence`, `licence_number`, `numero_licence_delegataire`, `source_licence_number`. Ces derniers sont maintenant exposés par `read_ambiguous()` mais jamais lus comme ASPTT. La réservation est transactionnelle/idempotente, vérifie l'existence de l'entité, refuse `UFSC-` sans sensibilité à la casse et annule si l'écriture canonique échoue.

## 7. Affiliations

La saison canonique bascule le 1er août. Le gate accepte `active`/`validated` et refuse les états d'attente. Le produit par défaut est 4823 ; une configuration positive est désormais respectée partout dans l'ancien submit. Les commandes payables et CTA sont présents statiquement, mais doivent être éprouvés avec BACS, échec, reprise et concurrence en DEV.

## 8. Licences

Le service produit une nouvelle ligne annuelle, conserve `previous_licence_id`, `person_identifier` et le numéro UFSC, et omet ASPTT, paiement/commande et documents. Les flux individuels/groupés et les lignes panier quantité 1 sont présents. La non-activation sur `on-hold` est couverte statiquement/runtime existant. Les écrans, actions groupées, blocages et isolation inter-clubs exigent une recette réelle.

## 9. WooCommerce

Contrats audités : produit licence/affiliation, quantité 1, métadonnées nominatives, gate affiliation, restauration/validation panier et promotion après paiement. Correction P0 : l'endpoint historique vérifie désormais que le club POST appartient à l'utilisateur (administrateur excepté) et n'utilise plus `add_to_cart(4823, ...)`. Aucun paiement réel n'a été lancé ici.

## 10. Sécurité

Les mutations critiques inspectées utilisent nonce, capacités, sanitation et requêtes préparées. Les nouvelles corrections ferment l'accès inter-club de l'ancien submit. Aucun numéro ASPTT n'est ajouté au front et aucun identifiant UFSC n'est rendu éditable. P1 : revue dynamique exhaustive des uploads/imports/exports et vérification que les journaux de production ne contiennent aucune donnée médicale/personnelle.

## 11. Performance

Listes paginées et indexes sont présents, mais aucune base représentative (56 clubs/milliers de licences) n'est disponible. Les diagnostics de doublons font des agrégations potentiellement coûteuses : les exécuter hors pointe. P1 : capturer Query Monitor/slow log, budgets temps/mémoire et `EXPLAIN` sur DEV avant tout score supérieur à 8.

## 12. Admin UI/UX

Le libellé « Tous les clubs permanents » devient « Tous les clubs enregistrés ». Les listes, filtres, KPI, fiches et diagnostics existent. Sans navigateur WordPress authentifié, contrastes, clavier, états vides, confirmations et responsive restent non certifiés.

## 13. Front UI/UX

Le scope `.ufsc-club-portal`, les blocs affiliation/licences/archives et renouvellements existent statiquement. Captures attendues : dashboard, affiliation active/suspendue/paiement, renouvellement individuel/groupé, panier/checkout, erreur ownership, à 320, 375, 768, 1024, 1280 et 1440 px, thèmes Astra et Elementor.

## 14. Accessibilité

Les contrôles statiques ne suffisent pas à conclure WCAG 2.2 AA. Recette obligatoire : ordre de titres, labels/erreurs, focus visible, clavier, `aria-live`, tableaux, zoom 200 %, contraste et cibles tactiles. Écart P1 ouvert tant que axe/Lighthouse + contrôle manuel ne sont pas annexés.

## 15. Tests

Le runner classe les scripts autonomes et tests PHPUnit. Il exécute tous les scripts autonomes ; s'il détecte PHPUnit sans binaire, il l'annonce explicitement avec instruction d'installation. Tests ajoutés : non-requalification des alias, produit configuré, absence du 4823 en dur et ownership.

## 16. CI

Workflow : lint PHP hors vendor, syntaxe JS, runner unique. PHPUnit n'est plus ignoré silencieusement. P1 : ajouter PHPUnit comme dépendance dev/version compatible pour l'exécuter réellement plutôt que signaler son indisponibilité.

## 17. Registre des risques/problèmes

| ID | Domaine | Gravité | Fichier/méthode | Description / impact | Correction / test | Statut |
|---|---|---:|---|---|---|---|
| P0-WOO-01 | Woo/sécurité | P0 | `ufsc_club_affiliation_submit` | club POST falsifiable et produit configuré contourné | ownership + helper; test statique | corrigé |
| P0-ID-01 | identifiants | P0 | `assign`, `save_asptt` | entité fantôme, rollback incomplet, préfixe sensible à casse | vérification/rollback/`stripos`; lint/tests | corrigé |
| P1-ID-02 | données | P1 | resolver | alias délégataire/source requalifiés ASPTT | diagnostic-only + runtime | corrigé |
| P1-CI-01 | CI | P1 | runner | PHPUnit ignoré | détection et message explicites | corrigé partiellement (binaire absent) |
| P1-GIT-01 | traçabilité | P1 | Git #497 | comparaison impossible sans objet distant | re-fetch/revue dans PR GitHub | ouvert |
| P1-UX-01 | UI/a11y | P1 | écrans admin/front | aucune recette navigateur réelle | checklist/captures/axe | ouvert |
| P1-PERF-01 | performance | P1 | listes/diagnostics | aucune donnée de charge | profilage DEV | ouvert |
| P2-SEASON-01 | architecture | P2 | `UFSC_Utils` | helper pivot 9 historique | inventorier puis déléguer | ouvert |

## 18. Score factuel

| Domaine | Note /10 | Preuve | Écart vers 10 / action |
|---|---:|---|---|
| Architecture | 8.5 | services bootstrapés, séparation claire | supprimer/déléguer helper concurrent après runtime |
| Données/migrations | 8.0 | migration additive/idempotente | clone DB + doublons + rollback chronométré |
| Identifiants | 9.0 | transaction, audit, contrats ajoutés | concurrence MySQL réelle |
| Affiliations | 8.5 | gate/produit/CTA statiques | paiement réel et reprise |
| Licences | 8.5 | lineage/payload/gate | parcours complet multi-rôles |
| WooCommerce | 8.5 | métadonnées/gates/tests | gateways réels/webhooks |
| Sécurité | 8.5 | nonce/capability/ownership | pentest dynamique uploads/exports |
| Performance | 7.0 | pagination/indexes inspectés | charge et `EXPLAIN` |
| Admin UI | 7.5 | composants présents | captures/contrastes |
| Admin UX | 7.5 | filtres/actions présents | observation utilisateur |
| Front UI | 7.5 | portail scopé/responsive statique | matrices viewport/thème |
| Front UX | 7.5 | CTA/messages présents | recette club réelle |
| Responsive | 7.0 | media queries statiques | 6 largeurs + zoom |
| Accessibilité | 6.5 | quelques labels/ARIA | axe + clavier + contraste |
| Tests | 8.5 | suite autonome et contrats | PHPUnit + E2E |
| CI | 8.0 | lint PHP/JS/runner | PHPUnit installé + ZIP job |
| Documentation | 9.0 | audit + checklist | annexer preuves DEV |
| Observabilité | 7.5 | audit identifiers/logs | politique rétention/redaction |
| Préparation production | 7.5 | rollback/checklist | recette préproduction complète |

Moyenne non pondérée : **8,0/10**. Aucun 10 artificiel. UI/UX et technique n'atteignent pas encore les seuils de GO production faute de recette réelle.

## 19. Checklist DEV

La procédure exécutable est dans `docs/checklist-recette-dev-production-ufsc.md`. Toutes les preuves (captures, IDs non personnels, résultats, temps) doivent être annexées au ticket de recette.

## 20. Rollback et décision

1. Avant installation : sauvegarde fichiers + base et hash du ZIP précédent.
2. En incident : maintenance, désactivation du plugin, restauration du ZIP précédent, purge caches.
3. Restaurer la base seulement si une écriture métier erronée est prouvée ; les migrations additives ne suppriment rien et peuvent rester.
4. Vérifier commandes/licences créées pendant la fenêtre avant réouverture ; ne jamais supprimer automatiquement.

**Décision : No-Go production. Candidate prête pour recette DEV après revue, sous réserve de comparer réellement #497 depuis GitHub et d'exécuter la checklist.**
