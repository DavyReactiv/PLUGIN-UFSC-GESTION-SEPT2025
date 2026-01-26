# UFSC Gestion - Documentation Technique Détaillée

## 📐 Architecture du Plugin

### Vue d'ensemble

Le plugin UFSC Gestion suit une architecture modulaire basée sur les principes suivants :
- Séparation des responsabilités
- Extensibilité via hooks WordPress
- Intégration native avec l'écosystème WordPress
- Performance optimisée avec système de cache

### Structure des modules

```
Core (includes/core/)
├── class-utils.php           # Utilitaires et validations
├── class-sql.php            # Couche d'abstraction SQL
├── class-uploads.php        # Gestion des fichiers
└── class-permissions.php    # Contrôle d'accès

Admin (includes/admin/ + inc/admin/)
├── class-admin-menu.php     # Menu principal
├── class-sql-admin.php      # Interface CRUD
└── menu.php                 # Pages d'administration

Frontend (includes/frontend/)
├── class-sql-shortcodes.php # Shortcodes publics
├── class-club-form.php      # Formulaires front
└── class-club-form-handler.php # Traitement des données

WooCommerce (inc/woocommerce/)
├── settings-woocommerce.php # Configuration
├── hooks.php               # Intégration commandes
├── admin-actions.php       # Actions administrateur
└── cart-integration.php    # Intégration panier

Common (inc/common/)
├── regions.php             # Gestion des régions
└── tables.php             # Helpers tables SQL
```

## ⚙️ Configuration et Réglages

### Système de settings

Le plugin utilise l'API Options de WordPress pour persister la configuration :

```php
// Structure des settings principaux
$ufsc_settings = array(
    'table_clubs' => 'wp_ufsc_clubs',
    'table_licences' => 'wp_ufsc_licences',
    'saison_courante' => '2025-2026',
    'quota_global' => 1000,
    'quota_par_region' => array(
        'UFSC ILE-DE-FRANCE' => 100,
        'UFSC NORMANDIE' => 75,
        // ...
    ),
    'woocommerce' => array(
        'product_licence_club' => 123,
        'product_licence_individuelle' => 124,
        'product_renouvellement' => 125
    ),
    'notifications' => array(
        'admin_email' => 'admin@ufsc.fr',
        'enable_club_notifications' => true,
        'enable_status_notifications' => true
    )
);
```

### Gestion des régions

Module centralisé dans `inc/common/regions.php` :

```php
/**
 * Liste unifiée des régions UFSC
 */
function ufsc_get_regions_labels() {
    return array(
        'UFSC ILE-DE-FRANCE' => 'Île-de-France',
        'UFSC NORMANDIE' => 'Normandie',
        'UFSC HAUTS-DE-FRANCE' => 'Hauts-de-France',
        'UFSC GRAND EST' => 'Grand Est',
        'UFSC BRETAGNE' => 'Bretagne',
        'UFSC PAYS DE LA LOIRE' => 'Pays de la Loire',
        'UFSC CENTRE-VAL DE LOIRE' => 'Centre-Val de Loire',
        'UFSC BOURGOGNE-FRANCHE-COMTE' => 'Bourgogne-Franche-Comté',
        'UFSC AUVERGNE-RHONE-ALPES' => 'Auvergne-Rhône-Alpes',
        'UFSC NOUVELLE-AQUITAINE' => 'Nouvelle-Aquitaine',
        'UFSC OCCITANIE' => 'Occitanie',
        'UFSC PACA' => 'Provence-Alpes-Côte d\'Azur',
        'UFSC CORSE' => 'Corse',
        'UFSC OUTRE-MER' => 'Outre-mer'
    );
}
```

## 🎨 Interface Front-end

### Tableau de bord club

Le shortcode `[ufsc_sql_my_club]` génère une interface riche :

```php
class UFSC_SQL_Shortcodes {
    public static function my_club_shortcode($atts) {
        // Récupération du club lié à l'utilisateur connecté
        $user_id = get_current_user_id();
        $club = self::get_user_club($user_id);
        
        if (!$club) {
            return '<div class="ufsc-notice error">Aucun club associé</div>';
        }
        
        // Interface avec statistiques et actions
        $output = '<div class="ufsc-club-dashboard">';
        $output .= self::render_club_header($club);
        $output .= self::render_club_stats($club);
        $output .= self::render_quick_actions($club);
        $output .= '</div>';
        
        return $output;
    }
}
```

### Restrictions d'édition après validation

```php
/**
 * Vérification des permissions d'édition
 */
function ufsc_can_edit_licence($licence_id) {
    $licence = ufsc_get_licence($licence_id);
    
    // Restriction après validation admin
    if ($licence->status === 'valide' && !current_user_can('manage_options')) {
        return false;
    }
    
    return true;
}
```

