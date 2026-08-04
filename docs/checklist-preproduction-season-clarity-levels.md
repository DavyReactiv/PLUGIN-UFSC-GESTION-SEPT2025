# Checklist de préproduction — saisons, clubs et niveaux

## Licences

- [ ] Un seul tableau sur la liste principale; aucun tableau après ajout, consultation ou édition.
- [ ] Filtres saison actuelle, précédente, toutes les saisons, archives et saisons historiques.
- [ ] Recherche, région, club, statut, paiement, doublon, visibilité, pagination et actions groupées.
- [ ] Ajout et édition : niveau sportif visible; ancienne valeur vide affichée « Non renseigné ».
- [ ] Mineur : Assaut uniquement; majeur : Classe C/B/A ou Vétéran; erreurs serveur explicites.
- [ ] Renouvellement : valeur reprise et modifiable, `previous_licence_id` conservé, ancienne ligne inchangée.
- [ ] Export filtré par saison, archives et niveau; colonne Niveau sportif contrôlée.

## Clubs

- [ ] Saison sélectionnée visible; filtre conservé lors de recherche, pagination et export.
- [ ] « Club permanent : Enregistré » distinct de « Affiliation {saison} ».
- [ ] Numéro, statut, licences et documents correspondent à la saison.
- [ ] KPI permanents/annuels clairement libellés et recalculés lors du changement de saison.
- [ ] CSV et XLSX, filtres rapides et pagination respectent la saison.

## Responsive et modes navigateur

- [ ] Largeurs 1440, 1280, 1024, 768 et 375 px.
- [ ] Parcours complet avec JavaScript activé.
- [ ] Parcours complet avec JavaScript désactivé (validation serveur obligatoire).
- [ ] Plugin Licence & Compétition actif : menus, callbacks et pages sans doublon ni erreur fatale.
