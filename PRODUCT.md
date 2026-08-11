# Produit

<!-- impeccable:product-schema 1 -->

## Platform

web

## Utilisateurs

- **Responsables de clubs UFSC — utilisateurs quotidiens principaux.** Ils gèrent leur club et les licences de leurs adhérents sans assistance technique : ajout, enregistrement en brouillon, complétion, renouvellement et paiement. Leur résultat critique est d’achever un parcours simple, fiable et compréhensible jusqu’au panier WooCommerce, avec des erreurs précises et des actions toujours visibles.
- **Équipe administrative UFSC — utilisateurs de pilotage.** Elle gère les clubs, licences, saisons, statuts, documents, quotas d’affiliation, renouvellements et paiements. Elle a besoin de données cohérentes, de filtres fiables et de contrôles d’accès stricts.
- **Comptes Région — utilisateurs de pilotage à périmètre restreint.** Ils accèdent aux fonctions et données autorisées par leurs droits, sans exposition des données d’autres régions ou clubs.

## Finalité du produit

UFSC – Gestion Clubs & Licences est un outil métier WordPress sur mesure qui centralise les démarches administratives de l’Union Française des Sports de Combat et relie l’administration nationale et régionale au portail Club.

Le produit doit permettre aux clubs d’accomplir leurs démarches de manière autonome et à l’UFSC de piloter les opérations fédérales avec des données fiables, des règles métier cohérentes et des accès maîtrisés. Le succès signifie que les parcours critiques — notamment la création, le renouvellement et le paiement des licences — peuvent être terminés sans ambiguïté ni assistance technique.

## Positionnement

« Outil métier WordPress sur mesure centralisant les démarches administratives UFSC et reliant l’administration nationale et régionale au portail Club. »

Sa spécificité repose sur l’intégration, dans un même environnement métier UFSC, des démarches clubs et licences, des saisons, statuts, documents, quotas d’affiliation, renouvellements et paiements, avec un portail Club relié au pilotage national et régional.

## Contexte opérationnel

- Le produit fonctionne dans un environnement WordPress de production et s’intègre durablement à WooCommerce, Astra et Elementor.
- Les responsables de clubs utilisent le portail Club pour gérer les informations du club et le cycle de vie des licences jusqu’au panier WooCommerce.
- L’administration nationale et les comptes Région utilisent les interfaces de pilotage selon leurs droits pour contrôler les clubs, licences, saisons, statuts, documents, quotas, renouvellements et paiements.
- La saison UFSC s’étend du 1er août au 31 juillet.
- Le pack d’affiliation comprend 10 licences : 3 réservées au président, au secrétaire et au trésorier, puis 7 licences libres.
- L’attestation d’honorabilité concerne uniquement les fonctions réglementées ; les adhérents ordinaires n’y sont pas soumis.
- Toute évolution suit une branche Git dédiée, une recette obligatoire en environnement DEV, puis une fusion et un déploiement. Aucune modification directe n’est effectuée en production.

## Capacités et contraintes

- Gestion des clubs, licences, affiliations, saisons, statuts, documents administratifs, quotas, renouvellements, paiements, exports, diagnostics et communications UFSC.
- Ajout, sauvegarde en brouillon, complétion, renouvellement et paiement de licences depuis le portail Club.
- Intégration du paiement et du panier par WooCommerce.
- Contrôles d’accès stricts entre clubs, régions et administration nationale ; aucune donnée personnelle ne doit être exposée hors du périmètre autorisé.
- Interface entièrement en français.
- Compatibilité durable avec WordPress, WooCommerce, Astra et Elementor.
- Les styles du portail UFSC doivent rester strictement bornés afin de ne pas dégrader le thème ni d’autres plugins.
- Les règles métier, permissions, nonces, mécanismes de panier, saisons, statuts et requêtes ne doivent jamais être modifiés uniquement pour améliorer l’interface.
- Aucun défaut fonctionnel ne doit être masqué par du CSS.
- Le produit doit rester rapide, compact et piloté par le contenu.
- Toute évolution doit être vérifiée avec les tests existants sur mobile, tablette et desktop avant fusion.

## Engagements de marque

- Le nom produit est **UFSC – Gestion Clubs & Licences**.
- L’identité officielle UFSC doit être respectée et déclinée dans un système visuel homogène.
- L’expérience doit rester professionnelle, intuitive et fluide.
- Tous les contenus d’interface sont rédigés en français.

## Éléments de preuve disponibles

- Présentation du produit, de ses objectifs, de ses modules et de son écosystème : `README.md`.
- Parcours utilisateur documentés, rôles, tableau de bord Club, API, attestations, statuts, administration et paiement : `USER_JOURNEY_DOCUMENTATION.md`.
- Documentation de la couche portail et des shortcodes : `FRONTEND_LAYER_README.md` et `SHORTCODE_USAGE.md`.
- Documentation des permissions et audits de contrôle d’accès : `docs/permissions.md` et les rapports d’audit sous `docs/`.
- Implémentation WordPress principale et intégrations : `ufsc-clubs-licences-sql.php`, `includes/`, `inc/` et `templates/`.
- Interfaces et styles existants : `assets/`.
- Un badge UFSC vectoriel est présent dans `assets/svg/ufsc-badge.svg`. Son statut comme élément officiel de l’identité n’est pas confirmé par sa seule présence dans le dépôt.
- Le dépôt contient des tests automatisés sous `tests/` et une vérification de syntaxe PHP sous `.github/workflows/php-syntax.yml`.
- Aucun témoignage, benchmark, label de conformité ou résultat utilisateur quantifié ne doit être inventé à partir des éléments actuellement disponibles.

## Principes produit

1. **Autonomie des clubs.** Chaque démarche courante doit pouvoir être comprise et terminée sans assistance technique, avec des actions visibles et des erreurs qui expliquent précisément comment poursuivre.
2. **Fiabilité fédérale.** Les données, saisons, statuts, quotas, renouvellements et paiements restent cohérents entre le portail Club et le pilotage national ou régional.
3. **Séparation stricte des périmètres.** Les permissions et l’isolation des données priment sur la commodité ; aucun club ou compte Région ne voit des informations hors de son mandat.
4. **Intégration sans régression.** L’expérience évolue sans fragiliser WordPress, WooCommerce, Astra, Elementor ni les règles métier et mécanismes de sécurité existants.
5. **Clarté sur tous les appareils.** Les parcours restent rapides, compacts, lisibles et complets du mobile au grand écran.

## Accessibilité et inclusion

- Conformité requise : **WCAG 2.2 niveau AA**.
- Contrastes suffisants, navigation complète au clavier, focus visible, labels correctement associés et messages compréhensibles.
- Cibles interactives d’au moins 44 × 44 px.
- Boutons lisibles dans tous leurs états : normal, survol, focus, actif et désactivé.
- Responsive obligatoire de 360 px aux grands écrans, avec prise en charge des formats mobile, tablette et desktop.
- Aucun débordement horizontal, texte tronqué, hauteur fixe excessive ou grand espace vide qui nuise à l’usage.
- Conformité RGPD : minimisation et protection des données personnelles, avec absence d’exposition entre clubs ou régions.
