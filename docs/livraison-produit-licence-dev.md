# Livraison DEV — produit Licence UFSC

## Cause de `product_id = 0`

La source canonique est l'option WordPress `ufsc_woocommerce_settings`, clé
`product_license_id`. Sa valeur par défaut est volontairement `0`. Ainsi, une
installation dont ce réglage n'a jamais été enregistré résout exactement zéro.
Le code ne doit pas masquer cette absence en choisissant le premier produit du
catalogue. Un produit supprimé reste un ID configuré mais le diagnostic le
classe « introuvable » ; un brouillon, un produit privé, sans prix ou non
achetable est également refusé. Les variations sont acceptées uniquement si
l'ID de variation est celui explicitement sélectionné et si WooCommerce la
considère publiée et achetable. Le filtre `ufsc_licence_product_id` constitue
l'unique extension documentée pour un provisionnement externe.

## Configuration

1. Dans WooCommerce, créer ou ouvrir le produit (ou la variation) Licence UFSC,
   renseigner son prix, le publier et vérifier qu'il est achetable.
2. Ouvrir **UFSC Gestion > Paramètres WooCommerce**.
3. Dans **ID du produit licence additionnelle**, sélectionner nominativement le
   produit affiché avec son ID et son statut, puis enregistrer.
4. Vérifier que le diagnostic indique « produit publié et achetable ». Dans le
   cas contraire, corriger la cause affichée ; ne remplacer l'ID par aucun
   produit arbitraire.

## Recette DEV restant obligatoire

- purger le cache WordPress/CDN puis contrôler `data-ufsc-build` et les versions
  `?ver=` CSS/JS dans le HTML réellement servi ;
- tester clavier, mobile et contraste avec le thème actif ;
- sélectionner une licence incomplète, compléter chaque champ et vérifier les
  trois étapes jusqu'au panier ;
- effectuer un double-clic réel et confirmer une seule ligne nominative de
  quantité 1 dans WooCommerce ;
- vérifier Première/Précédente/numéros/Suivante/Dernière avec tous les filtres ;
- tester successivement produit absent, brouillon, supprimé, variation puis
  produit simple publié ;
- contrôler les six étapes du formulaire de création et la persistance du
  brouillon dans la base DEV.
