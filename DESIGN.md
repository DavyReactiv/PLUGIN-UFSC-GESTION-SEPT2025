---
name: "UFSC – Gestion Clubs & Licences"
description: "Système visuel constaté du portail Club et de l’administration UFSC, organisé comme une console opérationnelle."
colors:
  bleu-federal: "#0b4f86"
  bleu-profondeur: "#073b66"
  turquoise-action: "#0f9fba"
  bleu-controle: "#173b67"
  bleu-survol: "#062f54"
  succes: "#047857"
  information: "#1d4ed8"
  avertissement: "#b45309"
  erreur: "#b91c1c"
  texte-principal: "#0f172a"
  texte-secondaire: "#475569"
  surface: "#ffffff"
  surface-douce: "#f6f9fd"
  bordure: "#cbdff3"
  focus: "#f59e0b"
  desactive-fond: "#e5e7eb"
  desactive-texte: "#374151"
  admin-bleu: "#123c7c"
  admin-bleu-profond: "#0d2f66"
  admin-bleu-vif: "#164fc4"
  admin-rouge: "#d71920"
  admin-fond: "#f4f6fb"
  admin-texte: "#111827"
  admin-texte-secondaire: "#344054"
  admin-bordure: "#cfd9ec"
typography:
  display:
    fontFamily: "inherit"
    fontSize: "clamp(2rem, 4vw, 3.45rem)"
    fontWeight: 850
    lineHeight: 1.15
    letterSpacing: "-0.04em"
  headline:
    fontFamily: "inherit"
    fontSize: "clamp(1.7rem, 3vw, 2.65rem)"
    fontWeight: 900
    lineHeight: 1.55
    letterSpacing: "-0.035em"
  title:
    fontFamily: "inherit"
    fontSize: "1.2rem"
    fontWeight: 900
    lineHeight: 1.35
    letterSpacing: "-0.025em"
  body:
    fontFamily: "inherit"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "inherit"
    fontSize: "0.78rem"
    fontWeight: 850
    lineHeight: 1.35
    letterSpacing: "0.075em"
rounded:
  compact: "7px"
  control: "8px"
  field: "14px"
  card: "18px"
  card-large: "28px"
  pill: "999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
  xl: "32px"
  xxl: "48px"
components:
  button-primary:
    backgroundColor: "{colors.bleu-federal}"
    textColor: "{colors.surface}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.bleu-survol}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-secondary:
    backgroundColor: "#f8fafc"
    textColor: "{colors.bleu-controle}"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-danger:
    backgroundColor: "{colors.surface}"
    textColor: "#8f1d14"
    typography: "{typography.label}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-disabled:
    backgroundColor: "{colors.desactive-fond}"
    textColor: "{colors.desactive-texte}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  field:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.texte-principal}"
    typography: "{typography.body}"
    rounded: "{rounded.field}"
    padding: "10px"
    height: "48px"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.texte-principal}"
    rounded: "{rounded.card-large}"
    padding: "18px"
  navigation-item:
    backgroundColor: "#f8fafc"
    textColor: "{colors.bleu-controle}"
    rounded: "{rounded.control}"
    padding: "9px 12px"
    height: "44px"
  navigation-item-active:
    backgroundColor: "{colors.bleu-controle}"
    textColor: "{colors.surface}"
    rounded: "{rounded.control}"
    padding: "9px 12px"
    height: "44px"
---

# Design System: UFSC – Gestion Clubs & Licences

## Overview

**Creative North Star: "La Console des clubs"**

Le système constaté cherche à transformer les opérations fédérales en une console professionnelle, compacte, institutionnelle et sportive. Cette métaphore décrit le système interne ; elle ne remplace pas les intitulés métier visibles tels que « Tableau de bord Club », « Compte Club » ou « Mes licences UFSC ». L’interface privilégie la lecture rapide des statuts, la continuité des actions et l’accès direct aux démarches.

Le portail actuel n’est toutefois pas un système unifié. Sa couche canonique est le conteneur combinant les portées portail, compte et version premium, stylé principalement à la fin de `assets/css/ufsc-front.css`. Cette couche est chargée après `assets/frontend/css/frontend.css`, qui conserve un système historique sur les mêmes classes. Les formulaires d’ajout de licence, de club et d’authentification ont encore leurs propres feuilles. L’administration WordPress possède une autre déclinaison, puis une déclinaison supplémentaire pour la liste des clubs.

