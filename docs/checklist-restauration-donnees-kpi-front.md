# Checklist préproduction — restauration données historiques, clubs, licences et rattachements

## Audit lecture seule
- [ ] Ouvrir **UFSC Gestion → Diagnostic stockage** et confirmer le mode `legacy`, `hybrid` ou `modern`.
- [ ] Relever les tables trouvées pour clubs, licences, affiliations et attestations.
- [ ] Noter les compteurs avant migration : clubs, licences, affiliations, saisons, utilisateurs liés, documents.
- [ ] Vérifier que l'inventaire liste les colonnes `club_id`, `user_id/responsable_id`, saison, statut et suppression logique.

## Admin
- [ ] Le dashboard n'affiche plus de fausse alerte bloquante si des tables historiques compatibles contiennent clubs/licences.
- [ ] Les KPI s'affichent en mode compatibilité historique.
- [ ] « Tous les clubs permanents » retourne tous les clubs non supprimés logiquement.
- [ ] « Saison précédente : 2025-2026 » retrouve les clubs par affiliation annuelle ou licences historiques.
- [ ] « Clubs à renouveler / anciens clubs » affiche les clubs sans affiliation annuelle active courante mais avec historique.
- [ ] Les vues Actifs, En attente, Documents incomplets, Sans numéro, Moins de 10 licences et Sans licence restent cohérentes.
- [ ] La liste Licences affiche saison courante, saison précédente, archives et toutes saisons.
- [ ] Les exports respectent les filtres et ne masquent pas les historiques.

## Front `/compte-club/`
- [ ] Un utilisateur historiquement rattaché retrouve son club et ne voit plus « Aucun club associé ».
- [ ] Le rattachement persiste même si l'affiliation annuelle est inactive ou absente.
- [ ] Le résumé affiche nom, logo, région, adresse, code postal, ville, téléphone, email, site, numéro historique et état annuel.
- [ ] Les alias historiques (`tel`, `email_contact`, `site_web`, `adresse_siege`, etc.) sont lus sans écriture en base.
- [ ] Les documents, dirigeants, attestations et archives restent consultables.
- [ ] Les nouvelles licences restent bloquées sans affiliation annuelle `active`/`validated`.
- [ ] Les clubs actifs peuvent créer des licences.

## Migration additive éventuelle
- [ ] Lancer d'abord une simulation, jamais une exécution silencieuse.
- [ ] Vérifier que les comptages avant/après ne diminuent pas.
- [ ] Confirmer qu'aucun ID club/licence/utilisateur n'est recréé ou écrasé.
- [ ] Conserver les sources legacy en fallback pendant la transition.
