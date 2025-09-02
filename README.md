# UFSC – Clubs & Licences (SQL)

Plugin WordPress pour la gestion complète des clubs et licences UFSC - Saison 2025-2026

## 📋 Présentation

Ce plugin offre une solution complète pour la gestion des clubs et licences de l'UFSC (Union Française des Sports de Combat), intégrant WooCommerce pour la gestion des quotas, statuts et processus de validation.

### Fonctionnalités principales

- **Gestion des clubs** : Création, modification, validation avec mapping complet SQL
- **Gestion des licences** : Formulaires complets admin et front-end
- **Intégration WooCommerce** : Gestion des quotas et processus de commande
- **Import/Export CSV** : Gestion des données avec séparateur `;` et encodages multiples
- **Système de statuts** : Workflow complet de validation (en_attente, valide, a_regler, desactive)
- **Notifications email** : Alertes automatiques selon les statuts
- **Audit et traçabilité** : Suivi complet des modifications
- **Cache des statistiques** : Performance optimisée pour les tableaux de bord
- **Shortcodes front-end** : Interface utilisateur riche
- **Multilingue (i18n)** et accessibilité (a11y)

## 🔧 Prérequis

- WordPress 6.0+
- PHP 7.4+
- WooCommerce 5.0+ (pour les fonctionnalités e-commerce)
- Base de données MySQL avec tables clubs et licences existantes

## 📦 Installation

1. **Télécharger le plugin**
   ```bash
   # Cloner le dépôt ou télécharger le ZIP
   wget https://github.com/DavyReactiv/PLUGIN-UFSC-GESTION-SEPT2025/archive/main.zip
   ```

2. **Installation WordPress**
   - Aller dans `Extensions > Ajouter > Téléverser une extension`
   - Sélectionner le fichier ZIP
   - Cliquer sur "Installer maintenant"
   - Activer le plugin

3. **Configuration initiale**
   - Aller dans `UFSC – Gestion > Paramètres`
   - Configurer les noms des tables SQL existantes
   - Définir les IDs des produits WooCommerce
   - Paramétrer les quotas par région
   - Configurer la saison actuelle (2025-2026)

## ⚙️ Configuration

### Tables SQL

Le plugin se connecte à vos tables existantes :

```sql
-- Table des clubs (configurable)
wp_ufsc_clubs (par défaut)

-- Table des licences (configurable) 
wp_ufsc_licences (par défaut)
-- Note: Colonne 'status' ajoutée pour la gestion des statuts
```

### IDs Produits WooCommerce

Configurer dans `Paramètres > WooCommerce` :
- ID produit licence club
- ID produit licence individuelle
- ID produit renouvellement

### Quotas par région

```php
// Exemple de configuration
$quotas = array(
    'UFSC ILE-DE-FRANCE' => 100,
    'UFSC NORMANDIE' => 75,
    'UFSC PACA' => 80
);
```

## 🎯 Shortcodes Front-end

### Tableau de bord club

```php
[ufsc_sql_my_club]
```
Affiche la carte récapitulative du club lié au responsable connecté.

### Formulaire de licence

```php
[ufsc_sql_licence_form]
```
Formulaire complet de demande de licence (statut par défaut : `en_attente`).

### Liste des clubs par région

```php
[ufsc_clubs_region region="UFSC ILE-DE-FRANCE" limite="10"]
```

## 📊 Import/Export CSV

### Format d'import

- **Séparateur** : `;` (point-virgule)
- **Encodages supportés** : UTF-8, ISO-8859-1, Windows-1252
- **Région** : Reprise automatiquement depuis le club associé

### Champs requis

**Clubs :**
```csv
nom;region;adresse;code_postal;ville;email;telephone;president_nom;president_email
```

**Licences :**
```csv
nom;prenom;email;telephone;club_id;statut;date_naissance
```

## 📧 Notifications Email

### Déclencheurs automatiques

- **Nouvelle licence** : Notification au club et à l'admin
- **Changement de statut** : Alerte selon workflow
- **Quota atteint** : Notification région
- **Validation admin** : Confirmation utilisateur

### Templates personnalisables