L’objectif futur confirmé est une interface administrative sportive professionnelle, compacte, accessible et cohérente, immédiatement compréhensible par des utilisateurs non techniques. Les effets décoratifs excessifs, les composants surdimensionnés, les grandes zones vides et le vocabulaire abstrait n’en font pas partie. Cette documentation décrit l’existant et ses écarts ; elle ne constitue ni une refonte ni une validation navigateur.

**Key Characteristics:**

- Bleu fédéral dominant, surfaces blanches et bleus très pâles.
- Densité opérationnelle élevée : filtres, statuts, tableaux et actions restent proches du contenu.
- Hiérarchie par cartes, bordures, bandes latérales de statut et ombres diffuses.
- Contrôles principaux d’au moins 44 px dans la couche canonique du portail.
- Navigation locale persistante et grilles qui se replient progressivement.
- Dépendance assumée à WordPress, Astra, Elementor et WooCommerce, avec des frontières de style encore inégales.

**The Existing-System Rule.** La source de vérité visuelle actuelle du portail est la cascade réellement chargée, pas le nom « premium » d’une section CSS ni un ancien template isolé.

## Colors

La palette canonique associe un bleu fédéral profond à des surfaces froides et à des couleurs fonctionnelles franches. Les tokens en frontmatter sont les valeurs normatives constatées pour la couche actuelle du portail et la page d’administration des clubs.

### Primary

- **Bleu fédéral** : fond des actions primaires, marqueur principal et base du dégradé d’en-tête.
- **Bleu de profondeur** : extrémité sombre des dégradés, titres sur fond clair et états de profondeur.
- **Turquoise d’action** : accent secondaire, halo de focus historique et lumière du dégradé principal. Son contraste avec le blanc n’est pas suffisant pour du petit texte normal ; il fonctionne mieux comme accent non textuel ou avec un texte sombre.
- **Bleu de contrôle** : contours, navigation active, boutons secondaires et pagination du contrat final du portail.

### Secondary

- **Bleus d’administration** : la liste des clubs utilise une famille séparée — bleu, bleu profond et bleu vif — avec un rouge UFSC à l’extrémité du dégradé d’en-tête.

### Tertiary

- **Succès, information, avertissement et erreur** : ces couleurs servent aux badges, messages, bandes latérales et états de dossier. Elles sont accompagnées de libellés, d’icônes, de bordures ou de structure ; la couleur ne doit jamais être l’unique signal.
- **Focus ambre** : le contrat final du portail utilise un contour ambre très visible. Cet ambre ne doit pas être employé comme petit texte sur fond blanc.

### Neutral

- **Texte principal** : texte courant et valeurs fortes sur surface claire.
- **Texte secondaire** : aides, sous-titres et métadonnées.
- **Surface et surface douce** : cartes blanches et fonds bleu-gris très pâles.
- **Bordure** : séparation froide des cartes, champs et tableaux.
- **États désactivés** : fond gris clair et texte gris sombre avec opacité conservée à 1 dans le contrat final.

### Contrastes statiques observés

Les rapports suivants sont calculés à partir des valeurs CSS, sans styles calculés dans un navigateur :

- Bleu fédéral sur blanc : **8,48:1**.
- Bleu de profondeur sur blanc : **11,50:1**.
- Succès sur blanc : **5,48:1**.
- Information sur blanc : **6,70:1**.
- Avertissement sur blanc : **5,02:1**.
- Erreur sur blanc : **6,47:1**.
- Texte principal sur blanc : **17,85:1**.
- Texte secondaire sur blanc : **7,58:1**.
- Texte désactivé sur fond désactivé : **8,33:1**.
- Turquoise d’action sur blanc : **3,14:1**, insuffisant pour du texte normal WCAG AA.
- Le bleu historique `#3498db` sur blanc : **3,15:1**, insuffisant pour du texte normal WCAG AA.
- Le bleu historique `#257af8` sur blanc : **4,02:1**, insuffisant pour du texte normal WCAG AA.
- Le gris `#64748b` sur `#eef2f7` : **4,23:1**, légèrement sous le seuil AA du texte normal.
- Le gris `#6b7280` sur `#e5e7eb` : **3,90:1**, insuffisant pour du texte normal.

