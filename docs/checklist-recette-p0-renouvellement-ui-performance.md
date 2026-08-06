# Checklist recette DEV P0

> Cette checklist doit être exécutée sur DEV; elle n'est pas une preuve de recette déjà réalisée.

## Admin
- [ ] Ouvrir la saison précédente: badge historique et aucune action Modifier/Paiement/Corbeille/Annuler.
- [ ] Consulter une archive, lancer « Renouveler pour [saison] », vérifier la source et la nouvelle annualité.
- [ ] Vérifier les états déjà renouvelé, demande ouverte et commande payable.
- [ ] Vérifier 25 lignes par défaut, option 50 et pagination SQL Query Monitor.

## Front MFC
- [ ] Sélectionner une licence incomplète: checkbox active, motif « Informations à compléter ».
- [ ] Tester le lien Renouveler avec JS puis sans JS; vérifier l'étape 2 et le focus.
- [ ] Compléter identité, date de naissance, sexe, adresse, niveau Débutant puis Pro et poids décimal avec virgule.
- [ ] Étape 3: vérifier résumé, soumettre, notice, ligne nominative, quantité 1 et métadonnées de saison/filiation.
- [ ] Payer sur un moyen de test; confirmer que BACS/on-hold n'active pas la licence.
- [ ] Confirmer par comparaison SQL en lecture seule que l'archive source est intacte.

## Documents, accessibilité et responsive
- [ ] Questionnaire majeur/mineur et note d'honorabilité visibles avec Astra et Elementor.
- [ ] Parcours clavier, focus, lecteur d'écran, contraste et zoom 200 %.
- [ ] Captures 375 px, 768 px, 1440 px, admin et panier; aucune erreur console.

## Performance et livraison
- [ ] Relever requêtes, temps, mémoire, taille HTML, scripts et AJAX à froid/chaud.
- [ ] Confirmer <80 requêtes, <1 s chaud, <2 s froid raisonnable et aucun N+1.
- [ ] Joindre état CI et captures à la PR avant toute décision de production.
