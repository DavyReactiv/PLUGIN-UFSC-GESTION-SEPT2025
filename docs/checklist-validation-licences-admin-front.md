# Checklist de validation licences admin / front UFSC Gestion

## 1. Objectif et périmètre

Cette checklist sert à valider en environnement WordPress réel les corrections anti-régression appliquées aux licences UFSC Gestion :

- harmonisation de la résolution des tables clubs/licences entre admin, front et mapping utilisateur → club ;
- conservation du rendu admin canonique des licences ;
- ajout du menu front **Mes licences UFSC** ;
- amélioration de l'affichage des tableaux admin/front.

Cette checklist est volontairement non destructive : elle ne prévoit aucune migration, aucune suppression, aucun changement d'identifiant et aucune modification de statut.

## 2. Pré-requis de validation

Avant de commencer les tests manuels :

- [ ] Tester d'abord sur une préproduction ou une copie récente de production.
- [ ] Utiliser un compte administrateur WordPress.
- [ ] Utiliser au moins un compte club rattaché à un club ayant des licences valides.
- [ ] Utiliser un second compte club rattaché à un autre club pour vérifier l'isolation des données.
- [ ] Utiliser un compte connecté sans club rattaché pour vérifier le message d'absence de club.
- [ ] Identifier un club précis dans l'admin avec plusieurs licences et comparer ce même club côté front.
- [ ] Ne lancer aucune requête SQL destructive pendant la validation.

## 3. Résumé anti-régression attendu

- [ ] L'admin Licences utilise la page canonique `UFSC_SQL_Admin::render_licences()`.
- [ ] Les anciens slugs de licences restent compatibles mais ne déclenchent pas un deuxième tableau.
- [ ] Le front club utilise le rendu existant `render_club_licences()` en lecture seule pour **Mes licences UFSC**.
- [ ] Les requêtes front restent filtrées sur le `club_id` du club connecté.
- [ ] La vue détail d'une licence front vérifie à la fois l'identifiant de licence et le `club_id`.
- [ ] Les tables clubs/licences résolues côté front correspondent à la source canonique utilisée par l'admin.
- [ ] Le menu **Licences UFSC/ASPTT – Documents & Numéros** reste présent et distinct.
- [ ] Aucune donnée du plugin compétition n'est affichée dans **Mes licences UFSC**.

## 4. Validation admin — tableau Licences UFSC Gestion

### 4.1 Accès et rendu unique

- [ ] Ouvrir `/wp-admin/admin.php?page=ufsc_lc_licences`.
- [ ] Vérifier qu'un seul tableau de licences s'affiche.
- [ ] Vérifier qu'aucun ancien tableau `WP_List_Table` legacy n'apparaît sous ou au-dessus du tableau principal.
- [ ] Ouvrir `/wp-admin/admin.php?page=ufsc-gestion-licences`.
- [ ] Vérifier que l'ancien slug affiche le même rendu canonique, sans double tableau.
- [ ] Ouvrir `/wp-admin/admin.php?page=ufsc-sql-licences`.
- [ ] Vérifier que ce slug historique ne déclenche pas de double rendu.
- [ ] Ouvrir `/wp-admin/admin.php?page=ufsc-sql-licenses`.
- [ ] Vérifier que ce slug historique ne déclenche pas de double rendu.

### 4.2 Filtres, recherche et navigation

- [ ] Vérifier que le champ de recherche est toujours présent et utilisable.
- [ ] Rechercher par nom ou numéro de licence et vérifier que les résultats restent cohérents.
- [ ] Vérifier que le filtre club est toujours présent.
- [ ] Filtrer sur un club précis et vérifier que seules ses licences apparaissent.
- [ ] Vérifier que le filtre statut est toujours présent.
- [ ] Tester au moins un statut validé, en attente, brouillon ou annulé si les données existent.
- [ ] Vérifier que le filtre saison est toujours présent si disponible.
- [ ] Vérifier que le filtre catégorie est toujours présent si disponible.
- [ ] Vérifier que le filtre compétition/pratique est toujours présent si disponible.
- [ ] Vérifier que les filtres PDF et visibilité restent présents si l'installation les propose.
- [ ] Vérifier la pagination sur une liste contenant plus d'une page de résultats.

### 4.3 Export et actions admin

