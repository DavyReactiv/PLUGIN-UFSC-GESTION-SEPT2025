# Recette P0 — submit final du renouvellement

Objectif : vérifier que le clic final du wizard atteint toujours `admin-post.php`, même si un ancien listener JavaScript annule le clic ou l'événement `submit`.

## Scénario DEV

1. Ouvrir le renouvellement d'une licence ancienne avec un dossier initialement incomplet.
2. Compléter les champs obligatoires à l'étape 2.
3. Passer à l'étape 3.
4. Vérifier que le bouton final est activé après complétion, sans rechargement de page.
5. Cliquer une seule fois sur le bouton final.
6. Vérifier dans `debug.log` la présence du POST de renouvellement puis des traces `ufsc_renewal_native_*`.
7. Si le quota est épuisé, vérifier que la nouvelle ligne s'ajoute au panier existant sans supprimer les lignes déjà présentes.
8. Recharger le panier et vérifier que la ligne reste présente.

## Non-régression

- aucun `empty_cart()` ;
- aucune modification de licence historique ;
- aucune modification du quota côté JavaScript ;
- aucune validation métier déplacée vers le navigateur ;
- nonce, club, saison, complétude et règles WooCommerce restent contrôlés côté serveur.