**The Meaning-Before-Color Rule.** Un état comporte toujours un libellé ou une structure explicite en plus de sa couleur.

**The Contrast-Pair Rule.** Un token de couleur n’est pas automatiquement une paire texte/fond valide ; les combinaisons doivent être vérifiées dans leur état réel.

## Typography

**Display Font:** héritée du thème actif

**Body Font:** héritée du thème actif

**Admin Font:** pile native de WordPress

**Character:** La couche canonique n’importe aucune police et conserve l’intégration à Astra ou Elementor par héritage. La personnalité vient surtout des graisses élevées, des titres serrés et des labels compacts plutôt que d’une famille typographique propre.

### Hierarchy

- **Display** : grand titre du bandeau du portail, très gras et resserré ; valeurs exactes dans `typography.display`.
- **Headline** : nom du club et titres principaux de fiche ; valeurs exactes dans `typography.headline`.
- **Title** : titres de cartes et sections ; valeurs exactes dans `typography.title`.
- **Body** : base canonique du portail, valeurs exactes dans `typography.body`.
- **Label** : KPI, métadonnées et libellés denses, souvent en capitales avec espacement accru ; valeurs exactes dans `typography.label`.
- **Tables compactes** : la plupart des tables frontales et administratives descendent à 12–13 px ; certaines actions et aides atteignent 11 px.

### Sources et héritage

- Le conteneur canonique définit `font-family: inherit` ; Astra ou Elementor peuvent donc déterminer la famille finale.
- La feuille historique `assets/frontend/css/frontend.css` assigne une pile système à l’ancien tableau de bord, mais la règle canonique chargée après peut rétablir l’héritage sur l’élément qui porte les deux classes.
- L’administration conserve la typographie WordPress et ses tailles de contrôle natives, puis les feuilles UFSC renforcent principalement les poids et la hiérarchie.
- Les poids 850 et 900 sont demandés sans police variable garantie ; un navigateur peut les synthétiser ou les ramener à une graisse disponible.

**The Theme-Compatible Type Rule.** Le portail n’impose pas une nouvelle police globale ; toute future police doit être décidée explicitement et bornée au portail.

## Layout

Le portail repose sur des grilles CSS, des cartes pleine largeur et une densité adaptative. La couche la plus récente vise un conteneur allant jusqu’à 1540 px, tandis que des règles antérieures limitent le même produit à 1200, 1280, 1360 ou 1680 px. La largeur réellement gagnante dépend de la combinaison de classes et de l’ordre de chargement.

### Structure du portail

- Le wrapper actuel est `.ufsc-club-portal.ufsc-club-account.ufsc-premium-v3`.
- Le bandeau utilise deux colonnes principales dans la couche premium : contenu et zone latérale d’environ 300 px.
- Le profil est organisé en deux colonnes de cartes, puis en une seule colonne aux seuils intermédiaires.
- Les blocs dirigeants utilisent trois colonnes, puis deux, puis une.
- Les KPI utilisent deux ou quatre colonnes selon le contexte ; plusieurs règles `auto-fit` coexistent.
- La navigation Compte Club est une grille de six entrées, passe à trois puis deux colonnes, avec d’anciennes variantes flexibles ou défilantes encore présentes dans la cascade.
- La barre d’enregistrement reste collée au bas de la fenêtre avec un fond blanc translucide.

### Tables

- Les tables de licences historiques imposent des largeurs minimales de 760, 980, 1040, 1080 ou 1180 px et utilisent un conteneur à défilement horizontal.
- La table des licences courantes devient une pile de cartes à **900 px**.
- La table de renouvellement devient une pile à **780 px**, avec une autre règle semblable à **768 px**.
- Les tables d’administration des clubs et licences conservent un tableau large et un défilement horizontal ; elles ne se transforment pas en cartes.

### Breakpoints réellement présents

