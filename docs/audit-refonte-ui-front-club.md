# Audit UI front club — tableau de bord et compte club

## Renderers et shortcodes

- `/tableau-de-bord-club/` : shortcode canonique `[ufsc_club_dashboard]`, rendu par `UFSC_Frontend_Shortcodes::render_club_dashboard()` dans `includes/frontend/class-frontend-shortcodes.php`.
- `/compte-club/` : rendu profil via `[ufsc_club_profile]` / section profil du tableau de bord, rendu par `UFSC_Frontend_Shortcodes::render_club_profile()` dans `includes/frontend/class-frontend-shortcodes.php`.

## CSS réellement chargé

- Les deux renderers enqueue `assets/css/ufsc-front.css` avec le handle `ufsc-front`.
- Le bootstrap charge aussi `assets/frontend/css/frontend.css` sur les pages contenant `[ufsc_club_dashboard]` ou les pages club connues.
- La refonte premium de cette PR reste volontairement limitée à `assets/css/ufsc-front.css`, avec un scope strict `.ufsc-club-account.ufsc-premium-v3` pour éviter les régressions admin/back-office.

## Structure HTML principale

- Tableau de bord : `.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3` → `.ufsc-dashboard-header--premium` → `.ufsc-dashboard-hero-layout` → résumé club + `.ufsc-hero-kpi-grid`.
- Compte club : `.ufsc-club-account.ufsc-club-profile.ufsc-premium-v3` → `.ufsc-profile-header` → `.ufsc-club-hero` → résumé club + `.ufsc-profile-insight-band` + formulaire existant.

## Cause racine des problèmes de lisibilité

- Les KPI du hero étaient rendus sur fonds translucides dans un header bleu, ce qui diminuait le contraste.
- Les cartes profil/formulaire utilisaient plusieurs passes CSS successives avec largeurs et espacements hétérogènes.
- Le résumé du compte club pouvait retomber sur des propriétés directes (`$club->adresse`, `$club->telephone`, etc.) au lieu des alias historiques centralisés.
- Les actions et documents existaient mais manquaient d’une hiérarchie visuelle et de focus styles homogènes.

## Garanties de non-régression

- Aucune migration, écriture automatique, suppression ou renommage de données.
- Les noms de champs, nonces, actions `admin-post.php`, upload/suppression logo et documents restent inchangés.
- Les helpers de lecture utilisés pour les alias historiques sont read-only.
- La logique affiliation annuelle, attestation UFSC, produit 4823, archives et rattachement utilisateur-club n’est pas modifiée.

## Recette manuelle demandée

Tester `/tableau-de-bord-club/` et `/compte-club/` aux largeurs 1440, 1280, 1024, 768 et 375 px :

- résumé : nom, région, adresse, téléphone, email, site, saison et statut lisibles si présents ;
- KPI : contraste lisible, grille stable, aucun débordement ;
- actions : ajouter licence, mettre à jour club, attestation visible/téléchargeable si disponible ;
- formulaire : sections, nonces, champs, dirigeants, distribution, documents et bouton de sauvegarde préservés ;
- aucune disparition de club, licence, archive ou rattachement utilisateur.
