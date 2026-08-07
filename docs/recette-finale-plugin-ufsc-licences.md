# Recette finale du parcours licences UFSC

## Causes établies et correctifs présents

La cause racine encore présente était dans `UFSC_Frontend_Shortcodes::render_renewal_assistant()` : `selectable` exigeait à tort `$product_id` et `blocked` devenait vrai lorsque le produit WooCommerce n'était pas résolu. Sur une installation où ce produit vaut `0`, les 10 lignes renouvelables rendaient donc 10 inputs `disabled` et le compteur JavaScript, qui comptait les inputs désactivés, affichait « 10 bloquées ». L'absence de poids, niveau ou e-mail n'est pas une cause de blocage. Le renderer sépare désormais `selectable`, `complete`, `cart_eligible`, `blocked`, `block_code` et `block_message`. Une archive renouvelable mais sans niveau/poids produit un input coché possible, sans attribut `disabled`. L'asset est chargé sous le handle `ufsc-renewal-runtime`, versionné par `filemtime()`.

Avant, avec produit non résolu : `selectable=false`, `complete=false`, `cart_eligible=false`, `blocked=true`, donc `<input type="checkbox" disabled="disabled">`. Après, pour la même archive incomplète : `selectable=true`, `complete=false`, `cart_eligible=false`, `blocked=false`. Le HTML runtime obtenu pour l'identifiant 42 est `<label class="ufsc-renewal-selection-control" for="ufsc-renew-42"><input id="ufsc-renew-42" class="ufsc-renew-checkbox ufsc-renewal-checkbox" type="checkbox" name="ufsc_renew_ids[]" value="42" aria-describedby="ufsc-renewal-reason-42"><span class="screen-reader-text">Sélectionner Test Janaelle</span></label>` ; il ne contient pas `disabled`. La raison affichée est « Sélectionnable : complétez le dossier à l’étape suivante. » Le lien d'action est une URL réelle avec `renew_source_id=42` et `target_season=2026-2027`. Les attributs `data-selectable="1"`, `data-complete="0"`, `data-cart-eligible="0"`, `data-blocked="0"` sont portés par la ligne.

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

## Matrice P0 de recette

| Scénario | Preuve automatisée locale | Résultat / recette DEV restante |
|---|---|---|
| Incomplète sans poids/niveau, même produit absent | HTML PHP réel : input sans `disabled`, décisions `1/0/0/0`, fallback étape 2 | Conforme localement ; clic visuel navigateur DEV à capturer |
| Compteur et Continuer | délégation `change`, recalcul depuis `:checked`, bouton désactivé seulement à zéro | À confirmer visuellement sur DEV (0 → 1) |
| Renouveler | URL serveur réelle et amélioration JS qui coche, déclenche `change`, ouvre et focalise la fiche | Fallback PHP réel conforme ; focus navigateur à confirmer |
| Brouillon incomplet | intention POST `save_draft`, transient uniquement, sortie avant tout appel panier | Conforme par séparation du handler ; requête DEV à tracer |
| Ajout après complétion | validation nominative serveur puis un unique `add_to_cart()` par identifiant dédupliqué | Mutation simulée conforme ; mutation WooCommerce DEV réelle requise |
| Déjà renouvelée / demande en cours / panier | `UFSC_Renewal_Service::season_context_status()` et contrôle répété à la frontière panier | Couvert par règles runtime existantes ; données DEV à rejouer |
| Autre club | comparaison serveur stricte `source->club_id !== club_id` | Refus serveur conforme ; test authentifié DEV à rejouer |
| JavaScript désactivé | POST `verify` conserve sélection/profil puis redirige vers `ufsc_renew_step=2`; les trois étapes ont de vrais submits | HTML/fallback conforme ; parcours WooCommerce DEV sans JS requis |

## Décision

**No-Go pour production directe. Prêt pour recette finale DEV.** Le runtime automatisé est vert, mais les captures, le paiement WooCommerce, Query Monitor et l'identification DOM du texte vertical exigés ne sont pas réalisables sans l'instance DEV authentifiée.