- **420 px** : navigation et empilement très étroit.
- **480 px** : seuil historique mobile.
- **520 px** : actions du tableau de bord pleine largeur.
- **600 px** : principal seuil mobile compact, formulaires et actions sur une colonne.
- **640 px** et **680 px** : variantes historiques de repli des actions et KPI.
- **720 px**, **767 px**, **768 px**, **780 px** et **782 px** : famille de seuils tablette/mobile concurrents.
- **900 px** et **960 px** : repli des profils, tables courantes et KPI administratifs.
- **1024 px** et **1100 px** : repli des héros, grilles et filtres.
- **1180 px**, **1200 px** et **1380 px** : ajustements de cockpit, dirigeants et administration.
- **1600 px** : extension des grilles et tables d’administration.

Les déclarations de breakpoints sous forme de variables CSS (`var(--tablet)`, `var(--desktop)`) apparaissent dans des requêtes média historiques. Les propriétés personnalisées ne sont pas résolues dans les conditions `@media` CSS classiques ; ces règles peuvent donc être ignorées, et les seuils chiffrés ajoutés plus tard servent de compensation.

### Frontières avec l’écosystème

- **Astra** : uniquement sur les pages marquées `body.ufsc-club-portal-page`, le plugin force la largeur de `.ast-container`, `.content-area`, `.site-main` et `.entry-content` pour libérer le portail. Ces règles appartiennent au plugin mais ciblent la structure Astra.
- **Elementor** : le plugin force la largeur des widgets shortcode contenant le portail avec `:has()` et modifie les variables `--width` et `--container-widget-width`. Le rendu dépend donc du support de `:has()` et de la structure Elementor.
- **WooCommerce** : aucune identité complète de panier ou checkout n’est définie dans les feuilles analysées. Le plugin stylise ses propres actions avant la redirection, tandis que le panier et le paiement restent sous l’autorité de WooCommerce et du thème.
- **WordPress Admin** : les contrôles `.button`, les tables `.wp-list-table`, Dashicons et la structure `.wrap` proviennent de WordPress ; les feuilles UFSC les surchargent sur les pages du plugin.

**The Portal Boundary Rule.** Les adaptations Astra et Elementor restent conditionnées à la classe de page du portail ; elles ne deviennent jamais des règles de thème globales.

## Elevation & Depth

Le système est **structuré et surélevé**, mais sa profondeur n’est pas encore harmonisée. Les surfaces utilisent à la fois bordures, dégradés, ombres diffuses, bandes latérales et translations au survol. La direction future confirmée privilégie une profondeur fonctionnelle et retenue.

### Shadow Vocabulary

- **En-tête principal** (`0 28px 70px rgba(8, 35, 61, .20)`) : grand bandeau bleu du portail.
- **Carte premium** (`0 16px 38px rgba(15, 23, 42, .10)`) : sections, KPI et cartes structurantes.
- **Carte canonique antérieure** (`0 18px 45px rgba(15, 23, 42, .10)`) : wrapper et barre d’enregistrement.
- **Carte légère** (`0 8px 24px rgba(15, 23, 42, .06)`) : cartes ordinaires du portail.
- **Administration Clubs** (`0 8px 24px rgba(18, 60, 124, .09)`) : KPI et surfaces de la liste des clubs.
- **Administration compacte** (`0 1px 3px rgba(15, 76, 129, .08)`) : cartes et panneaux de gestion génériques.
- **Focus historique** : halos bleus ou turquoise par `box-shadow`, en concurrence avec le contour ambre final.

Les anciennes cartes appliquent encore une translation verticale de 2 px et une ombre renforcée au survol. Le contrat final des boutons applique une translation de 1 px au survol puis 1 px vers le bas à l’état actif selon la règle gagnante.

**The Structural Depth Rule.** Les ombres servent à distinguer une zone de travail, une barre persistante ou un état interactif ; elles ne doivent pas ajouter une seconde décoration à une bordure et un dégradé déjà suffisants.

## Shapes

Le langage de forme mélange trois générations : coins WordPress compacts, cartes intermédiaires et grandes surfaces premium.