## 📥 Import/Export CSV

### Traitement des encodages

```php
class UFSC_CSV_Handler {
    /**
     * Détection automatique et conversion d'encodage
     */
    public static function detect_and_convert_encoding($content) {
        $encodings = array('UTF-8', 'ISO-8859-1', 'Windows-1252');
        $detected = mb_detect_encoding($content, $encodings, true);
        
        if ($detected && $detected !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $detected);
        }
        
        return $content;
    }
    
    /**
     * Région reprise automatiquement depuis le club
     */
    public static function import_licences_csv($file_path) {
        $csv = file_get_contents($file_path);
        $csv = self::detect_and_convert_encoding($csv);
        
        $lines = str_getcsv($csv, "\n");
        foreach ($lines as $line) {
            $data = str_getcsv($line, ";"); // Séparateur point-virgule
            
            // Récupération automatique de la région depuis le club
            if (!empty($data['club_id'])) {
                $club = ufsc_get_club($data['club_id']);
                $data['region'] = $club->region ?? '';
            }
            
            ufsc_import_licence($data);
        }
    }
}
```

## 📊 Système de Statuts

### Workflow des licences

```php
/**
 * Machine à états pour les licences
 */
class UFSC_Status_Manager {
    const STATUS_EN_ATTENTE = 'en_attente';
    const STATUS_VALIDE = 'valide';
    const STATUS_A_REGLER = 'a_regler';
    const STATUS_DESACTIVE = 'desactive';
    
    /**
     * Transitions autorisées
     */
    public static function get_allowed_transitions($current_status) {
        $transitions = array(
            self::STATUS_EN_ATTENTE => array(self::STATUS_VALIDE, self::STATUS_A_REGLER),
            self::STATUS_VALIDE => array(self::STATUS_A_REGLER, self::STATUS_DESACTIVE),
            self::STATUS_A_REGLER => array(self::STATUS_VALIDE, self::STATUS_DESACTIVE),
            self::STATUS_DESACTIVE => array(self::STATUS_VALIDE)
        );
        
        return $transitions[$current_status] ?? array();
    }
    
    /**
     * Changement de statut avec hooks
     */
    public static function change_status($licence_id, $new_status, $reason = '') {
        $licence = ufsc_get_licence($licence_id);
        $old_status = $licence->status;
        
        // Vérification de la transition
        if (!in_array($new_status, self::get_allowed_transitions($old_status))) {
            return new WP_Error('invalid_transition', 'Transition non autorisée');
        }
        
        // Hook avant changement
        do_action('ufsc_before_status_change', $licence_id, $old_status, $new_status);
        
        // Mise à jour
        ufsc_update_licence_status($licence_id, $new_status);
        
        // Log d'audit
        ufsc_log_audit('status_change', 'licence', $licence_id, array(
            'old_status' => $old_status,
            'new_status' => $new_status,
            'reason' => $reason
        ));
        
        // Hook après changement
        do_action('ufsc_after_status_change', $licence_id, $old_status, $new_status);
        
        return true;
    }
}
```

## 📧 Notifications Email

### Système de templates

```php
class UFSC_Email_Manager {
    /**
     * Envoi de notification avec template
     */
    public static function send_notification($type, $recipient, $data) {
        $template = self::get_template($type);
        $subject = self::process_template($template['subject'], $data);
        $message = self::process_template($template['content'], $data);
        
        // Headers avec From personnalisé
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: UFSC <noreply@ufsc.fr>'
        );
        
        return wp_mail($recipient, $subject, $message, $headers);
    }
    
    /**
     * Templates configurables
     */
    private static function get_template($type) {
        $templates = array(
            'licence_created' => array(
                'subject' => 'Nouvelle demande de licence - {{club_name}}',
                'content' => 'templates/emails/licence-created.html'
            ),
            'status_changed' => array(
                'subject' => 'Statut de licence modifié - {{licence_number}}',
                'content' => 'templates/emails/status-changed.html'
            )
        );
        
        return $templates[$type] ?? null;
    }
}
```

## 🔌 API REST et Endpoints

### Endpoints personnalisés

```php
/**
 * Enregistrement des routes REST
 */
add_action('rest_api_init', function() {
    register_rest_route('ufsc/v1', '/clubs', array(
        'methods' => 'GET',
        'callback' => 'ufsc_rest_get_clubs',
        'permission_callback' => 'ufsc_rest_permissions'
    ));
    
    register_rest_route('ufsc/v1', '/licences/(?P<id>\d+)', array(
        'methods' => 'PUT',
        'callback' => 'ufsc_rest_update_licence',
        'permission_callback' => 'ufsc_rest_permissions'
    ));
});

/**
 * Endpoint pour récupération des clubs
 */
function ufsc_rest_get_clubs($request) {
    $region = $request->get_param('region');
    $status = $request->get_param('status');
    
    $clubs = ufsc_get_clubs(array(
        'region' => $region,
        'status' => $status
    ));
    
    return rest_ensure_response($clubs);
}
```

