# Checklist de recette DEV → préproduction UFSC

> Exécuter sur un clone anonymisé, jamais directement en production. Noter date, testeur, commit, WordPress/PHP/WooCommerce, navigateur, résultat et preuve pour chaque ligne.

## Préparation, ZIP et rollback
- [ ] Vérifier SHA-256 du ZIP et conserver le ZIP précédent.
- [ ] Sauvegarder base et `wp-content/uploads`; tester la restauration.
- [ ] Installer le ZIP à blanc, vérifier arborescence/bootstrap, puis activer sans fatal.
- [ ] Contrôler logs PHP/WordPress/WooCommerce pendant activation et migration.
- [ ] Vérifier version migration, trois tables identifiants, colonnes et préfixe WP.
- [ ] Relancer activation/boot : aucune suppression, duplication, génération massive ni écrasement.
- [ ] Documenter procédure maintenance → désactivation → ZIP précédent → caches → contrôle commandes.

## Données et club MFC
- [ ] Retrouver le club MFC et ses archives sans les modifier.
- [ ] Vérifier affiliation 2026-2027 unique et saison issue du service (bascule 1er août).
- [ ] Générer une fois le numéro UFSC club; double clic/rejeu rend le même numéro.
- [ ] Vérifier numéro UFSC non éditable et unique.
- [ ] Saisir/vider un numéro ASPTT admin; refuser `UFSC-` dans toutes les casses et un doublon.
- [ ] Confirmer que les alias historiques apparaissent au diagnostic sans migration/requalification.

## Licences et renouvellement
- [ ] Retrouver licences historiques/archives et numéro UFSC permanent.
- [ ] Renouveler individuellement : nouvelle ligne, `previous_licence_id`, `person_identifier`, saison correcte.
- [ ] Vérifier conservation UFSC et absence de copie ASPTT, paiement, commande et documents expirés.
- [ ] Vérifier recalcul catégorie et statut avant paiement/validation.
- [ ] Renouveler en groupe, compteurs/rapport/raisons de blocage/liens corrects.
- [ ] Rejouer/double-cliquer : aucun doublon annuel.
- [ ] Tester admin « à renouveler », filtres, individuel, groupé et exceptionnel avec justification/audit.
- [ ] Tenter d'accéder/renouveler une licence d'un autre club : refus sans fuite de données.

## Panier, checkout et commandes
- [ ] Produit affiliation par défaut 4823 puis produit explicitement configuré.
- [ ] Produit licence réellement configuré/utilisé.
- [ ] Une personne par ligne, quantité 1, lignes distinctes pour même produit.
- [ ] Contrôler clé nominative et métadonnées club/personne/saison/source/type.
- [ ] Altérer POST/session/cart : falsification refusée à ajout, restauration, validation et checkout.
- [ ] Tester panier, checkout et création de commande; aucune seconde commande si payable existe.
- [ ] BACS `on-hold` : affiliation/licence non active; CTA « Finaliser mon paiement » si pertinent.
- [ ] Paiement réussi : `pending_validation`, puis validation admin uniquement active.
- [ ] Commandes annulée/échouée/remboursée : aucune activation, reprise cohérente.
- [ ] Affiliation suspendue/inactive : création et renouvellement licence bloqués à chaque étape.

## Imports, exports, diagnostics et logs
- [ ] Import ASPTT : validation, doublons, dry-run/rapport, aucune copie vers UFSC.
- [ ] Export : permissions, périmètre club, échappement CSV, absence de données indues.
- [ ] Diagnostics : legacy/hybrid, doublons, alias ambigus, aucune mutation.
- [ ] Logs/audit : action, entité, saison, acteur; aucune donnée médicale ni secret/personnelle inutile.
- [ ] Tester erreurs DB/Woo/upload : message actionnable, pas de stack trace publique.

## UI/UX et accessibilité — captures attendues
- [ ] Admin : dashboard, Clubs, fiche Club, Licences, fiche Licence, diagnostics, imports/exports, journaux.
- [ ] Front : compte/dashboard, affiliation, licences, renouvellements, archives, documents, dirigeants, panier/checkout.
- [ ] Captures 320, 375, 768, 1024, 1280, 1440 px sous Astra et Elementor.
- [ ] Aucun overflow/CLS/scale; images dimensionnées; grilles/minmax et savebar utilisables.
- [ ] Zoom 200 %, clavier seul, ordre de focus, focus visible, liens/boutons explicites.
- [ ] Labels/requis/erreurs associés, `aria-live`, titres/tableaux/modal/confirmation corrects.
- [ ] Contrastes WCAG 2.2 AA et cibles tactiles; joindre rapport axe/Lighthouse + contrôle manuel.
- [ ] Vérifier états vides, succès/erreurs, filtres effaçables et absence de clé GET technique visible.

## Performance et décision
- [ ] Profil 56 clubs et plusieurs milliers de licences : listes paginées, KPI, archives, diagnostics.
- [ ] Capturer nombre/temps requêtes, mémoire, slow log et `EXPLAIN`; vérifier absence N+1.
- [ ] Vérifier assets conditionnels et appels WooCommerce sur pages non UFSC.
- [ ] Rejouer lint PHP, check JS, runner, tests ZIP extrait et bootstrap sur l'artefact exact.
- [ ] Aucun P0 ouvert, aucun fatal/migration destructive, archives intactes et rollback testé.
- [ ] Décision signée : prêt pour recette DEV / prêt pour préproduction / No-Go (jamais « production » sans recette réelle).
