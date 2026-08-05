# Checklist préproduction — restauration données, KPI et front club

## Admin
- [ ] Le tableau de bord UFSC Gestion affiche la saison courante calculée (`2026-2027` au 5 août 2026).
- [ ] Aucune fausse alerte « tables non configurées » si les tables clubs et licences existent.
- [ ] Le diagnostic administrateur liste les tables critiques, les tables optionnelles, la version de migration et les compteurs non sensibles.
- [ ] Les clubs permanents historiques sont visibles dans « Clubs permanents ».
- [ ] La saison courante est visible et filtrable.
- [ ] La saison précédente `2025-2026` restitue les clubs ayant affiliation annuelle ou licences de cette saison.
- [ ] Les clubs à renouveler / anciens clubs sont visibles sans affiliation annuelle active courante.
- [ ] Les licences historiques restent visibles dans leurs filtres de saison.
- [ ] Les exports CSV/XLSX respectent les filtres actifs.
- [ ] Les filtres sont réinitialisables et ne se neutralisent pas avec des valeurs vides.

## Front `/compte-club/`
- [ ] Le logo ou la photo club s'affiche dans une zone compacte.
- [ ] Les actions remplacer/supprimer le logo restent disponibles.
- [ ] Le résumé club est visible avant les formulaires.
- [ ] L'adresse, le code postal et la ville s'affichent si disponibles.
- [ ] Le téléphone s'affiche avec compatibilité `telephone` / `tel` / `tel_mobile`.
- [ ] L'email s'affiche avec compatibilité `email` / `email_contact`.
- [ ] Le site s'affiche avec compatibilité `url_site` / `site_web`.
- [ ] La région s'affiche si disponible.
- [ ] Le numéro, la saison et le statut d'affiliation annuelle s'affichent sans utiliser le statut permanent comme substitut.
- [ ] Le bloc attestation UFSC affiche les états disponible / préparation / indisponible / erreur sans bouton invalide.
- [ ] Les dirigeants restent visibles et modifiables.
- [ ] Les documents restent visibles, téléversables et supprimables selon permissions.
- [ ] La sauvegarde du formulaire conserve les champs non soumis.
- [ ] Le rendu est responsive sans débordement horizontal.

## Métier
- [ ] Une licence est bloquée sans affiliation annuelle active/validated pour la saison cible.
- [ ] Une licence est autorisée avec affiliation annuelle active/validated pour la saison cible.
- [ ] Une affiliation active uniquement sur une saison précédente ne débloque pas les licences courantes.
- [ ] Le renouvellement d'affiliation utilise le produit WooCommerce `4823` et son permalink réel.
- [ ] Un paiement pending/on-hold propose « Finaliser mon paiement ».
- [ ] Le produit 4823 force la quantité 1 et bloque les doublons panier/saison.
- [ ] L'historique des clubs, affiliations et licences n'est pas modifié par la consultation.