## 🛒 Hooks WooCommerce

### Intégration commandes

```php
class UFSC_WooCommerce_Hooks {
    /**
     * Hook après finalisation de commande
     */
    public static function order_completed($order_id) {
        $order = wc_get_order($order_id);
        
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            
            // Traitement selon le type de produit
            if (self::is_licence_product($product_id)) {
                self::process_licence_order($order, $item);
            }
        }
    }
    
    /**
     * Gestion des quotas au panier
     */
    public static function check_quota_before_add_to_cart($passed, $product_id) {
        if (!self::is_licence_product($product_id)) {
            return $passed;
        }
        
        $user_club = ufsc_get_user_club(get_current_user_id());
        if (!$user_club) {
            wc_add_notice('Vous devez être associé à un club', 'error');
            return false;
        }
        
        $quota_disponible = ufsc_get_quota_disponible($user_club->region);
        if ($quota_disponible <= 0) {
            wc_add_notice('Quota épuisé pour votre région', 'error');
            return false;
        }
        
        return $passed;
    }
}
```

## 💾 Cache et Transients

### Cache des statistiques

```php
/**
 * Récupération des KPI avec cache
 */
function ufsc_get_dashboard_stats($force_refresh = false) {
    $cache_key = 'ufsc_dashboard_stats';
    
    if (!$force_refresh) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }
    }
    
    // Calcul des statistiques
    $stats = array(
        'clubs_total' => ufsc_count_clubs(),
        'clubs_actifs' => ufsc_count_clubs(array('status' => 'valide')),
        'licences_total' => ufsc_count_licences(),
        'licences_actives' => ufsc_count_licences(array('status' => 'valide')),
        'quota_utilise' => ufsc_get_quota_utilise(),
        'regions_actives' => count(ufsc_get_active_regions())
    );
    
    // Cache pour 1 heure
    set_transient($cache_key, $stats, HOUR_IN_SECONDS);
    
    return $stats;
}

/**
 * Invalidation du cache lors des modifications
 */
add_action('ufsc_after_club_update', 'ufsc_clear_dashboard_cache');
add_action('ufsc_after_licence_update', 'ufsc_clear_dashboard_cache');

function ufsc_clear_dashboard_cache() {
    delete_transient('ufsc_dashboard_stats');
}
```

## 🔍 Audit et Traçabilité

### Custom Post Type pour l'audit

```php
/**
 * Enregistrement du CPT audit
 */
function ufsc_register_audit_cpt() {
    register_post_type('ufsc_audit', array(
        'label' => 'Audit UFSC',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => 'ufsc-gestion',
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'supports' => array('title', 'editor'),
        'meta_boxes' => array('ufsc_audit_details')
    ));
}

/**
 * Fonction de logging unifiée
 */
function ufsc_log_audit($action, $object_type, $object_id, $details = array()) {
    $post_id = wp_insert_post(array(
        'post_type' => 'ufsc_audit',
        'post_title' => sprintf('%s - %s #%d', ucfirst($action), $object_type, $object_id),
        'post_status' => 'publish',
        'post_content' => json_encode($details)
    ));
    
    if (!is_wp_error($post_id)) {
        update_post_meta($post_id, '_ufsc_audit_action', $action);
        update_post_meta($post_id, '_ufsc_audit_object_type', $object_type);
        update_post_meta($post_id, '_ufsc_audit_object_id', $object_id);
        update_post_meta($post_id, '_ufsc_audit_user_id', get_current_user_id());
        update_post_meta($post_id, '_ufsc_audit_ip', $_SERVER['REMOTE_ADDR']);
    }
    
    return $post_id;
}
```

## 🔄 Migration Status

### Gestion de la colonne status

```php
/**
 * Migration automatique lors de l'activation
 */
register_activation_hook(__FILE__, 'ufsc_plugin_activation');

function ufsc_plugin_activation() {
    global $wpdb;
    
    $table_licences = ufsc_get_licences_table();
    
    // Vérification si la colonne status existe
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SHOW COLUMNS FROM `{$table_licences}` LIKE %s",
            'status'
        )
    );
    
    // Ajout de la colonne si elle n'existe pas
    if (empty($column_exists)) {
        $wpdb->query(
            "ALTER TABLE `{$table_licences}` 
             ADD COLUMN status VARCHAR(20) DEFAULT 'en_attente'"
        );
        
        // Log de la migration
        ufsc_log_audit('migration', 'database', 0, array(
            'table' => $table_licences,
            'action' => 'add_status_column'
        ));
    }
    
    // Mise à jour des licences existantes sans statut
    $wpdb->query(
        "UPDATE `{$table_licences}` 
         SET status = 'valide' 
         WHERE status IS NULL OR status = ''"
    );
}
```

