# Checklist de recette définitive du renouvellement

## Front — club MFC

- [ ] Ouvrir « Licences à renouveler » et confirmer que la saison cible vient du service courant.
- [ ] Cocher Janaelle Vieira malgré un niveau ou un poids manquant; vérifier focus, libellé et maintien de la sélection.
- [ ] Ouvrir le lien individuel avec JavaScript, puis sans JavaScript; contrôler l'ID, la saison et l'étape 2 préremplie.
- [ ] Altérer `target_season` et `renew_source_id` dans l'URL; vérifier qu'aucune source étrangère, bloquée ou de mauvaise saison n'est présélectionnée.
- [ ] Renseigner niveau, poids avec virgule, et une coordonnée; contrôler le résumé des changements.
- [ ] Vérifier qu'un poids texte, inférieur à 20 kg ou supérieur à 300 kg bloque l'étape 3.
- [ ] Sélectionner deux licences; vérifier que les profils et résumés ne se mélangent pas.
- [ ] Ajouter au panier; vérifier deux lignes nominatives distinctes, quantité 1, prix et métadonnées de filiation.
- [ ] Payer sur la passerelle DEV et contrôler la nouvelle annualité, puis comparer l'archive avant/après.

## Administration

- [ ] Ouvrir l'archive d'Inès Audigier avec « Consulter » et confirmer la lecture seule.
- [ ] Vérifier l'absence d'Éditer, Paiement, Corbeille, Annuler et suppression sur toute ligne historique.
- [ ] Lancer l'action contextuelle de renouvellement, vérifier source/cible, formulaire et blocages.
- [ ] Contrôler le lien vers la nouvelle licence, `previous_licence_id` et l'archive intacte.
- [ ] Vérifier les actions normales d'une licence courante.
- [ ] Confirmer 25 lignes par défaut et 50 maximum avec Query Monitor.

## Responsive, accessibilité et intégration

- [ ] Capturer 320, 375, 768, 1024, 1280 et 1440 px sans bouton tronqué ni défilement horizontal obligatoire.
- [ ] Tester clavier, zoom 200 %, lecteur d'écran, focus visible, annonces et une seule étape exposée.
- [ ] Tester sous Astra et Elementor.
- [ ] Identifier dans l'inspecteur l'élément bleu vertical (nœud, classe, extension et CSS calculé). Aucun markup, ID, classe, script ou `writing-mode` correspondant n'est présent dans ce dépôt UFSC; son origine exacte ne peut donc être attribuée sans capture DOM de DEV. Ne pas appliquer de masquage global.
- [ ] Vérifier console sans erreur et réseau sans appel par champ.

## Performance et décision

- [ ] Reporter les chiffres réels dans `performance-licences-dev-avant-apres.md`.
- [ ] Joindre captures, export Query Monitor et preuve panier/commande.
- [ ] Garder la décision **No-Go production** jusqu'à validation de tous les points ci-dessus.
