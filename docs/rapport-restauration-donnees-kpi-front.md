# Rapport technique — restauration données historiques, KPI et front club

## Cause racine
- Les noms de tables configurés pouvaient être interprétés sans `$wpdb->prefix`, ce qui déclenchait une fausse alerte de configuration.
- Les filtres saison de la liste clubs s'appuyaient uniquement sur la table d'affiliations annuelles et masquaient les clubs ayant seulement des licences historiques.
- La normalisation de saison n'acceptait pas les formats historiques tels que `2027` pour une saison finissant en 2027.
- Le résumé front lisait uniquement les noms de colonnes récents, sans aliases historiques (`tel`, `email_contact`, `site_web`, `adresse_siege`).
- La table optionnelle d'attestations pouvait manquer et générer des états dégradés insuffisamment explicites.

## Tables détectées par le diagnostic
Le diagnostic runtime `ufsc_get_configuration_diagnostic()` distingue :
- critiques : clubs, licences ;
- optionnelles : affiliations saisonnières, attestations ;
- état de migration ;
- compteur de lignes si la table existe.

## Requêtes corrigées
### Avant
```sql
SHOW TABLES LIKE 'clubs';
```
Cette vérification échouait lorsque la table réelle était `wp_clubs` ou `wp_ufsc_clubs`.

### Après
```sql
SHOW TABLES LIKE '{$wpdb->prefix}clubs';
```
La résolution passe par les réglages normalisés et le diagnostic structuré.

### Avant
```sql
EXISTS (SELECT 1 FROM affiliations WHERE club_id = clubs.id AND season = '2025-2026')
```
Les clubs avec licences historiques mais sans ligne annuelle étaient masqués.

### Après
```sql
EXISTS (affiliation saison) OR EXISTS (licence de la saison)
```
La contrainte saison ne transforme pas la liste permanente en jointure bloquante.

## Données historiques retrouvées
Les données ne sont ni copiées ni converties. La restauration est une compatibilité de lecture : saisons normalisées, clubs permanents non supprimés, licences saisonnées.

## KPI avant/après
- Avant : les KPI pouvaient tomber à zéro si la table annuelle optionnelle était absente ou si le préfixe était incorrect.
- Après : les KPI principaux ne sont calculés que si les tables critiques existent ; les tables optionnelles produisent une alerte technique sans masquer le dashboard.

## Migrations
- Additives uniquement.
- `dbDelta()` pour `ufsc_affiliations_seasons` et `ufsc_attestations`.
- Aucune suppression, aucun `TRUNCATE`, aucun `DROP COLUMN`.
- Relance idempotente à l'activation et à l'upgrade.

## Risques
- Les installations ayant des noms de tables totalement personnalisés non préfixés doivent vérifier le diagnostic admin.
- Le comptage par licences historiques dépend de la présence d'une colonne saison (`paid_season`, `season`, `saison` ou `season_end_year`).

## Rollback
- Revenir au commit précédent restaure l'ancien comportement.
- Aucune donnée historique n'est modifiée par ce correctif ; seules des tables optionnelles peuvent être créées si absentes.
