# Identifiants UFSC / ASPTT et renouvellements

## Modèle canonique

L'identité permanente et l'enregistrement annuel sont deux concepts différents. Un club conserve `numero_affiliation_ufsc`; une personne conserve `numero_licence_ufsc`. Une affiliation ou licence annuelle est une nouvelle ligne liée à une saison. Les numéros ASPTT sont des valeurs partenaires distinctes, saisies ou importées exclusivement par un administrateur.

| Concept | Canonique | Lecture legacy | Règle |
|---|---|---|---|
| Club UFSC | `numero_affiliation_ufsc` | `num_affiliation`, `numero_affiliation` | permanent, généré |
| Club ASPTT | `numero_affiliation_asptt` | aucun alias ambigu | administrateur |
| Personne UFSC | `numero_licence_ufsc` | `numero_licence`, `num_licence`, `licence_number` | permanent, généré/réutilisé |
| Licence ASPTT | `numero_licence_asptt` | `numero_licence_delegataire`, `source_licence_number` | annuel/partenaire |

Les alias restent lisibles mais ne sont ni supprimés, ni utilisés pour copier une valeur UFSC vers ASPTT (ou inversement).

## Attribution et unicité

`UFSC_Identifier_Service` est l'unique générateur. Les formats par défaut sont `UFSC-C-000001` et `UFSC-L-000001`; préfixe et largeur sont filtrables (`ufsc_identifier_prefix`, `ufsc_identifier_width`). Une table de séquences monotones est mise à jour atomiquement et le registre impose une unicité globale de valeur et une attribution unique par entité. Un appel répété retourne l'attribution existante. Une ligne archivée reste dans le registre et son numéro n'est jamais réutilisé.

Les ASPTT vides sont autorisés. Une valeur non vide déjà portée par une autre entité est refusée. Un préfixe `UFSC-` est refusé dans un champ ASPTT.

## Renouvellement annuel

La saison cible vient exclusivement de `UFSC_Season_Service::get_current_season()`. Seules les affiliations `active` ou `validated` ouvrent la gestion des licences. La clé personne privilégie le numéro UFSC, puis `previous_licence_id`, puis un hash legacy nom + prénom + naissance + club. Le nom seul n'est jamais une clé.

Le renouvellement crée une ligne et conserve `previous_licence_id`, le club, l'identité stable et le numéro UFSC. Il recalcule saison et catégories. Il ne recopie pas le numéro ASPTT, paiement, commande, réponses médicales, documents expirés ou statut validé.

Chaque personne donne une ligne panier nominative de quantité 1 avec club, brouillon/licence, source, clé personne, saison, type `renewal` et numéro UFSC. Les vérifications sont répétées avant panier, checkout et paiement. `on-hold` reste en attente; après paiement le dossier passe `pending_validation`, puis l'administration revérifie affiliation, doublons et documents avant validation.

Statuts annuels canoniques : `draft`, `pending_payment`, `pending_validation`, `correction_required`, `active`, `validated`, `rejected`, `suspended`, `expired`.

## Administration, import et journalisation

Les générations/saisies utilisent POST, `manage_options` et un nonce lié à l'entité. Les imports doivent fournir l'ID interne ou l'identifiant UFSC (jamais le nom seul), être prévisualisés, signaler doublons/introuvables et attendre confirmation. Les opérations sont consignées sans donnée médicale dans `wp_ufsc_identifier_audit` (action, entité/ID, ancienne/nouvelle valeur, administrateur, date, saison, justification).

Le diagnostic **Audit identifiants UFSC / ASPTT** liste sans correction automatique les doublons UFSC/ASPTT et les lignes annuelles dupliquées. Toute correction est manuelle et justifiée.

## Migration et rollback

La migration 1.4.0 est additive et relançable : tables de séquences, registre, audit et colonnes canoniques. Elle ne génère, ne fusionne, ne réattribue et ne supprime aucune donnée historique. Les contraintes historiques ne sont posées qu'en l'absence de doublons.

Rollback applicatif : revenir au commit antérieur; les nouvelles tables/colonnes peuvent rester sans effet. Ne pas les supprimer avant sauvegarde et validation métier. Si un rollback SQL est imposé, exporter les trois tables `ufsc_identifier_*` puis les retirer manuellement; ne jamais effacer les colonnes legacy.

## Checklist préproduction

- [ ] Sauvegarde base et fichiers vérifiée; migration testée sur clone.
- [ ] Rapport de doublons exporté et arbitré sans fusion automatique.
- [ ] Double clic et deux sessions simultanées testés pour clubs/licences.
- [ ] Valeurs UFSC et ASPTT distinctes visibles sur les fiches.
- [ ] Affiliation inactive/pending bloque panier, checkout et paiement licence.
- [ ] Deux personnes produisent deux lignes nominatives, quantité 1.
- [ ] Renouvellement crée une ligne, préserve l'archive et `previous_licence_id`.
- [ ] ASPTT, paiement, commande et documents expirés ne sont pas recopiés.
- [ ] BACS `on-hold` ne valide pas; paiement abouti donne `pending_validation`.
- [ ] Validation admin, permissions, nonces, journaux et notifications vérifiés.
- [ ] ZIP installé sur un environnement vierge et procédure rollback répétée.
