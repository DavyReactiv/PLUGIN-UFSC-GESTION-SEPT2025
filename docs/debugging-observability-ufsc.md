# Diagnostic et observabilité UFSC

## Objectif

Pouvoir suivre un bug sans modifier le comportement métier ni ajouter des `error_log()` permanents :

`action utilisateur -> requête -> handler PHP -> décision métier -> BDD / WooCommerce -> résultat -> affichage`.

## Activation sur DEV uniquement

Ajouter temporairement dans `wp-config.php` de l'environnement DEV :

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'UFSC_DEBUG_TRACE', true );
```

Ne pas activer `UFSC_DEBUG_TRACE` en production en fonctionnement normal.

## Trace UFSC

`UFSC_Debug_Trace` produit un identifiant `trace_id` par requête. Les événements de la même requête partagent cet identifiant.

Le contexte doit rester technique :

- `club_id`
- `licence_id`
- `season`
- `action`
- `status_before`
- `status_after`
- `quota_before`
- `quota_after`
- `included`
- `wc_product_id`
- `wc_cart_key`
- `wc_order_id`
- `result`
- `error_code`

Ne jamais journaliser le certificat médical, l'honorabilité, une adresse, un email, un téléphone, un nonce, un token, un mot de passe ou le contenu complet d'un formulaire. Le traceur redige automatiquement les clés sensibles les plus communes, mais les appelants doivent aussi limiter le contexte transmis.

## Où lire les traces

1. Si WooCommerce est actif : Journaux WooCommerce, source `ufsc-trace`.
2. Si Query Monitor est installé : panneau Logs / Debug pour les événements `qm/debug`.
3. Sans WooCommerce : fallback dans le journal PHP WordPress lorsque le tracing est explicitement activé.

## Scénario de diagnostic licence incluse

1. Noter le `licence_id`, le `club_id`, la saison et le compteur quota avant l'action.
2. Ouvrir la fiche brouillon.
3. Cliquer une seule fois sur `Envoyer pour validation`.
4. Relever le `trace_id`.
5. Vérifier successivement :
   - handler reçu ;
   - ownership/capability validés ;
   - intention `submit_for_validation` ;
   - décision quota ;
   - réservation quota ;
   - écriture du statut ;
   - relecture BDD ;
   - redirection/réponse ;
   - état visible front ;
   - état visible admin.
6. Si une étape manque, le bug se situe entre le dernier événement présent et le premier événement absent.

## Scénario de diagnostic 11e licence payante

1. Préparer exactement 10 crédits inclus consommés pour le club et la saison.
2. Finaliser une 11e licence.
3. Vérifier dans le même `trace_id` :
   - quota = épuisé ;
   - décision = payante ;
   - produit WooCommerce résolu côté serveur ;
   - initialisation session/panier ;
   - `add_to_cart` réussi ;
   - clé panier créée ;
   - quantité = 1 ;
   - métadonnées `club_id` / `licence_id` présentes ;
   - panier persistant après rafraîchissement.
4. Aucun crédit inclus ne doit être consommé par cette 11e licence.

## Double clic / soumission répétée

Exécuter le même envoi deux fois et vérifier :

- une seule licence annuelle ;
- un seul crédit pack réservé ;
- une seule ligne panier pour une licence payante ;
- aucune double commande ;
- réponse idempotente ou blocage explicite au second appel.

## Query Monitor

Sur DEV, Query Monitor doit être utilisé pour contrôler en parallèle :

- erreurs PHP ;
- requêtes SQL déclenchées par la demande ;
- requêtes lentes ou dupliquées ;
- hooks/actions exécutés ;
- appels HTTP éventuels ;
- scripts/styles chargés sur les pages Club.

Les captures de recette doivent inclure le résultat utilisateur et, lorsqu'un bug subsiste, l'extrait Query Monitor correspondant au même scénario.

## Xdebug

Xdebug est destiné au DEV local ou à une préproduction contrôlée. Points de rupture prioritaires :

- `UFSC_Unified_Handlers::process_licence_request()` ;
- `ufsc_allocate_pack_credit()` ;
- service de renouvellement ;
- handler panier sécurisé ;
- fonction d'écriture du statut ;
- validation admin.

Inspecter les valeurs, ne jamais les modifier manuellement pendant la recette de référence.

## Désactivation

Après diagnostic :

```php
define( 'UFSC_DEBUG_TRACE', false );
```

Conserver Query Monitor désactivé ou retiré de la production si la politique d'exploitation l'exige. Les traces UFSC ne doivent pas devenir un stockage de données personnelles.
