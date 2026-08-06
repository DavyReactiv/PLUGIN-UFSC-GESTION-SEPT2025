# Performance du parcours licences — avant / après

## Périmètre et méthode

Audit du chemin réellement chargé : renderer `UFSC_Frontend_Shortcodes::render_renewal_assistant()`, asset `ufsc-renewal-runtime`, handler `admin_post_ufsc_bulk_renew_licences` et ajout WooCommerce. Aucun chiffre Query Monitor n'est publié ici : l'environnement de livraison ne fournit ni instance WordPress DEV, ni base représentative, ni navigateur authentifié. Les objectifs de temps et de requêtes restent donc à mesurer pendant la recette DEV.

## Constats avant correction

* L'assistant filtrait les archives de la saison précédente, puis rendait **toutes** les lignes renouvelables et un formulaire complet pour chacune. Avec 38 licences, le DOM contenait 38 lignes d'identité et jusqu'à 38 profils volumineux, même cachés.
* Le JavaScript parcourait toutes ces cases et tous ces profils à chaque changement d'étape.
* Les assets du parcours sont déjà limités au portail et versionnés avec `filemtime()` par les handles `ufsc-renewal-runtime` et `ufsc-renewal-style`. Ce comportement est conservé.
* L'admin dispose déjà d'une pagination SQL à 25 lignes par défaut, plafonnée à 50. Elle est conservée.

## Correction livrée

L'assistant applique désormais une pagination contrôlée avant le rendu : 10 licences par défaut, 20 sur choix explicite. Seule la fenêtre courante et ses formulaires sont ajoutés au DOM. Les liens Première, Précédente, Suivante et Dernière conservent la section et la taille. Un lien de repli individuel calcule automatiquement la page contenant la licence, puis ouvre l'étape 2 côté serveur.

La sélection est volontairement limitée à la page courante et ce comportement est annoncé, afin de ne jamais perdre silencieusement une sélection au changement de page. La soumission en masse conserve plusieurs personnes distinctes sur cette page et WooCommerce reçoit toujours une quantité de 1 par personne.

## Avant / après démontrable hors DEV

| Indicateur | Avant | Après | Preuve automatisée |
|---|---:|---:|---|
| Lignes/profils rendus pour 25 sources | 25 | 10 par défaut | test runtime du renderer |
| Tailles proposées | aucune | 10 ou 20 | test runtime + HTML rendu |
| Lien de page suivante | absent | section, page et taille conservées | test runtime |
| Repli direct vers une source en page 3 | toutes les sources rendues | fenêtre de page 3, étape 2 | test runtime |
| Quantité panier | 1 | 1 | tests existants du panier |

## Mesures DEV à relever

Avec Query Monitor, relever pour 10 puis 20 lignes : requêtes totales/dupliquées, temps PHP, mémoire de pointe et appels par composant. Dans le navigateur, relever taille HTML transférée, nombre de nœuds DOM, durée DOMContentLoaded, erreurs console et réseau. Comparer cache froid/chaud sans inventer de résultats. Seuils de décision : moins de 100 requêtes (cible 80), environ 1 s chaud, moins de 2 s froid et aucune erreur console.

## Risques restant à mesurer

Le shortcode reçoit encore la collection d'archives déjà chargée par le tableau principal : la correction réduit fortement HTML et travail JS, mais ne constitue pas encore une pagination SQL dédiée au sous-ensemble de renouvellement. Une mesure DEV décidera si une requête COUNT/LIMIT spécialisée est nécessaire, sans dupliquer le resolver de saison.
