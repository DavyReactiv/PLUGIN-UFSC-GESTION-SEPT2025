# Performance licences — relevé DEV avant/après

## Changements vérifiables dans le code

- La liste canonique d'administration exécute un `COUNT(*)` séparé et une requête paginée avec `LIMIT`/`OFFSET.
- La taille par défaut est désormais de **25** lignes et toute valeur demandée est plafonnée à **50**.
- L'assistant ne déclenche aucun appel AJAX par champ. Les changements sont calculés dans le navigateur et un seul POST groupé rejoint le handler panier.

## Mesures

Cette copie de travail ne contient ni instance WordPress DEV authentifiée, ni base représentative, ni Query Monitor, ni serveur HTTP. Il serait trompeur d'inventer des valeurs.

| Mesure | Avant | Après | État |
|---|---:|---:|---|
| Requêtes SQL admin | non mesuré | non mesuré | à relever avec Query Monitor |
| Temps cache chaud/froid | non mesuré | non mesuré | à relever sur DEV |
| Mémoire | non mesuré | non mesuré | à relever sur DEV |
| Taille HTML | non mesuré | non mesuré | à relever sur DEV |
| Lignes admin | comportement historique non mesuré | 25 par défaut, 50 maximum dans le code | contrôle navigateur requis |
| Formulaires front | non mesuré | non mesuré | le rendu actuel doit encore être profilé sur un club volumineux |
| Appels AJAX par champ | non mesuré | 0 dans l'assistant | vérifié dans le code |

## Protocole Query Monitor

1. Ouvrir la vue admin courante puis les archives avec 25 lignes.
2. Relever requêtes, doublons, temps, mémoire et taille de réponse à froid puis à chaud.
3. Confirmer qu'une demande `per_page=500` ne rend que 50 lignes.
4. Ouvrir l'assistant d'un club volumineux et relever taille HTML et nombre de fiches.
5. Sélectionner une puis plusieurs personnes et vérifier qu'aucune requête par champ n'est émise.

Tant que ces relevés ne sont pas ajoutés, l'objectif chiffré de performance reste **non démontré**.
