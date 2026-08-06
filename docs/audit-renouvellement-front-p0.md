# Audit P0 — renouvellement front fonctionnel

## Causes observées

L'assistant initial affichait trois indicateurs visuels mais ne possédait aucun état applicatif : aucune action ne passait de la sélection aux fiches, aucun bouton individuel ne sélectionnait sa source et le seul submit tentait immédiatement le panier. Les fiches étaient imbriquées dans le tableau et le tableau conservait son format desktop sur mobile. La complétude et la sélection étaient présentées comme un seul concept, ce qui rendait l'état incomplet ambigu; les anciennes actions d'archives étaient par ailleurs rendues en lecture seule.

## Correction

Les états sont désormais distincts : `selectable` dépend uniquement de l'autorisation métier contextuelle et du produit configuré; `complete` décrit niveau/poids; l'éligibilité panier reste validée côté serveur. Une licence renouvelable incomplète conserve donc une checkbox active et reçoit le badge « Informations à compléter ». Un blocage métier désactive la checkbox avec une raison reliée par `aria-describedby`.

L'assistant possède trois étapes effectives. Le bouton individuel sélectionne la source et ouvre sa fiche; les actions groupées conservent toutes les valeurs dans le DOM nominatif. Sans JavaScript, toutes les fiches et le submit POST sécurisé restent disponibles. Le handler réel est `admin_post_ufsc_bulk_renew_licences` → `ufsc_handle_bulk_renew_licences()` → `ufsc_add_renewal_sources_to_cart()`.

Le formulaire de vérification reprend les champs d'identité, coordonnées, rôle/pratique, niveau, poids, réductions, identifiants non fédéraux, consentements, assurances, honorabilité, représentant légal et note. Les identifiants UFSC/ASPTT restent non modifiables. Le serveur impose la liste blanche, les champs obligatoires, le niveau, le poids, le club, la saison précédente, l'affiliation, les doublons et la quantité 1 sans mettre à jour l'archive.

Chaque ligne panier contient source/previous, personne, UFSC permanent, saison, niveau, poids, catégorie et modifications autorisées. Le libellé est nominatif. Le paiement confirmé crée toujours une nouvelle annualité; l'archive reste intacte.

## Responsive et accessibilité

À 768 px et moins, les lignes deviennent des cartes avec libellés, checkbox visible et action pleine largeur. Les étapes exposent `aria-current`; les blocages utilisent `aria-describedby`; les erreurs sont proches des champs et annoncées; le focus est déplacé sur la fiche individuelle.

## Limites de recette

La recette WordPress/WooCommerce réelle, MFC, les dimensions 320 à 1440 px et la console navigateur nécessitent l'environnement DEV. Aucun enregistrement réel ni migration n'a été exécuté ici.