- [ ] Vérifier que l'export CSV reste disponible.
- [ ] Lancer un export CSV sur un périmètre réduit et vérifier que le fichier est généré.
- [ ] Vérifier que l'action **Consulter** reste visible et fonctionnelle.
- [ ] Vérifier que l'action **Éditer** reste visible et fonctionnelle pour un administrateur autorisé.
- [ ] Vérifier que l'action **Paiement** reste visible si elle existait avant la correction.
- [ ] Vérifier que l'action **Annuler**, **Corbeille** ou équivalent reste visible si elle existait avant la correction.
- [ ] Vérifier que les actions groupées existantes restent disponibles si elles existaient avant la correction.
- [ ] Ne valider aucune action destructive en production pendant ce test ; se limiter à vérifier leur présence ou utiliser une préproduction.

### 4.4 Lisibilité admin

- [ ] Vérifier que les colonnes importantes restent visibles : N° licence, N° ASPTT, nom, prénom, date de naissance, club, région, statut, saison, catégorie, date de création, actions.
- [ ] Vérifier que les badges de statut sont lisibles et cohérents visuellement.
- [ ] Vérifier que les boutons d'action ne se chevauchent pas.
- [ ] Vérifier que le tableau conserve un scroll horizontal propre sur écran réduit.
- [ ] Vérifier que le nombre de résultats est visible et compréhensible.
- [ ] Vérifier qu'un club précis ayant des licences valides en admin retrouve ces mêmes licences côté front.

## 5. Validation front club — menu Mes licences UFSC

### 5.1 Accès espace club

- [ ] Se connecter avec un compte club rattaché à un club existant.
- [ ] Ouvrir l'espace club.
- [ ] Vérifier que le menu **Mes licences UFSC** apparaît.
- [ ] Vérifier que le menu **Mes licences UFSC** est distinct du menu **Licences UFSC/ASPTT – Documents & Numéros**.
- [ ] Vérifier que le menu **Licences UFSC/ASPTT – Documents & Numéros** est toujours présent.
- [ ] Vérifier que le menu **Mes licences UFSC** ne remplace pas les pages de documents ASPTT/PDF existantes.

### 5.2 Contenu affiché

- [ ] Ouvrir **Mes licences UFSC**.
- [ ] Vérifier qu'un message discret indique qu'il s'agit des licences administratives UFSC Gestion, et non des compétitions.
- [ ] Vérifier que les licences affichées correspondent au club connecté.
- [ ] Comparer avec l'admin filtré sur le même club.
- [ ] Vérifier que les licences valides attendues apparaissent.
- [ ] Vérifier qu'aucune licence d'un autre club n'apparaît.
- [ ] Vérifier que les colonnes minimales sont lisibles : N° licence, N° ASPTT si disponible, nom, prénom, date de naissance, sexe si disponible, statut, saison, catégorie, pratique ou compétition/loisir si disponible, date de création si disponible.
- [ ] Vérifier que les statuts sont affichés sous forme de badges lisibles.
- [ ] Vérifier que le bouton **Consulter** est présent si une vue détail est prévue.
- [ ] Vérifier qu'aucune action destructive n'est proposée dans cette vue club.

### 5.3 Isolation par club

- [ ] Se connecter avec un second compte club rattaché à un autre club.
- [ ] Ouvrir **Mes licences UFSC**.
- [ ] Vérifier que seules les licences de ce second club s'affichent.
- [ ] Vérifier qu'aucune licence du premier club n'apparaît.
- [ ] Tester une URL directe de consultation d'une licence appartenant à un autre club.
- [ ] Vérifier que la licence d'un autre club n'est pas affichée.
- [ ] Se connecter avec un compte sans club rattaché.
- [ ] Ouvrir l'espace club ou le shortcode licences si accessible.
- [ ] Vérifier qu'un message propre de type **Club non trouvé** ou équivalent s'affiche.
- [ ] Vérifier qu'aucune liste globale de licences n'est affichée pour ce compte sans club.

## 6. Validation mobile et responsive

### 6.1 Admin

- [ ] Réduire la largeur du navigateur sur la page admin Licences.
- [ ] Vérifier que les filtres restent utilisables.
- [ ] Vérifier que le tableau ne casse pas la mise en page WordPress admin.
- [ ] Vérifier que le scroll horizontal permet de consulter toutes les colonnes.
- [ ] Vérifier que les actions restent lisibles et cliquables.

### 6.2 Front

