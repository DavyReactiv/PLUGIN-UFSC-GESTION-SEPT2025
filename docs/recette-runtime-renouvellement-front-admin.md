# Recette runtime — renouvellement front et admin

## Diagnostic vérifié

Le renderer marquait auparavant la sélection avec `disabled` dès que `renewal_allowed` était faux et le script censé piloter l'assistant (`assets/js/frontend-dashboard.js`) n'était jamais enqueued : seul `assets/frontend/js/frontend.js` était chargé. Le bouton avait donc un lien de repli, mais aucune amélioration JavaScript active sur la page. Le routeur admin lisait `id` et ne connaissait que `view/edit/new`; une URL utilisant `licence_id` ou `action=renew` retombait sur la liste.

Les assets ciblés sont désormais `assets/js/frontend-dashboard.js?ver=<filemtime>` et `assets/css/ufsc-front.css?ver=<filemtime>`. Sous `WP_DEBUG`, la version est écrite dans le journal.

## Front MFC

1. Cocher Janaelle et confirmer que la case reste cochée.
2. Sélectionner plusieurs archives et vérifier le compteur.
3. Cliquer **Renouveler** et vérifier l'URL `?ufsc_section=licences-renouvellement&renew_source_id=ID&target_season=SAISON`.
4. Vérifier que seule l'étape 2 est visible et que le focus arrive sur sa fiche.
5. Renseigner niveau et poids, puis **Enregistrer et continuer**.
6. À l'étape 3, vérifier les noms, le montant et cliquer **Ajouter au panier**.
7. Vérifier une ligne nominative par personne, quantité 1.

## Admin

1. Ouvrir Inès Audigier dans la saison historique.
2. Cliquer **Consulter** et constater la fiche en lecture seule.
3. Cliquer **Renouveler pour la saison courante**.
4. Vérifier `page=ufsc_lc_licences&action=renew&licence_id=ID&target_season=SAISON`.
5. Vérifier la source préremplie et en lecture seule, puis ajouter au panier.
6. Revenir à l'archive et comparer toutes ses valeurs : aucune ne doit avoir changé.

## Contrôle cache et sécurité

Recharger les outils réseau après déploiement et confirmer que les paramètres `ver` CSS/JS correspondent au `filemtime` livré. Tester déconnecté : le POST `ufsc_bulk_renew_licences` doit répondre **Accès refusé**. Aucune migration ni écriture sur une archive ne fait partie de ce parcours.