- **4–6 px** : formulaires, badges et boutons historiques.
- **7–8 px** : boutons, messages, pagination et contrôles du contrat final.
- **10–14 px** : filtres, champs récents, cartes de lignes et petits panneaux.
- **18–20 px** : cartes et KPI du portail.
- **22–28 px** : héros et grandes sections premium.
- **999 px** : badges, anciennes navigations, barres et boutons premium antérieurs.
- **50 %** : points de statut, avatars et marqueurs circulaires.

Les bordures sont généralement de 1 px sur les surfaces et de 2 px sur les boutons canoniques. Les cartes KPI utilisent souvent une bande gauche de 4 à 6 px pour communiquer un état. Le contrat final ramène les boutons du portail à 8 px malgré une règle premium antérieure à 999 px ; les deux intentions restent visibles dans le fichier.

**The Compact-Control Rule.** Les contrôles d’action utilisent la géométrie compacte du contrat final ; les pilules restent réservées aux badges et aux éléments réellement catégoriels.

## Components

### Buttons

**Caractère :** explicites, compacts et confiants, avec un contraste fort et une cible minimale claire dans le portail.

- **Primaire — normal :** fond Bleu fédéral, texte blanc, bordure assortie, rayon 8 px, hauteur minimale 44 px et padding 10 × 18 px.
- **Secondaire — normal :** fond gris très pâle, texte et bordure Bleu de contrôle.
- **Danger — normal :** fond blanc, texte et bordure rouges ; il n’est pas rempli tant qu’il n’est pas survolé.
- **Survol :** fond Bleu de profondeur très sombre, texte blanc ; certaines générations ajoutent `translateY(-1px)`.
- **Focus visible :** contour ambre de 3 px avec décalage de 2 px dans le contrat final. Des halos bleus ou turquoise plus anciens subsistent ailleurs.
- **Actif :** translation verticale de 1 px dans la règle générale du portail ; le contrat final partage aussi le fond sombre du survol.
- **Désactivé :** fond gris clair, texte gris sombre, curseur interdit et opacité 1 dans le contrat final.
- **Variations existantes :** rayon 4 ou 6 px dans les anciens formulaires, 999 px dans la génération premium, hauteurs 28–38 px dans plusieurs tables et écrans d’administration.

### Cards / Containers

**Caractère :** surfaces blanches bordées, parfois légèrement teintées, organisées autour de la tâche.

- **Grandes sections :** rayon 28 px, bordure bleu-gris et ombre premium.
- **Cartes ordinaires :** rayons de 18 à 20 px dans le portail récent ; 4 à 12 px dans les couches historiques et l’administration.
- **Padding :** généralement 16–24 px ; les héros utilisent des valeurs fluides allant jusqu’à 46 px.
- **Survol historique :** translation de 2 px et ombre renforcée sur toute `.ufsc-card`, y compris lorsque la carte n’est pas une action.

### KPI Cards

- **Portail :** valeur très grasse, label compact, bordure latérale colorée, rayon 20 px et hauteur minimale constatée de 112, 126 ou 132 px selon la génération.
- **Administration Clubs :** carte d’au moins 116 px avec bande gauche de 6 px et valeur de 29–36 px.
- **Administration générique :** carte d’au moins 112 px, valeur de 30 px et description de 12 px.
- **Risque :** la coexistence de plusieurs hauteurs minimales et tailles de valeur produit une densité variable entre écrans.

### Inputs / Fields

**Caractère :** champs pleine largeur, labels denses et structure de formulaire en grille.

- **Portail canonique :** hauteur minimale 48 px, rayon 14 px, bordure bleu-gris et fond blanc.
- **Renouvellement :** hauteur minimale 44 px.
- **Anciennes couches :** 34, 38, 40 ou 42 px, rayons de 3 à 10 px et paddings différents.
- **Focus :** contour ambre dans le portail final ; halo ou bordure bleue dans les formulaires historiques ; une feuille d’ajout de licence applique aussi des règles globales de focus.
- **Erreur :** bordure rouge, message rouge explicite et parfois halo rouge.
- **Lecture seule :** fond gris très pâle et texte secondaire dans les anciennes feuilles.
- **Textarea :** hauteur minimale de 110 px dans le portail, redimensionnement vertical.

