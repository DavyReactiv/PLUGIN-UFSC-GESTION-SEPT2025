# Recette finale du parcours licences UFSC

## Causes établies et correctifs présents

La cause historique des cases inactives était double : le renderer assimilait un dossier incomplet à un blocage et ajoutait `disabled`, tandis que `frontend-dashboard.js`, qui pilote l'assistant, n'était pas chargé sur le chemin réel. Le renderer sépare désormais `selectable`, `complete`, `cart_eligible`, `blocked`, `block_code` et `block_message`. Une archive renouvelable mais sans niveau/poids produit un input coché possible, sans attribut `disabled`. L'asset est chargé sous le handle `ufsc-renewal-runtime`, versionné par `filemtime()`.

Avant (cause) : `<input type="checkbox" disabled="disabled">` pour un dossier incomplet. Après : `<input id="ufsc-renewal-source-ID" type="checkbox" name="source_ids[]" value="ID" aria-describedby="ufsc-renewal-reason-ID">`; `data-selectable="1"`, `data-complete="0"`, `data-cart-eligible="0"`, `data-blocked="0"` sont portés par la ligne.

Le bouton inactif était une conséquence du runtime JavaScript absent. Il est maintenant un vrai lien, amélioré par JavaScript, avec le repli `?ufsc_section=licences-renouvellement&renew_source_id=ID&target_season=SAISON_COURANTE`. Le serveur valide cible, appartenance et éligibilité, sélectionne la source et rend l'étape 2 sans écrire l'archive.

Le texte bleu vertical n'est produit par aucun markup, `writing-mode` ou contrôle fixe du plugin UFSC inspecté. La cause exacte ne peut donc pas être attribuée au plugin depuis le dépôt ; elle doit être identifiée dans le DOM de DEV (thème/widget externe) avant tout correctif. Aucun masquage global dangereux n'est ajouté. **Ce point impose un No-Go tant que l'inspection DEV n'est pas faite.**

## Scénario front

1. Ouvrir « Mes licences UFSC », appliquer recherche/statut/tri et confirmer que `ufsc_section` reste présent.
2. Choisir 10 licences par page, soumettre « Appliquer », puis vérifier Première/Précédente/Suivante/Dernière et la conservation de la taille.
3. Vérifier le message explicite indiquant que la sélection est limitée à la page courante.
4. Sélectionner Janaelle par la case puis par son libellé, sélectionner une deuxième personne et contrôler le compteur vocalisé.
5. Cliquer « Renouveler » : vérifier l'étape 2, le focus sur la bonne fiche et la conservation de l'autre sélection.
6. Modifier niveau via la liste centrale, poids avec virgule ou point et une coordonnée ; une valeur hors 20–300 kg doit être refusée.
7. Continuer : une seule étape est visible ; l'étape 3 affiche deux lignes nominatives et « quantité 1 ».
8. Ajouter au panier : vérifier deux lignes distinctes, quantité 1, identité, club, saison, filiation et numéro UFSC permanent.
9. Payer sur le moyen de test autorisé, vérifier la nouvelle annualité et comparer l'archive source, qui doit être strictement intacte.

## Archive, brouillon et administration

* Archive front : Consulter → Modifier pour renouveler → Vérifier → Ajouter au panier → Payer. Contrôler lecture seule et filiation.
* Brouillon courant : Consulter → Modifier → relever chaque élément manquant → Vérifier → Ajouter au panier sans créer une seconde annualité.
* Admin : ouvrir `page=ufsc_lc_licences&action=view&licence_id=ID`, puis `action=renew&licence_id=ID&target_season=SAISON`; contrôler le formulaire, le panier et la nouvelle licence. Aucune action éditer/corbeille/valider ne doit apparaître sur l'archive.

## UI, accessibilité et responsive

Tester à 320, 375, 768, 1024, 1280 et 1440 px : cartes mobiles, absence de défilement horizontal desktop, cibles de 44 px, focus visible, clavier, labels, `aria-describedby`, `aria-current`, `aria-live` et zoom 200 %. Le logo doit rester carré avec `object-fit: contain`, sans découpe ni déformation.

Inspecter l'élément bleu parasite dans DEV : capturer tag, id, classes, texte, origine, styles calculés, position et z-index. Corriger uniquement sa source prouvée et refaire les captures desktop/mobile.

## Performance et preuves à joindre à la recette DEV

Capturer Query Monitor (requêtes, doublons, PHP, mémoire), console sans erreur, réseau et taille HTML pour 10/20 lignes. Joindre captures desktop, mobile, panier et Query Monitor. Les tests automatisés prouvent le HTML de la case incomplète, le fallback étape 2, la fenêtre de pagination et le formulaire POST du panier ; ils ne remplacent pas une transaction WooCommerce sur DEV.

## Score honnête hors recette DEV

| Domaine | Avant | Après | Risque restant |
|---|---:|---:|---|
| Logique/multi-saison | 8/10 | 9/10 | données DEV non rejouées |
| Sécurité/filiation | 8/10 | 9/10 | test d'intrusion DEV requis |
| Renouvellement front/panier | 6/10 | 8/10 | paiement réel non exécuté |
| Admin/archives/brouillons | 7/10 | 8/10 | recette rôles/données requise |
| Filtres/pagination | 6/10 | 8/10 | sélection volontairement limitée à une page |
| UI/UX/responsive/accessibilité | 6/10 | 8/10 | captures et audit WCAG DEV requis |
| Performances | 5/10 | 7/10 | SQL du sous-ensemble et Query Monitor à mesurer |
| Tests/documentation | 7/10 | 9/10 | CI distante indisponible localement |

## Décision

**No-Go pour production directe. Prêt pour recette finale DEV.** Le runtime automatisé est vert, mais les captures, le paiement WooCommerce, Query Monitor et l'identification DOM du texte vertical exigés ne sont pas réalisables sans l'instance DEV authentifiée.