Les templates sont dans `templates/emails/` et peuvent être surchargés dans le thème.

## 📈 Statuts et Workflow

### Statuts disponibles

- `en_attente` : Demande soumise, en attente de validation
- `valide` : Licence validée et active
- `a_regler` : Problème administratif à résoudre
- `desactive` : Licence suspendue ou expirée

### Restrictions d'édition

Après validation admin, l'édition front-end est restreinte pour maintenir l'intégrité des données.

## 🚀 Cache et Performance

### Cache des statistiques

```php
// Cache transient pour les KPI (1 heure)
$stats = get_transient('ufsc_dashboard_stats');
```

### Optimisations

- Requêtes SQL optimisées avec index
- Cache des régions et paramètres
- Lazy loading des assets front-end

## 🔍 Audit et Traçabilité

### Custom Post Type Audit

Toutes les modifications importantes sont tracées via un CPT dédié :

```php
// Enregistrement automatique
ufsc_log_audit($action, $object_type, $object_id, $details);
```

## 🌐 Internationalisation et Accessibilité

### i18n (Multilingue)

- Text Domain : `ufsc-clubs`
- Fichiers PO/MO dans `languages/`
- Traductions FR complètes incluses

### a11y (Accessibilité)

- Navigation clavier complète
- Labels ARIA appropriés
- Contraste couleurs conforme WCAG 2.1
- Structure sémantique HTML5

## 🏗️ Structure du Plugin

```
ufsc-clubs-licences-sql.php    # Fichier principal
assets/
├── admin/                     # CSS/JS administration
└── frontend/                  # CSS/JS front-end
inc/
├── admin/                     # Menus et interfaces admin
├── common/                    # Modules partagés (régions, tables)
├── woocommerce/              # Intégration WooCommerce
├── settings.php              # Gestion des paramètres
└── form-license-sanitizer.php # Validation des données
includes/
├── core/                     # Classes utilitaires et SQL
├── admin/                    # Interface d'administration
└── frontend/                 # Shortcodes et formulaires
templates/                    # Templates (si présents)
languages/                    # Fichiers de traduction
docs/                        # Documentation technique
tests/                       # Tests d'intégration
examples/                    # Exemples d'extension
```

## 🔄 Migration Status

### Changements structurels

Cette version inclut un aplatissement de la structure du plugin :
- **Avant** : Fichiers dans `UFSC_Clubs_Licences_v1_5_3f_SQL/`
- **Après** : Fichiers directement à la racine du plugin

### Colonne SQL ajoutée

```sql
ALTER TABLE wp_ufsc_licences ADD COLUMN status VARCHAR(20) DEFAULT 'en_attente';
```

Cette migration est automatique lors de l'activation du plugin.

## 🧪 Tests et Outillage

### Tests d'intégration

```bash
# Lancer les tests
cd tests/
php integration-test.php
```

### Outils de développement

- Validation des données avec sanitizers
- Logs de debug sécurisés
- Hooks d'extensibilité pour développeurs

## 📚 Documentation Technique

Pour la documentation technique complète, consultez :
- [`docs/UFSC_GESTION_DOCUMENTATION.md`](docs/UFSC_GESTION_DOCUMENTATION.md) - Architecture et API
- [`CHANGELOG.md`](CHANGELOG.md) - Historique des versions
- [`examples/extension-example.php`](examples/extension-example.php) - Exemples d'extension

## 🆘 Support

### Problèmes courants

1. **Plugin ne s'active pas** : Vérifier les prérequis WordPress et PHP
2. **Tables non trouvées** : Configurer les noms de tables dans Paramètres
3. **Erreurs WooCommerce** : Vérifier que WooCommerce est activé et configuré

### Logs de debug

Activer les logs dans `wp-config.php` :
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 👥 Développement

### Contribution

1. Fork du projet
2. Créer une branche feature (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit des changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Créer une Pull Request

### Standards de code

- PSR-4 pour l'autoloading
- WordPress Coding Standards
- Documentation PHPDoc complète

---

**Version** : 1.5.3ff  
**Auteur** : Davy – Studio REACTIV (pour l'UFSC)  
**Licence** : GPLv2 or later  
**Saison** : 2025-2026