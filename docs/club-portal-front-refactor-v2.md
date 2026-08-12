# Refonte front du portail Club — v2

## Causes racines constatées

- `assets/frontend/css/frontend.css` est chargé avant `assets/css/ufsc-front.css`, mais les deux feuilles définissaient encore les mêmes composants. La règle de focus générique de la première feuille n’était pas bornée au portail.
- Plusieurs générations de règles dans `ufsc-front.css` imposaient successivement des colonnes, rayons, hauteurs minimales et états de focus différents aux mêmes classes.
- La colonne KPI réelle ne représentait qu’environ un tiers du héros, alors que six KPI y étaient forcés sur trois colonnes. Les pistes devenaient trop étroites malgré une grille statiquement « valide ».
- Le portail appliquait `overflow-wrap: anywhere` à tous ses liens. Associé aux pistes `minmax(0, 1fr)`, ce comportement autorisait la découpe visuelle des mots dans le pack et la navigation.
- Des hauteurs minimales historiques de 112, 126 et 128 px forçaient les KPI à conserver du vide, et trois règles `overflow-x: hidden` masquaient les débordements au lieu d’en corriger la cause.
- À 1024 px, une ancienne media query rabattait prématurément le formulaire Compte Club en une seule colonne ; la navigation passait au contraire trop tôt à six colonnes.
- La zone logo restait une pile verticale centrée, avec un aperçu pouvant atteindre 140 px et des actions placées dessous.

## Comparaison avant / après

| Surface | Avant | Après |
| --- | --- | --- |
| Héros du tableau de bord | KPI répartis sur trois pistes étroites dans une colonne secondaire ; hauteurs minimales concurrentes | Deux pistes KPI lisibles, alignement en haut, hauteur uniquement pilotée par le contenu |
| Pack d’affiliation | Trois pistes sans largeur minimale, texte susceptible d’être cassé arbitrairement | Cartes de 220 px minimum, trois colonnes desktop, deux tablette, une mobile, valeurs et explications séparées |
| Navigation Compte Club | Breakpoints concurrents et risque de mots découpés | Six colonnes à partir de 1200 px, trois de 768 à 1199 px, deux sous 768 px et une sous 480 px |
| Formulaire Compte Club | Repli en une colonne dès 1024 px ; cartes étirées par une hauteur héritée | Deux colonnes sur desktop et tablette, une colonne mobile, cartes et champs pilotés par leur contenu |
| Logo | Empilement vertical avec aperçu jusqu’à 140 px | Grille compacte aperçu/actions, aperçu de 112 px (96 px mobile), image en `object-fit: contain` |
| Focus et cascade | Deux contrats de focus concurrents, dont un correctif tardif | Contrat canonique unique gagnant : contour bleu foncé et halo blanc |
| Débordement | Trois `overflow-x: hidden` dans le portail | Aucun masquage horizontal ; les tableaux larges gardent leur conteneur de défilement dédié |

## Contrat responsive vérifié statiquement

- 360 px : une colonne pour le pack, les cartes Compte Club et la navigation ; actions pleine largeur.
- 768 px : trois onglets par ligne, deux cartes Compte Club et deux cartes du pack par ligne.
- 1024 px : trois onglets par ligne, deux cartes Compte Club et deux cartes du pack par ligne.
- 1440 px : six onglets, deux cartes Compte Club, trois cartes du pack et largeur de portail plafonnée à 1280 px.

Les cibles interactives restent à 44 px minimum. `word-break: break-all` et `overflow-x: hidden` sont interdits par le test de régression ajouté.

## Validation et limites

- `tests/run-tests.sh` : 70 scripts autonomes réussis.
- Trois tests PHPUnit sont détectés, mais `vendor/bin/phpunit` n’est pas présent dans cet environnement.
- Les contrats de contraste existants passent : texte, actions, états désactivés et focus.
- Le navigateur intégré n’est pas disponible dans cette session. Aucune validation visuelle WordPress/Astra/Elementor ni capture DEV n’est donc revendiquée ; la matrice responsive ci-dessus est une vérification de structure et de cascade, pas une recette visuelle réelle.
- Aucun gestionnaire métier, permission, route REST, panier, renouvellement, saison ou comportement WooCommerce n’a été modifié.
