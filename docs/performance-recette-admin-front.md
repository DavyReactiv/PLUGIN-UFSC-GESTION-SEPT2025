# Recette administration/front et performance — renouvellement final

## Statut de la preuve

Ce dépôt local ne contient ni URL DEV, ni identifiants, ni base WordPress/WooCommerce, ni Query Monitor exploitable. Aucune donnée de production n'a été modifiée et aucune valeur de performance n'est inventée. Les mesures navigateur et Query Monitor restent donc **à relever sur DEV** avant un Go production.

## Vérifications reproductibles dans le code

- La liste admin exécute un `COUNT(*)`, puis une requête paginée `LIMIT/OFFSET`; la valeur par défaut est 25 et la borne maximale 50.
- Le premier rendu front contient une ligne compacte par archive. Un seul formulaire groupé transporte les profils sélectionnés; aucun appel AJAX par champ n'est effectué.
- Le panier reçoit chaque source avec une quantité explicite de 1 et une identité de ligne UUID. Le handler conserve les valeurs en transient lorsqu'une source échoue.
- L'archive est protégée aussi contre une URL `action=edit` saisie directement: cette route rend désormais la fiche en lecture seule.

## Matrice Query Monitor à compléter sur DEV

| Écran/scénario | SQL | PHP | Mémoire | HTML | Lignes/formulaires | AJAX | Console | Panier |
|---|---:|---:|---:|---:|---:|---:|---|---:|
| Admin, page 1, cache froid | non mesuré | non mesuré | non mesuré | non mesuré | 25 attendues | n/a | non vérifiée | n/a |
| Admin, page 1, cache chaud | non mesuré | non mesuré | non mesuré | non mesuré | 25 attendues | n/a | non vérifiée | n/a |
| Front, sélection vide | non mesuré | non mesuré | non mesuré | non mesuré | 1 formulaire groupé attendu | 0 attendu | non vérifiée | n/a |
| Front, deux sources → panier | non mesuré | non mesuré | non mesuré | non mesuré | 2 profils ciblés | 0 attendu | non vérifiée | non mesuré |

Pour chaque ligne, joindre export Query Monitor, capture Réseau, capture Console et taille du document HTML. Contrôler spécifiquement l'absence de répétition par licence des requêtes clubs, affiliations, renouvellements et commandes.

## Élément bleu vertical

La recherche exhaustive des sources UFSC ne trouve aucun `writing-mode`, libellé vertical, widget latéral fixe ou script d'accessibilité injecté par ce plugin. Le seul bleu latéral intentionnel du tunnel est une bordure décorative de résumé, dans le flux normal; ce n'est pas un widget fixe. L'élément signalé doit donc être attribué depuis le DOM calculé de DEV (tag, id, classes, iframe éventuelle, feuille CSS, script initiateur, `position` et `z-index`). Sans cette inspection, masquer globalement un plugin ou une classe serait dangereux et est expressément exclu.

## Recette navigateur obligatoire

1. Avec une archive de test non réelle, ouvrir **Consulter**, tenter une URL `action=edit`, puis vérifier l'absence de formulaire POST et de bouton Modifier.
2. Depuis le compte du club, sélectionner un dossier incomplet, avancer aux étapes 2 et 3, modifier niveau/poids/coordonnée et ajouter au panier.
3. Vérifier deux lignes nominatives, quantité 1, `previous_licence_id`, puis confirmer en base de test que l'archive est inchangée.
4. Capturer 320, 375, 768, 1024, 1280 et 1440 px, Console, Réseau et Query Monitor.
5. Inspecter l'élément bleu dans l'onglet Elements avant toute correction externe.

## Décision

**No-Go production tant que la recette DEV, les captures et les mesures ci-dessus ne sont pas jointes.** Le code local est candidat à cette recette, pas une preuve de fonctionnement de l'environnement DEV.
