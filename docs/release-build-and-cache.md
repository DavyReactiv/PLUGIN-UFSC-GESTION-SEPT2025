# Build installé et purge des caches

Le build public est exposé uniquement comme attribut `data-ufsc-build` du portail. Dans
l’administration UFSC, il est également affiché discrètement sous le titre des paramètres
WooCommerce. Un ZIP produit avec `git archive` développe le SHA court du commit dans le
fichier principal ; une copie Git locale lit `.git/HEAD`.

Après déploiement du ZIP :

1. désactiver puis réactiver le plugin si un cache d’opcode conserve l’ancienne arborescence ;
2. purger le cache de page WordPress, le cache objet et le cache du reverse proxy/CDN ;
3. purger le cache navigateur ou ouvrir une fenêtre privée ;
4. contrôler le SHA dans **UFSC Gestion → WooCommerce** et dans `data-ufsc-build` une fois
   authentifié au portail Club ;
5. contrôler dans l’onglet Réseau que les CSS/JS UFSC portent une version `?ver=` égale au
   `filemtime()` du fichier déployé.

Le build n’est pas ajouté aux pages publiques qui ne rendent pas le portail Club et la
version administrative n’est jamais imprimée côté visiteur anonyme.
