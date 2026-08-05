# Checklist préproduction — UI Compte Club

## Environnements / thèmes
- [ ] Tester avec Astra actif.
- [ ] Tester avec Elementor actif sur la page `/compte-club/`.
- [ ] Confirmer que la largeur utile atteint environ 1200–1280 px sur desktop.
- [ ] Vérifier qu’aucun conteneur de thème ne limite le contenu à 350/600 px.

## Responsive
- [ ] 1440 px : hero horizontal, CTA visible, KPI sur plusieurs colonnes.
- [ ] 1280 px : contenu centré et fluide, aucun grand vide sous le logo.
- [ ] 1024 px : hero adaptable, cartes alignées, pas de débordement.
- [ ] 768 px : sections empilées proprement, KPI en 2 colonnes si lisible.
- [ ] 375 px : CTA pleine largeur, documents en 1 colonne, logo raisonnable.

## Données club
- [ ] Club complet : nom, région, adresse, ville, téléphone, email et site affichés dans la synthèse.
- [ ] Club incomplet : aucune ligne vide inutile dans la synthèse.
- [ ] Numéro d’affiliation affiché si disponible.
- [ ] Le champ ID responsable n’est pas mis en avant comme action utilisateur.

## Affiliation annuelle
- [ ] Affiliation à renouveler : bouton `Renouveler mon affiliation {saison}` visible au-dessus de la ligne de flottaison.
- [ ] Le lien du bouton cible le permalink réel du produit WooCommerce ID 4823.
- [ ] Paiement en attente : bouton `Finaliser mon paiement` vers l’URL de paiement de la commande existante.
- [ ] Affiliation active/validated : le bouton renouveler disparaît et l’état actif est visible.
- [ ] Pending validation : afficher un état d’attente, sans nouvelle demande.
- [ ] Correction required : afficher une action/consigne de correction.

## Documents
- [ ] Documents validés : cartes homogènes avec Voir / Télécharger.
- [ ] Documents manquants : résumé `Documents reçus X / Y` et liste synthétique.
- [ ] Document refusé ou à corriger : motif lisible si disponible.
- [ ] Upload document : champs `*_upload` conservés et fonctionnels.
- [ ] Remplacement document : sélection de fichier puis sauvegarde fonctionne.

## Sauvegarde / médias
- [ ] Modification d’informations club puis sauvegarde.
- [ ] Message succès/erreur visible et accessible.
- [ ] Upload logo.
- [ ] Changement logo.
- [ ] Suppression logo.
- [ ] Les nonces restent présents et valides.

## Accessibilité
- [ ] Navigation clavier sur les ancres, boutons, champs et liens documents.
- [ ] Focus visible.
- [ ] Labels de champs visibles et associés.
- [ ] États compréhensibles sans couleur seule.
- [ ] Test sans JavaScript : les sections restent accessibles via le contenu HTML.

## Non-régression WooCommerce / licences
- [ ] Renouvellement affiliation ajoute le produit canonique 4823 avec quantité 1.
- [ ] Aucune demande doublon en panier ou commande pending/on-hold.
- [ ] Tant que l’affiliation annuelle n’est pas active, renouvellement licence bloqué.
- [ ] Après affiliation active/validated, renouvellement nominatif des licences possible pour la saison courante.
