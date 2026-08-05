# Rapport technique — restauration données historiques, rattachements et archives

## Cause racine
- Le code pouvait vérifier des tables configurées nues (`clubs`, `licences`) sans résoudre les tables historiques réellement présentes (`wp_ufsc_clubs`, `wp_ufsc_licences`, variantes legacy), d'où la fausse alerte « tables critiques absentes ».
- Le rattachement front dépendait surtout de `responsable_id`, alors que les installations historiques peuvent utiliser `user_id`, `owner_id`, `contact_user_id`, `wp_user_id` ou des métadonnées utilisateur.
- Les filtres saison utilisaient des comparaisons trop strictes et ne reconnaissaient pas tous les anciens formats (`2025/2026`, `2026`, `season_end_year`).
- Les tables optionnelles modernes ne doivent pas bloquer la consultation des clubs, licences et archives legacy.

## Adaptateur central
`UFSC_Storage_Resolver` résout en lecture seule :
- table clubs ;
- table licences ;
- table affiliations annuelles ;
- mode `legacy`, `hybrid` ou `modern` ;
- inventaire détaillé des tables UFSC ;
- rattachement utilisateur → club compatible.

## Tables attendues / trouvées
Le diagnostic admin affiche pour chaque source :
- TABLE ATTENDUE / TABLE TROUVÉE ;
- COMPATIBILITÉ ;
- NOMBRE DE LIGNES ;
- ACTION RECOMMANDÉE.

## Requêtes corrigées
### Avant
```sql
SHOW TABLES LIKE 'clubs';
```
Pouvait échouer alors que `wp_ufsc_clubs` contenait les données.

### Après
```sql
SHOW TABLES LIKE %candidate_from_storage_resolver%;
```
Le resolver teste les candidates configurées, modernes et legacy, puis choisit la table compatible avec données.

### Avant
```sql
EXISTS (SELECT 1 FROM affiliations WHERE club_id = clubs.id AND season = '2025-2026')
```
Masquait les clubs historiques ayant seulement des licences de cette saison.

### Après
```sql
EXISTS (affiliation saison normalisée) OR EXISTS (licence saison normalisée)
```
La contrainte saison n'élimine plus les clubs permanents sans ligne annuelle.

## Rattachements utilisateurs
Priorité de résolution :
1. colonnes explicites du club (`responsable_id`, `user_id`, `owner_id`, `contact_user_id`, `wp_user_id`) ;
2. métadonnées utilisateur (`ufsc_club_id`, `club_id`, `ufsc_user_club_id`, `_ufsc_club_id`) ;
3. email en diagnostic seulement, jamais comme rattachement automatique silencieux.

## Migrations
Aucune migration destructive n'est ajoutée dans ce hotfix. Les outils de diagnostic sont en lecture seule et les actions de migration sont affichées désactivées jusqu'à recette.

## Risques
- Une installation avec plusieurs tables legacy remplies doit confirmer via diagnostic quelle table est source de vérité.
- Les correspondances email nécessitent validation administrative avant écriture éventuelle.

## Rollback
Revenir au commit précédent restaure l'ancien comportement. Ce hotfix ne supprime aucune donnée et ne recrée aucun club, utilisateur ou licence.
