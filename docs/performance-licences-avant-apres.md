# Performance licences — avant / après

| Mesure | Avant (recette communiquée) | Après vérifiable dans le code | Mesure DEV restante |
|---|---:|---:|---|
| Lignes admin | 996 | 25 par défaut, 50 maximum | Compter les `<tr>` avec Query Monitor |
| SQL liste | ~392 page observée | 1 `COUNT(*)` + 1 `SELECT … LIMIT/OFFSET` pour la liste | Total page cible < 80 |
| Génération | ~1,9 s | Charge principale bornée | cache chaud < 1 s, froid < 2 s |
| Mémoire | ~33,9 Mo | DOM et calculs de lignes bornés | relever pic réel |
| Formulaires front | jusqu'à plusieurs profils | uniquement les archives de la saison source; profils révélés selon sélection | mesurer HTML et requêtes sur MFC |

La pagination est effectuée dans SQL, avant rendu; aucune tranche PHP des 996 lignes n'est utilisée. Les valeurs `per_page` sont une liste blanche 25/50. Les index n'ont pas été modifiés: sans plan `EXPLAIN` de la base DEV, ajouter un index aurait été spéculatif. À vérifier de façon non destructive: index composé saison/statut/club et index de filiation `previous_licence_id`/`person_identifier` selon les noms de colonnes réellement déployés.
