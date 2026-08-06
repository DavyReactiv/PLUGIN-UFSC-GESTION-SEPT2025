# Checklist préproduction — identifiants et renouvellements

Utiliser exclusivement un clone anonymisé. Noter pour chaque étape l’utilisateur, l’heure, le club, la saison, la commande et le résultat du journal.

## Club MFC
- [ ] Ouvrir MFC, contrôler l’affiliation 2026-2027 `active`/`validated`, paiement et commande.
- [ ] Générer l’UFSC; double-cliquer puis recharger : une valeur persistante, bouton disparu, audit présent.
- [ ] Enregistrer un ASPTT distinct; vérifier refus d’un doublon et de `UFSC-*`, puis listes, exports et KPI.

## Trois licences MFC
- [ ] Contrôler saison source, identité et générer trois UFSC distincts et persistants.
- [ ] Saisir un ASPTT sur une source; renouveler deux personnes ensemble.
- [ ] Vérifier deux lignes nominatives, deux clés panier, quantité 1, noms et saison cible.
- [ ] Après paiement vérifier `pending_validation`, filiation, même UFSC, ASPTT/ancienne commande/paiement/documents expirés absents, niveau conservé, catégorie recalculée.
- [ ] Valider en admin; vérifier archives intactes, liens précédent/suivant et second renouvellement refusé.

## Affiliation inactive
- [ ] Sur un clone, suspendre l’affiliation et vérifier le blocage front, brouillon, ajout/restauration panier, checkout, paiement, commande et validation admin.
- [ ] Réactiver et rejouer le parcours complet.

## Concurrence
- [ ] Deux onglets et double clic sur génération club puis licence : une attribution par entité.
- [ ] Deux renouvellements et ajouts panier simultanés : une demande/commande, quantité 1, refus et audit dédupliqué.

## Import ASPTT
- [ ] Prévisualiser sans écriture un CSV puis XLSX valide.
- [ ] Tester doublon, entité inconnue, `UFSC-*` dans ASPTT et fichier avec nom seul : lignes et raisons visibles, aucune écriture partielle.
- [ ] Confirmer un import valide; vérifier rapport exportable, valeur et audit.

## Livraison / rollback
- [ ] Sauvegarde base et fichiers; migration répétée sur bases vide, legacy, hybride et déjà migrée.
- [ ] Contrôler qu’aucun numéro n’est généré et qu’aucune archive n’est modifiée.
- [ ] Restaurer la version précédente sans retirer les colonnes/tables additives; vérifier l’ancien parcours.
