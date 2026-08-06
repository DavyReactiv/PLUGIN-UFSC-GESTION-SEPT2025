# Audit de la résolution de l’affiliation annuelle

## Cause exacte

Le portail et la porte métier appelaient `UFSC_Season_Archive_Manager::get_affiliation()`, mais ce lecteur imposait simultanément la table moderne issue de la classe de migration, la colonne `season`, le format exact `YYYY-YYYY` et `LIMIT 1`. L’administration et les diagnostics disposent déjà de `UFSC_Storage_Resolver`, capable de sélectionner le stockage réellement peuplé. Une installation legacy/hybride utilisant `saison`, `paid_season`, `season_end_year`, `statut`, ou plusieurs annualités pouvait donc être visible en administration mais absente du portail. Le renderer Archives ajoutait une seconde divergence en appelant le booléen historique au lieu d’afficher le résultat structuré de la porte.

## Source canonique après correction

`UFSC_Season_Archive_Manager::resolve_affiliation()` est l’unique lecteur annuel. La table vient de `UFSC_Storage_Resolver::get_annual_affiliations_table()`; le fallback de bootstrap reste `{$wpdb->prefix}ufsc_affiliations_seasons`. La clé club est `club_id` (`id_club` en compatibilité), la clé primaire est `id` (`affiliation_id` en compatibilité), et la saison est lue avec la priorité `season`, `saison`, `paid_season`, `season_end_year`. Les champs reconnus pour la décision sont `status`/`statut`, `payment_status`, `wc_order_id`/`order_id`.

Le resolver ne consulte jamais le statut permanent du club. Il limite strictement la recherche au `club_id` et à la saison cible normalisée. Si plusieurs lignes existent, il préfère `active`/`validated`, puis les états en attente/payables, puis l’identifiant le plus récent. Le nombre de doublons est exposé dans `evidence`.

## Statuts et messages

Les variantes réellement compatibles (`actif`, `valide`, `validée`, `approved`, `approuvé`, `approuvée`) sont normalisées vers `active` ou `validated`. Tous les autres états restent bloqués; `on-hold` n’est jamais actif. Les réponses distinguent absence, paiement, validation, correction, suspension et erreur technique. Une erreur de schéma reste fail-closed sans prétendre que l’affiliation est absente.

## Diagnostic WP_DEBUG

La porte journalise un objet non sensible contenant `user_id`, `club_id`, saison demandée et normalisée, table, colonne, colonnes disponibles, nombre de lignes, doublons, affiliation sélectionnée, statut brut/canonique, paiement, commande, décision et code. Aucun nom, contact, document ou renseignement médical n’est journalisé.

## Cohérence des consommateurs

Le dashboard, la carte Saison, les Archives, l’assistant, la création, le panier, la restauration de session et le checkout utilisent `ufsc_club_can_manage_licences_for_season()`. Les écrans qui ont besoin de la ligne utilisent `get_affiliation()`, désormais simple façade du même resolver. La période d’ouverture de l’affiliation future n’entre pas dans cette porte et ne bloque donc pas les licences de la saison courante.

## Recette MFC

Aucune base réelle n’est disponible dans cet environnement : le `club_id`, la valeur brute de saison, le statut, la commande et le paiement MFC doivent être relevés sur DEV avec WP_DEBUG. Le résultat attendu est `allowed=true`, code `affiliation_active`, carte « Affiliation [saison courante] : Active » et absence de blocage Archives. Cette livraison ne modifie aucune annualité, licence, commande ou donnée réelle.