- [ ] Tester **Mes licences UFSC** sur mobile ou via l'émulation responsive du navigateur.
- [ ] Vérifier que le tableau reste dans son conteneur.
- [ ] Vérifier que le scroll horizontal est disponible si les colonnes dépassent la largeur de l'écran.
- [ ] Vérifier que les badges de statut restent lisibles.
- [ ] Vérifier que le bouton **Consulter** reste accessible.
- [ ] Vérifier que les messages vides ou d'erreur restent compréhensibles sur mobile.

## 7. Validation sécurité et non-régression données

- [ ] Vérifier qu'un utilisateur non connecté ne voit pas les licences d'un club.
- [ ] Vérifier qu'un compte club ne peut pas consulter une licence d'un autre club via URL directe.
- [ ] Vérifier qu'un compte sans club ne voit jamais toutes les licences.
- [ ] Vérifier qu'aucune action destructive n'est disponible dans **Mes licences UFSC**.
- [ ] Vérifier qu'aucun statut de licence n'est modifié par une simple consultation front.
- [ ] Vérifier qu'aucun identifiant de club, licence ou utilisateur n'est modifié pendant les tests.
- [ ] Vérifier qu'aucune donnée compétition n'est mélangée aux licences administratives UFSC Gestion.

## 8. Vérifications techniques à relancer avant déploiement

Commandes recommandées depuis la racine du dépôt :

```bash
php -l inc/admin/menu.php
php -l inc/common/tables.php
php -l includes/admin/class-sql-admin.php
php -l includes/core/class-sql.php
php -l includes/core/class-user-club-mapping.php
php -l includes/frontend/class-frontend-shortcodes.php
git diff --check
git diff --name-only HEAD^..HEAD
```

Contrôles complémentaires recommandés :

- [ ] Rechercher dans le diff toute requête destructive ajoutée : `DROP`, `TRUNCATE`, `DELETE FROM`, `ALTER TABLE`, migration destructive ou changement massif de statut.
- [ ] Vérifier que la liste des fichiers modifiés ne contient aucun fichier du plugin UFSC Licences Compétitions.
- [ ] Vérifier que les anciens slugs de licences redirigent ou délèguent vers le rendu canonique.
- [ ] Vérifier que l'ancien rendu `UFSC_Gestion_Licences_List_Table` n'est pas instancié en parallèle du rendu canonique.
- [ ] Vérifier que le front appelle bien `ufsc_get_licences_table()` et filtre sur `club_id`.
- [ ] Vérifier que la vue détail front utilise bien une condition combinant l'identifiant de licence et le `club_id`.

## 9. Critères d'acceptation avant production

Le déploiement peut être validé si :

- [ ] le tableau admin Licences apparaît une seule fois sur le slug principal et les slugs historiques ;
- [ ] les filtres, exports, actions et paginations admin existants sont conservés ;
- [ ] **Mes licences UFSC** apparaît dans l'espace club ;
- [ ] **Licences UFSC/ASPTT – Documents & Numéros** reste présent ;
- [ ] les licences front correspondent au même club que le filtre admin ;
- [ ] aucun club ne peut voir les licences d'un autre club ;
- [ ] un compte sans club ne voit pas de liste globale ;
- [ ] les affichages admin et front restent lisibles sur écran large et mobile ;
- [ ] aucun fichier compétition n'a été modifié ;
- [ ] aucune requête destructive n'a été ajoutée ;
- [ ] aucune donnée réelle n'a été modifiée pendant la validation.

## 10. Risques restants à surveiller

- Les installations anciennes peuvent encore contenir des options historiques `ufsc_gestion_settings`; elles doivent rester en fallback, sans migration automatique.
- Certaines colonnes optionnelles peuvent dépendre du schéma réellement présent en base ; vérifier les libellés sur une copie représentative de production.
- Les anciens liens ou favoris administrateurs doivent être testés explicitement pour confirmer la compatibilité des slugs historiques.
- Les thèmes front peuvent surcharger des styles ou largeurs de conteneur ; valider l'affichage sur le thème réellement utilisé par les clubs.
- Les comptes clubs mal rattachés doivent être identifiés par audit métier, sans afficher de licences globales côté front.

## 11. Recommandations avant déploiement production

- Faire valider la checklist par un administrateur UFSC et par au moins un club pilote.
- Comparer un échantillon de clubs entre admin et front avant ouverture large.
- Conserver une sauvegarde complète avant déploiement, même si la correction ne contient pas de migration.
- Surveiller les journaux WordPress/PHP après déploiement pour détecter les schémas atypiques ou options historiques.
- Ne traiter les éventuels ajustements de filtres avancés ou de menus compétition que dans une tâche séparée.
