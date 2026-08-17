# Recette P0 DEV v2

Objectif: aucun faux état métier et aucune régression responsive avant fusion production.

## Métier
- Club affilié actif, quota 0/10, licence brouillon: aucun CTA `Ajouter au panier`.
- Le CTA doit indiquer `Envoyer pour validation — inclus dans votre affiliation`.
- Après finalisation incluse: aucune ligne WooCommerce et quota 1/10.
- Quota 10/10: la licence suivante devient payante et seulement elle peut être ajoutée au panier.
- Les compteurs `Licences actives/courantes` excluent brouillons, refusées, archivées et anciennes saisons.

## Mobile 320 / 375 / 390 / 430 px
- Aucun débordement horizontal.
- Aucun texte vertical ou masqué.
- Dirigeants et états quota lisibles sur fond bleu.
- Navigation Compte Club compacte: 2 colonnes, puis 1 colonne <=390 px.
- Le formulaire/informations commence immédiatement sous la navigation.
- Tous les boutons tactiles >=44 px.

## Desktop / tablette
- Navigation Compte Club compacte sur une ligne quand la largeur le permet.
- Formulaire pleine largeur, sans compression par le bloc logo.
- Aucune couleur héritée ne rend les informations illisibles.

## Qualité cible
- PHP/JS/runtime/static: 100% verts.
- Lighthouse cible DEV après déploiement: Performance >=90, Accessibility >=95, Best Practices >=95, SEO >=95.
- Aucun P0/P1 ouvert avant production.