### Navigation

- **Compte Club :** navigation collante, six entrées sur grand écran, trois à 900 px et deux à 600 px dans le contrat final.
- **État normal :** surface claire, texte Bleu de contrôle, bordure visible et cible de 44 px.
- **État courant :** fond Bleu de contrôle et texte blanc via `aria-current="page"`.
- **Anciennes variantes :** pilules à 999 px, navigation flex avec scroll horizontal ou onglets à bordure inférieure.
- **Admin :** navigation rapide en pilules compactes de 28–30 px, donc sous l’objectif de cible 44 × 44 px.

### Tables

- **Portail — licences courantes :** en-tête gris pâle, texte sombre, lignes alternées et survol bleu pâle. À 900 px, chaque ligne devient une carte avec libellés générés par `data-label`.
- **Portail — renouvellement :** colonnes à largeurs fixes, boutons de 44 px, transformation en cartes à 768/780 px.
- **Portail — archives :** tableau large dans un conteneur horizontal ; une aide de défilement apparaît sur petit écran.
- **Admin — clubs :** en-tête Bleu admin, texte blanc forcé, largeur minimale de 1040 à 1180 px, colonnes d’action de 220 à 285 px.
- **Admin — licences :** en-tête collant gris pâle, largeur minimale 1180 px et actions de 30 px.

### Badges / Status

- Forme généralement en pilule, graisse forte et taille de 11–12 px.
- Le portail possède des variantes pleines ; les tables et l’administration utilisent aussi des variantes pastel avec texte sombre et point coloré.
- Plusieurs nomenclatures coexistent : suffixes `success/warning/info/danger`, états `valid/pending/rejected/draft`, modificateurs préfixés par tiret et doubles tirets.
- Les états ne doivent pas dépendre du seul code couleur ; le libellé visible reste obligatoire.

### Messages

- Bloc de 12–16 px de padding, rayon 4–8 px, bordure complète ou bande gauche de 4 px.
- Variantes information, avertissement, erreur et succès avec fond pâle et texte sombre.
- Plusieurs générations Bootstrap-like et Slate-like coexistent avec des valeurs différentes.

### Forms and Wizards

- Les formulaires Club utilisent deux colonnes, puis une sur tablette/mobile.
- Le bloc Dirigeants utilise trois sous-cartes, puis deux et une.
- L’assistant de licence comporte six étapes de 44 px ; sur mobile, les étapes deviennent une rangée horizontale défilante.
- Les actions finales deviennent pleine largeur sous 600–782 px selon la feuille chargée.
- Plusieurs templates contiennent encore des `style="display:none"` ou des dimensions inline, ce qui déplace une partie du système hors des feuilles CSS.

### WooCommerce Boundary

- Les boutons « Ajouter au panier », « Finaliser mon paiement » et « Voir panier » utilisent les composants UFSC tant qu’ils restent dans le portail.
- Le panier et le checkout ne sont pas redéfinis dans les feuilles analysées ; ils conservent les composants WooCommerce, Astra et Elementor.
- Les sélecteurs génériques `.button` sous le wrapper premium peuvent néanmoins modifier un bouton WordPress ou WooCommerce injecté à l’intérieur du portail.

## Do's and Don'ts

### Do:

- **Do** conserver les intitulés métier visibles existants ; « La Console des clubs » reste une North Star documentaire.
- **Do** garder tous les nouveaux styles sous une racine UFSC explicite.
- **Do** préserver une cible interactive d’au moins 44 × 44 px pour les parcours Club.
- **Do** associer chaque couleur d’état à un libellé, une icône ou une structure explicite.
- **Do** vérifier chaque état normal, survol, focus, actif et désactivé sur fond réel.
- **Do** maintenir une densité professionnelle et compacte, sans grande hauteur fixe ni espace vide non fonctionnel.
- **Do** vérifier les adaptations à partir de 360 px, sur tablette, desktop et grand écran avant de déclarer la conformité.
- **Do** laisser WooCommerce, Astra et Elementor gouverner leurs surfaces hors du portail.

### Don't:

