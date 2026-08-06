# Rapport d’implémentation — phase 1 du portail Club

## Limites de recette

Le dépôt fourni ne contient ni URL DEV, ni compte de recette, ni base WordPress/WooCommerce, ni navigateur authentifié. Les mesures Query Monitor, réseau, DOM calculé et captures ne sont donc **pas vérifiées**. Elles ne doivent pas recevoir 10/10 avant exécution de la matrice ci-dessous.

## Matrice exigences / réalisation

| Exigence | État avant | Correction réalisée | Fichiers | Test / preuve dépôt | Reste à faire en DEV |
|---|---|---|---|---|---|
| Pagination archives | Toutes les lignes étaient chargées puis découpées en PHP | Requête distincte des saisons; aucune requête de lignes sans filtre; `COUNT(*)`, saison, `LIMIT` et `OFFSET` pour 10/20 lignes | `includes/frontend/class-frontend-shortcodes.php` | Suite autonome et inspection SQL préparé | Query Monitor: confirmer le nombre et le plan des requêtes |
| Sélection renouvellement | Cible checkbox fragile | Label natif associé, cible 44 px, focus visible, compteur et bouton synchronisés | `includes/frontend/class-frontend-shortcodes.php`, `assets/css/ufsc-front.css`, `assets/js/frontend-dashboard.js` | Runtime de rendu renouvellement | Clic, label, Espace et multi-sélection dans le navigateur |
| Renouvellement complet | Assistant existant mais non démontré | Trois étapes, profils modifiables, validation niveau/poids, POST nonce, panier nominatif quantité 1, fallback direct et sans JS conservés | mêmes fichiers + `inc/woocommerce/cart-integration.php` existant | Tests runtime renouvellement et panier | Paiement réel en sandbox WooCommerce |
| Actions par état | Panier proposé même avec données essentielles manquantes dans la liste secondaire | Consulter/Modifier conservés; panier seulement si niveau, poids et e-mail présents; motif précis sinon; renouvellement/archive conserve les handlers existants | `templates/frontend/licences-list.php` | Lint et suite | Vérifier chaque statut avec données DEV représentatives |
| Brouillon | Un dépassement de quota pouvait envoyer un brouillon au panier malgré l’intention « save » | Intention normalisée; le brouillon est sauvegardé et redirigé sans mutation panier; panier uniquement après `add_to_cart` explicite | `includes/core/class-unified-handlers.php` | `tests/test-draft-cart-intent-runtime.php` | Créer, reprendre, compléter et payer un brouillon DEV |
| Tableau de bord | Coordonnées et KPI secondaires répétés, en-tête haut | En-tête compact, quatre KPI principaux, coordonnées déplacées vers Compte Club, quatre actions principales visibles | `includes/frontend/class-frontend-shortcodes.php`, `assets/css/ufsc-front.css` | Suite UI | Captures 1440/768/390 et zoom 200 % |
| Compte Club | Mise en page déjà partiellement refondue | Largeur canonique 1280 px conservée, grille responsive, dirigeants/documents/savebar existants conservés, composant logo partagé | `includes/frontend/class-frontend-shortcodes.php`, `assets/css/ufsc-front.css` | Tests Compte Club | Soumission réelle, uploads et maintien de position |
| Ajouter une licence | Formulaire continu | Assistant progressif en 6 étapes, barre de progression, validation par étape, précédent/suivant, récapitulatif, brouillon et panier; fallback sans JS intact | `assets/js/ufsc-license-form.js`, `assets/css/ufsc-licence-form.css`, `includes/frontend/class-frontend-shortcodes.php` | Syntaxe JS/PHP + suite | Navigation et erreurs réelles aux trois largeurs |
| Logo | Images rectangulaires et implémentations distinctes | Composant carré partagé, fallback, `aspect-ratio:1/1`, `object-fit:contain`, aperçu local et contrôles renommés | `includes/frontend/class-frontend-shortcodes.php`, `assets/css/ufsc-front.css`, `assets/js/frontend-dashboard.js` | Test UI statique actualisé | Upload/suppression et netteté sur DEV |
| Texte « raconte nous » | Aucun nœud, texte, `writing-mode` ou widget correspondant dans le dépôt | Audit exhaustif du dépôt documenté; aucun masquage global dangereux ajouté | ce rapport; `docs/performance-recette-admin-front.md` | `rg -ni "raconte|writing-mode"` | **Bloquant DEV:** relever balise, ID/classes, iframe, CSS calculé et script initiateur avant correctif ciblé |
| Performance | Archives jusqu’à 2000 lignes chargées; assets formulaire non cache-bustés | Archives saisonnées SQL; source renouvellement limitée; JS/CSS de formulaire versionnés par `filemtime`; DOM archives absent sans saison | PHP front + enqueue | Suite et lint | Query Monitor avant/après, mémoire, HTML, DOM, console, réseau |

## Matrice de recette navigateur DEV

1. **Renouvellement** : sélectionner deux lignes par checkbox, label et clavier; vérifier compteur; compléter niveau/poids; modifier une donnée; revenir puis avancer; ajouter; contrôler deux lignes nominatives quantité 1; payer en sandbox; comparer l’archive avant/après.
2. **Sans JavaScript** : désactiver JS; utiliser le lien direct signé et la soumission serveur; vérifier nonce, propriété, saison et redirection.
3. **Brouillon** : enregistrer un dossier incomplet; confirmer panier inchangé; rouvrir; compléter; ajouter; confirmer quantité 1 et absence de deuxième commande payable.
4. **Archives** : ouvrir sans saison et contrôler qu’aucune requête de lignes d’archives n’est exécutée; choisir une saison; contrôler `COUNT`, `LIMIT 10/20`, `OFFSET`, URL et pagination; réinitialiser.
5. **Interfaces** : capturer dashboard, compte, assistant, logo et panier en 1440, 768 et 390 px, puis zoom 200 %; parcours clavier complet.
6. **Texte parasite** : inspecter l’élément DEV et exporter le nœud DOM, les styles calculés et l’initiateur; seulement ensuite ajouter un sélecteur ciblé au composant propriétaire.
7. **Performance** : relever Query Monitor, temps PHP/total, mémoire, octets HTML, nœuds DOM, scripts/CSS, AJAX, console et réseau avant/après avec le même club et la même saison.

## Décision

**Prêt pour recette DEV, pas prêt pour production.** Le code traitable depuis le dépôt est implémenté et testé localement; les preuves navigateur, le paiement sandbox, l’attribution du widget externe et les mesures réelles restent obligatoires.