## 🧪 Tests et Qualité

### Tests d'intégration

```php
// tests/integration-test.php
class UFSC_Integration_Tests {
    /**
     * Test de création de club
     */
    public function test_club_creation() {
        $club_data = array(
            'nom' => 'Test Club',
            'region' => 'UFSC ILE-DE-FRANCE',
            'email' => 'test@club.fr'
        );
        
        $club_id = ufsc_create_club($club_data);
        $this->assertNotEmpty($club_id);
        
        $club = ufsc_get_club($club_id);
        $this->assertEquals('Test Club', $club->nom);
    }
    
    /**
     * Test de workflow de statuts
     */
    public function test_status_workflow() {
        $licence_id = $this->create_test_licence();
        
        // Test transition valide
        $result = UFSC_Status_Manager::change_status($licence_id, 'valide');
        $this->assertTrue($result);
        
        // Test transition invalide
        $result = UFSC_Status_Manager::change_status($licence_id, 'en_attente');
        $this->assertInstanceOf('WP_Error', $result);
    }
}
```

## 🌐 Accessibilité et Internationalization

### Standards d'accessibilité

```php
/**
 * Génération de formulaires accessibles
 */
function ufsc_render_form_field($field, $value = '') {
    $field_id = 'ufsc_' . $field['name'];
    $required = $field['required'] ? 'required aria-required="true"' : '';
    
    $output = sprintf(
        '<div class="ufsc-form-field">
            <label for="%s" class="ufsc-label">%s%s</label>
            <input type="%s" id="%s" name="%s" value="%s" 
                   class="ufsc-input" %s aria-describedby="%s-desc">
            <div id="%s-desc" class="ufsc-field-description">%s</div>
         </div>',
        esc_attr($field_id),
        esc_html($field['label']),
        $field['required'] ? ' <span class="required" aria-label="requis">*</span>' : '',
        esc_attr($field['type']),
        esc_attr($field_id),
        esc_attr($field['name']),
        esc_attr($value),
        $required,
        esc_attr($field_id),
        esc_attr($field_id),
        esc_html($field['description'] ?? '')
    );
    
    return $output;
}
```

### Internationalisation

```php
/**
 * Chargement des traductions
 */
add_action('plugins_loaded', 'ufsc_load_textdomain');

function ufsc_load_textdomain() {
    load_plugin_textdomain(
        'ufsc-clubs',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

/**
 * Messages traduits
 */
function ufsc_get_status_label($status) {
    $labels = array(
        'en_attente' => __('En attente', 'ufsc-clubs'),
        'valide' => __('Validé', 'ufsc-clubs'),
        'a_regler' => __('À régler', 'ufsc-clubs'),
        'desactive' => __('Désactivé', 'ufsc-clubs')
    );
    
    return $labels[$status] ?? $status;
}
```

## 🔧 Extensibilité et Hooks

### Hooks pour développeurs

```php
/**
 * Hooks disponibles pour l'extension
 */

// Filtres pour personnaliser les champs
apply_filters('ufsc_club_fields', $fields);
apply_filters('ufsc_licence_fields', $fields);

// Actions pour les événements
do_action('ufsc_club_created', $club_id, $club_data);
do_action('ufsc_licence_validated', $licence_id);
do_action('ufsc_quota_exceeded', $region, $current_count);

// Filtres pour la validation
apply_filters('ufsc_validate_club_data', $errors, $data);
apply_filters('ufsc_validate_licence_data', $errors, $data);
```

### Exemple d'extension

```php
/**
 * Extension pour ajouter des champs personnalisés
 */
add_filter('ufsc_club_fields', 'mon_plugin_custom_club_fields');
function mon_plugin_custom_club_fields($fields) {
    $fields['site_web'] = array(
        'label' => 'Site Web',
        'type' => 'url',
        'required' => false
    );
    
    return $fields;
}

/**
 * Traitement des champs personnalisés
 */
add_action('ufsc_club_created', 'mon_plugin_save_custom_fields', 10, 2);
function mon_plugin_save_custom_fields($club_id, $club_data) {
    if (!empty($club_data['site_web'])) {
        update_post_meta($club_id, '_club_site_web', $club_data['site_web']);
    }
}
```

---

Cette documentation technique couvre tous les aspects avancés du plugin UFSC Gestion. Pour des questions spécifiques ou des besoins d'extension, consultez les exemples fournis ou contactez l'équipe de développement.