- **Don't** ajouter une nouvelle génération visuelle sur les mêmes classes sans retirer ou isoler l’ancienne.
- **Don't** utiliser `!important` comme mécanisme normal de composition.
- **Don't** masquer un débordement ou un défaut fonctionnel avec `overflow-x: hidden` sans en traiter la cause.
- **Don't** appliquer de règles globales à `a`, `button`, `input`, `table`, `.wrap`, `.wp-list-table` ou `.form-control` depuis une feuille destinée à un shortcode.
- **Don't** utiliser le Turquoise d’action ou les bleus historiques clairs comme petit texte sur blanc.
- **Don't** supposer qu’une couleur, une icône seule ou un effet au survol est perceptible par tous les utilisateurs.
- **Don't** revendiquer une validation navigateur ou WCAG complète à partir de cette analyse statique.

### Incohérences constatées

1. **Plusieurs systèmes chargés simultanément.** `assets/frontend/css/frontend.css` puis `assets/css/ufsc-front.css` redéfinissent les mêmes cartes, boutons, grilles, formulaires, navigation, badges et tables.
2. **Trois générations premium dans une seule feuille.** `ufsc-front.css` contient un redesign, un second passage cockpit, une V3, une stabilisation, un système Compte Club, un passage P0 et un contrat correctif final.
3. **Tokens redéfinis.** `--ufsc-primary`, `--ufsc-text`, `--ufsc-border`, `--ufsc-radius` et `--ufsc-shadow` changent de valeur entre `:root`, `.ufsc-club-portal`, `.ufsc-club-account` et `.ufsc-premium-v3`.
4. **Couleurs historiques concurrentes.** Les bleus WordPress, Bootstrap-like, anciens UFSC et portail canonique coexistent.
5. **Rayons incompatibles.** Un même bouton peut recevoir successivement 4, 6, 7, 8 ou 999 px.
6. **Focus non unifié.** Contour ambre, contour bleu, contour noir, halo turquoise et halo bleu apparaissent selon la feuille et la spécificité.
7. **Hauteurs de contrôle inégales.** Le portail final atteint 44–48 px, mais les actions de tables et la navigation admin descendent à 28–38 px.
8. **Contrastes insuffisants potentiels.** Plusieurs paires historiques restent sous 4,5:1 pour du texte normal ; aucune validation de styles calculés n’a été réalisée.
9. **Cause possible de texte invisible : cascade des boutons.** Des règles historiques posent `color: #fff` avant qu’un fond ne soit garanti, puis Astra, Elementor ou une règle UFSC ultérieure peut remplacer le fond.
10. **Cause possible de texte invisible : texte WebKit forcé.** Les correctifs `-webkit-text-fill-color` protègent certains boutons, mais peuvent diverger de `color` si une variante future change seulement l’un des deux.
11. **Cause possible de texte invisible : héritage d’en-tête WordPress.** La table Clubs force fond et texte avec 15 occurrences de `!important`, signe d’un conflit réel avec les styles de tables administratives.
12. **Feuille d’ajout de licence syntaxiquement fragile.** `assets/css/ufsc-frontend.css` présente des blocs apparemment non fermés autour des transitions globales et de `.ufsc-btn-primary`; selon le moteur CSS, des règles peuvent être imbriquées, ignorées ou interprétées différemment.
13. **Règles globales dans une feuille de shortcode.** Cette même feuille cible directement `a`, `button`, `input`, `select`, `textarea`, `[tabindex]` et `table tr`.
14. **Sélecteurs admin trop larges.** `assets/admin/css/admin.css` cible `.wrap`, `.wp-list-table`, `.form-control`, `.custom-file`, `.activity-item` et `.sr-only` sans racine UFSC systématique. Le chargement est limité aux pages UFSC, mais les composants WordPress présents sur ces pages peuvent être affectés.
15. **Breakpoints éclatés.** 768, 780 et 782 px décrivent presque le même seuil avec des comportements différents ; 600, 640, 680 et 720 px ajoutent d’autres replis concurrents.
16. **Variables dans les requêtes média.** Les requêtes `@media (min-width: var(--tablet))` et similaires ne constituent pas un breakpoint CSS fiable.
17. **Largeurs de conteneur concurrentes.** 1200, 1280, 1320, 1360, 1540 et 1680 px sont utilisés comme maxima selon la couche.
18. **Débordement parfois masqué.** Certains wrappers appliquent `overflow-x: hidden` tandis que les tableaux internes imposent de grandes largeurs minimales.
19. **Tables responsive non uniformes.** Les licences courantes deviennent des cartes à 900 px, le renouvellement à 768/780 px, les archives restent défilantes et l’administration reste en tableau large.
20. **Hauteurs fixes et minimales multiples.** KPI à 112/116/126/128/132 px, graphiques à 300 px, canvas à 400 px maximum et logos à 72/76/96/112 px.
21. **Effets non liés à l’interactivité.** La règle historique `.ufsc-card:hover` soulève toute carte, même lorsqu’elle n’est pas cliquable.
22. **Inline styles nombreux.** Les écrans administratifs, KPI, graphiques, formulaires conditionnels et diagnostics transportent encore couleurs, grilles, dimensions et espacements directement dans le PHP.
23. **Templates visuels non référencés directement.** `templates/frontend/club-dashboard.php`, `templates/frontend/licence-form.php` et `templates/front/dashboard-club.php` ne sont pas inclus explicitement par le code PHP analysé, alors que `templates/frontend/licences-list.php` l’est ; ils constituent donc des sources historiques ou alternatives à confirmer avant toute modification.
24. **CSS inline dans un template.** `templates/front/dashboard-club.php` contient sa propre grille et n’affiche les actions documentaires qu’au survol, ce qui ne convient ni au tactile ni au clavier si ce template est actif.
25. **Déclaration CSS invalide observée.** `justify-content: between` apparaît dans la feuille frontend historique.
26. **Poids typographiques non garantis.** Les graisses 750, 850 et 900 dépendent de la police héritée et peuvent être synthétisées.
27. **Dépendance à `:has()`.** L’élargissement Elementor s’appuie sur ce sélecteur ; aucune solution de repli équivalente n’est documentée dans la feuille.
28. **Styles WooCommerce partiels.** Les actions UFSC sont documentées, mais le panier et le checkout n’ont pas de contrat visuel UFSC propre dans les sources analysées.

