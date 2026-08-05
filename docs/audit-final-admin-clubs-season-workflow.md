# Audit P0 — workflow admin Clubs

## Cause racine

Avant correction, un clic depuis une URL telle que
`admin.php?page=ufsc-sql-clubs&season=2026-2027&club_view=permanent&archive_scope=all_historical&kpi_filter=documents_incomplete&paged=3`
pouvait conserver ou recréer plusieurs axes de filtrage. Surtout, la liste ajoutait implicitement la preuve de présence dans la saison courante **après** la condition métier du KPI. Le compteur « dossiers incomplets » comptait les clubs permanents incomplets, tandis que la liste exécutait conceptuellement :

```sql
SELECT * FROM clubs
WHERE non_supprime
  AND dossier_incomplet
  AND EXISTS (preuve_saison_2026_2027)
LIMIT 20 OFFSET 40;
```

Cette intersection non partagée (plus la pagination) expliquait un KPI positif et une liste vide.

## Contrat après correction

Un raccourci est une vue exclusive construite depuis `admin.php`, et non depuis l'URL courante. Exemple après clic :
`admin.php?page=ufsc-sql-clubs&season=2026-2027&kpi_filter=affiliations_active`.
Seuls `page`, la saison, la vue KPI et, lorsqu'il est choisi séparément, `per_page` ont vocation à subsister. `club_view`, ancien `kpi_filter`, `statut`, `affiliation_status`, `doc_status`, `licence_range`, `archive_scope`, `region`, `q`, dates et `paged` ne sont pas hérités.

Les conditions `affiliations_active`, `affiliations_pending`, `renewals`, `documents_incomplete` et `annual_numbers_missing` viennent toutes du helper partagé `get_admin_kpi_filter_condition()`. La requête KPI et la requête liste réutilisent donc la même condition. Une vue KPI ne reçoit plus la condition saisonnière implicite additionnelle. Le diagnostic repliable WP_DEBUG expose les requêtes COUNT/liste, la condition, le total et la dernière erreur SQL aux seuls administrateurs.

## Vues et workflow

- une affiliation active sans numéro appartient à « Actifs » et « Numéros annuels à attribuer » ;
- une demande active ou en attente n'appartient pas aux renouvellements ;
- « Renouveler » ouvre la gestion annuelle sans activation silencieuse ;
- la recherche couvre nom, email, numéro permanent, numéro annuel et ville ;
- la fiche reçoit une URL de retour interne validée pour restaurer filtres et pagination ;
- l'historique sans preuve reste consultable sans qualifier les clubs d'affiliés.

## Rollback

Le correctif ne migre et ne modifie aucune donnée. Le rollback consiste à rétablir les fichiers PHP/CSS et le test de ce commit. Les tables, affiliations, documents, licences, utilisateurs et archives restent intacts.
