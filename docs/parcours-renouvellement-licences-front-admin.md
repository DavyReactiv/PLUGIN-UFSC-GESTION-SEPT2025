# Parcours multi-saison front et administration

## Club

Dans **Mes licences UFSC**, le club voit successivement la saison courante, les licences de la saison précédente à renouveler et les archives. L'assistant: (1) sélectionne les personnes éligibles, (2) vérifie identité, niveau et poids, (3) ajoute une ligne nominative par personne. Une ligne incomplète est désactivée et expliquée. Le lien Archives utilise une URL locale stable, conserve les paramètres de portail et positionne le focus sur le titre.

Le niveau sportif provient d'une liste centrale filtrable. Il est requis au panier. Le poids accepte point/virgule dans le handler existant, est borné et déclenche recalcul/journalisation; une pesée officielle n'est jamais exposée à ce formulaire. La catégorie est toujours calculée par `UFSC_Category_Repository` pour la saison cible.

## Administration

La liste Licences propose saison, club, région, niveau, poids manquant et état contextuel. Les lignes historiques sont consultables sans édition destructive; leur action mène au renouvellement, à la nouvelle licence ou à une commande payable. La fiche sépare données annuelles, sport/catégorie, documents, paiement et historique grâce aux sections existantes. Le niveau et le poids passent par la sanitation serveur.

## Panier, paiement et statuts

La porte unique d'affiliation est vérifiée au POST, au panier, à la session, au checkout et au traitement payé. Source, club, saison, personne, niveau, poids et quantité sont contrôlés. Les clés uniques empêchent la fusion. Une commande payable existante est réutilisée; les doublons sont ignorés.

`pending` et `on-hold` restent en attente et n'activent rien. Seuls `payment_complete`, `processing` ou `completed` effectivement payés créent l'annualité en `pending_validation`; la validation UFSC reste manuelle. Annulation/échec ne crée ni n'active une autre licence.

## Sécurité, erreurs et rollback

Toutes les mutations exigent POST, nonce, session, propriété du club, affiliation cible, identifiants et données valides. Les redirections sont internes. Les requêtes utilisent `$wpdb->prepare()`. Les archives et la pesée officielle ne sont jamais mises à jour.

Rollback applicatif: désactiver le commit/extension et vider uniquement les paniers de test. Il n'existe aucune migration destructive dans cette livraison. Ne jamais supprimer les lignes annuelles déjà créées sans procédure métier auditée.