### Sources analysées

**Contexte et chargement**

- `PRODUCT.md`
- `ufsc-clubs-licences-sql.php`
- `includes/admin/class-admin-menu.php`
- `includes/frontend/class-frontend-shortcodes.php`

**Feuilles du portail et des formulaires**

- `assets/css/ufsc-front.css`
- `assets/frontend/css/frontend.css`
- `assets/css/ufsc-frontend.css`
- `assets/css/ufsc-licence-form.css`
- `assets/css/ufsc-login-form.css`
- `assets/css/frontend-dashboard.css`
- `assets/frontend/css/ufsc-club-form.css`
- `assets/frontend/css/ufsc-front.css`

**Feuilles d’administration**

- `assets/admin/css/admin.css`
- `assets/css/ufsc-admin.css`
- `assets/admin/css/ufsc-clubs-admin.css`
- `assets/admin/css/user-club-admin.css`
- `includes/communication/assets/admin-communication.css`

**Templates et structures PHP**

- `templates/frontend/club-dashboard.php`
- `templates/frontend/licences-list.php`
- `templates/frontend/licence-form.php`
- `templates/front/dashboard-club.php`
- `templates/partials/notice.php`
- `includes/frontend/class-auth-shortcodes.php`
- `includes/frontend/class-club-form.php`
- `includes/frontend/class-affiliation-form.php`
- `includes/admin/class-sql-admin.php`
- `includes/admin/list-tables/class-ufsc-clubs-list-table.php`
- `includes/admin/list-tables/class-ufsc-licences-list-table.php`
- `includes/admin/class-user-club-admin.php`
- `includes/communication/class-ufsc-mail-service.php`
- `inc/admin/menu.php`
- `inc/woocommerce/settings-woocommerce.php`
- `inc/woocommerce/hooks.php`

L’analyse est statique. Aucun navigateur, viewport réel, thème Astra actif, page Elementor, panier WooCommerce ou état authentifié n’a été exécuté pour cette documentation.
