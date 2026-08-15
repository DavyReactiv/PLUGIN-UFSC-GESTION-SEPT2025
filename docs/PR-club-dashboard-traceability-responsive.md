# Compte Club — traçabilité, saison et responsive

Cette PR regroupe les corrections validées après recette DEV du Compte Club.

## Contrat métier

- Les KPI du tableau de bord sont strictement rattachés à la saison active.
- Les statistiques démographiques (sexe, âge, pratique) utilisent uniquement les licences validées de la saison sélectionnée.
- Les saisons précédentes restent consultables dans les archives et ne sont jamais réécrites pour fabriquer une date ou une saison.
- Une date historique inconnue reste explicitement inconnue : aucune date actuelle n'est injectée rétroactivement.
- Les nouvelles licences conservent une date de création immuable ; la validation admin dispose d'une date et de l'identité du validateur quand le schéma le permet.
- L'affiliation annuelle conserve created_at/requested_at/paid_at/validated_at/validated_by sans écraser created_at lors des mises à jour.

## UX

- Le statut d'affiliation est dynamique : active, en attente, renouvellement en cours ou renouvellement disponible selon l'état réel du dossier.
- Une affiliation déjà active n'affiche pas d'appel au renouvellement inutile.
- Le dashboard indique directement les rôles du bureau et les documents à compléter, avec des liens vers les sections concernées.
- Le Compte Club doit être utilisable sans débordement horizontal sur smartphone : cartes, formulaires, logo, URLs et navigation se replient dans le viewport.

## Non-régression

- aucune modification destructive des licences historiques ;
- aucune attribution automatique de dates aux anciennes lignes ;
- conservation du filtrage saison et des archives ;
- conservation du parcours 10 licences incluses / 11e payante issu de la PR précédente.
