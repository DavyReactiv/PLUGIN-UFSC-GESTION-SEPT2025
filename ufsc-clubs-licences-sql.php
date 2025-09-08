<?php
/**
 * Plugin Name: UFSC – Clubs & Licences (SQL)
 * Description: Gestion Clubs/Licences connectée aux tables SQL existantes (mapping complet), formulaires complets (admin & front), documents PDF/JPG/PNG, exports CSV, badges colorés, mini-dashboard, shortcodes.
 * Version: 1.5.8
 * Author: Davy – Studio REACTIV (pour l'UFSC)
 * Text Domain: ufsc-clubs
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'UFSC_CL_VERSION', '1.5.8' );
define( 'UFSC_CL_DIR', plugin_dir_path( __FILE__ ) );
define( 'UFSC_CL_URL', plugin_dir_url( __FILE__ ) );

require_once UFSC_CL_DIR.'includes/core/class-utils.php';
require_once UFSC_CL_DIR.'includes/core/column-map.php';
require_once UFSC_CL_DIR.'includes/admin/class-admin-menu.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-settings-page.php';
require_once UFSC_CL_DIR.'includes/core/class-sql.php';
require_once UFSC_CL_DIR.'includes/admin/class-sql-admin.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-export-base.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-export-clubs.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-export-licences.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-import-csv.php';
require_once UFSC_CL_DIR.'includes/frontend/class-sql-shortcodes.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form-handler.php';
require_once UFSC_CL_DIR.'includes/core/class-uploads.php';
require_once UFSC_CL_DIR.'includes/front/class-ufsc-media.php';
require_once UFSC_CL_DIR.'includes/core/class-permissions.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-badges.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-status.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-pdf-attestations.php';
require_once UFSC_CL_DIR.'includes/lib/class-simple-pdf.php';
require_once UFSC_CL_DIR.'includes/core/class-unified-handlers.php';
require_once UFSC_CL_DIR.'includes/core/class-cache-manager.php';

// New UFSC Gestion enhancement classes
require_once UFSC_CL_DIR.'includes/common/class-ufsc-utils.php';
require_once UFSC_CL_DIR.'includes/common/functions.php';
require_once UFSC_CL_DIR.'includes/common/class-ufsc-cron.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-transaction.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-db-migrations.php';
require_once UFSC_CL_DIR.'includes/frontend/class-affiliation-form.php';
require_once UFSC_CL_DIR.'includes/admin/list-tables/class-ufsc-licences-list-table.php';
require_once UFSC_CL_DIR.'includes/admin/list-tables/class-ufsc-clubs-list-table.php';

require_once UFSC_CL_DIR.'includes/admin/class-ufsc-club-metaboxes.php';

require_once UFSC_CL_DIR.'includes/front/class-ufsc-stats.php';

require_once UFSC_CL_DIR.'includes/front/class-ufsc-licences-table.php';

require_once UFSC_CL_DIR.'includes/front/class-ufsc-licence-form.php';



// New frontend layer components
require_once UFSC_CL_DIR.'includes/frontend/class-frontend-shortcodes.php';
require_once UFSC_CL_DIR.'includes/frontend/class-auth-shortcodes.php';
require_once UFSC_CL_DIR.'includes/front/class-ufsc-documents.php';
require_once UFSC_CL_DIR.'includes/api/class-rest-api.php';
require_once UFSC_CL_DIR.'includes/core/class-audit-logger.php';
require_once UFSC_CL_DIR.'includes/core/class-email-notifications.php';
require_once UFSC_CL_DIR.'includes/core/class-import-export.php';
require_once UFSC_CL_DIR.'includes/core/class-badge-helper.php';
require_once UFSC_CL_DIR.'includes/core/class-user-club-mapping.php';
require_once UFSC_CL_DIR.'includes/admin/class-user-club-admin.php';
require_once UFSC_CL_DIR.'includes/cli/class-wp-cli-commands.php';

// New UFSC Gestion modules
require_once UFSC_CL_DIR.'inc/common/regions.php';
require_once UFSC_CL_DIR.'inc/common/tables.php';
require_once UFSC_CL_DIR.'inc/settings.php';
require_once UFSC_CL_DIR.'inc/form-license-sanitizer.php';
require_once UFSC_CL_DIR.'inc/permissions.php';
require_once UFSC_CL_DIR.'inc/woocommerce/settings-woocommerce.php';
require_once UFSC_CL_DIR.'inc/woocommerce/hooks.php';
require_once UFSC_CL_DIR.'inc/woocommerce/admin-actions.php';
require_once UFSC_CL_DIR.'inc/woocommerce/cart-integration.php';
require_once UFSC_CL_DIR.'includes/woo/class-ufsc-woo-sync.php';

register_activation_hook(__FILE__, ['UFSC_DB_Migrations','activate']);
add_action('plugins_loaded', ['UFSC_DB_Migrations','maybe_upgrade']);

register_activation_hook(__FILE__, function() {
    if ($r = get_role('administrator')) $r->add_cap('ufsc_manage');
});
add_action('admin_init', function() {
    if ($r = get_role('administrator')) $r->add_cap('ufsc_manage');
});

UFSC_Export_Clubs::init();
UFSC_Export_Licences::init();

final class UFSC_CL_Bootstrap {
    private static $instance = null;
    public static function instance(){ if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct(){
        register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );

        add_action( 'admin_enqueue_scripts', array( 'UFSC_CL_Admin_Menu', 'enqueue_admin' ) );

        // SQL Admin CRUD actions (pages cachées mais enregistrées pour les actions directes)
        add_action( 'admin_menu', array( 'UFSC_SQL_Admin', 'register_hidden_pages' ), 99 );
        add_action( 'admin_post_ufsc_sql_save_club', array( 'UFSC_SQL_Admin', 'handle_save_club' ) );
        add_action( 'admin_post_ufsc_sql_delete_club', array( 'UFSC_SQL_Admin', 'handle_delete_club' ) );
        add_action( 'admin_post_ufsc_sql_save_licence', array( 'UFSC_SQL_Admin', 'handle_save_licence' ) );
        add_action( 'admin_post_ufsc_sql_delete_licence', array( 'UFSC_SQL_Admin', 'handle_delete_licence' ) );
        add_action( 'admin_post_ufsc_send_license_payment', array( 'UFSC_SQL_Admin', 'handle_send_license_payment' ) );
        add_action( 'admin_post_ufsc_export_data', array( 'UFSC_SQL_Admin', 'handle_export_data' ) );

        // AJAX handlers
        add_action( 'wp_ajax_ufsc_update_licence_status', array( 'UFSC_SQL_Admin', 'handle_ajax_update_licence_status' ) );
        add_action( 'wp_ajax_ufsc_send_to_payment', array( 'UFSC_SQL_Admin', 'handle_ajax_send_to_payment' ) );

        // Shortcodes front
        add_action( 'init', array( 'UFSC_SQL_Shortcodes', 'register_shortcodes' ) );
        add_action( 'init', array( 'UFSC_Frontend_Shortcodes', 'register' ) );
        add_action( 'init', array( 'UFSC_Auth_Shortcodes', 'register' ) );
        
        // Initialize new UFSC Gestion enhancement components
        add_action( 'init', array( 'UFSC_Affiliation_Form', 'init' ) );
        add_action( 'init', array( 'UFSC_CL_Club_Form', 'init' ) );

        add_action( 'init', array( 'UFSC_Documents', 'init' ) );

        add_action( 'init', array( 'UFSC_Media', 'init' ) );

        add_action( 'init', array( 'UFSC_Unified_Handlers', 'init' ) );
        add_action( 'init', array( 'UFSC_Cache_Manager', 'init' ) );
                
        // Initialize UFSC Gestion WooCommerce hooks
        add_action( 'plugins_loaded', 'ufsc_init_woocommerce_hooks' );
        add_action( 'plugins_loaded', array( 'UFSC_Woo_Sync', 'init' ) );

        // Load translations
        add_action( 'init', array( $this, 'load_textdomain' ) );

        // Initialize frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }
    public function on_activate(){

        if ( ! wp_next_scheduled( 'ufsc_daily' ) ) {
            wp_schedule_event( time(), 'daily', 'ufsc_daily' );
        }
        flush_rewrite_rules();
    }
    public function on_deactivate(){
        $timestamp = wp_next_scheduled( 'ufsc_daily' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'ufsc_daily' );
        }
        flush_rewrite_rules();
    }

    /**
     * Load plugin translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'ufsc-clubs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;

        $should_enqueue = false;
        $shortcodes     = array(
            'ufsc_club_dashboard',
            'ufsc_club_licences',
            'ufsc_club_stats',
            'ufsc_club_profile',
            'ufsc_add_licence',
            'ufsc_licences',
        );

        if ( $post ) {
            foreach ( $shortcodes as $shortcode ) {
                if ( has_shortcode( $post->post_content, $shortcode ) ) {
                    $should_enqueue = true;
                    break;
                }
            }
        }

        if ( ! $should_enqueue && is_user_logged_in() ) {
            if ( function_exists( 'is_account_page' ) && is_account_page() ) {
                $should_enqueue = true;
            } else {
                $should_enqueue = is_page( array( 'tableau-de-bord', 'club-dashboard', 'mon-club', 'mon-compte', 'my-account' ) );
            }
        }

        if ( $should_enqueue ) {
            wp_enqueue_style( 'ufsc-frontend' );
            wp_enqueue_script( 'ufsc-frontend' );
            $this->localize_frontend_scripts();
        }
    }

    /**
     * Localize frontend scripts with data and translations
     */
    private function localize_frontend_scripts() {
        wp_localize_script( 'ufsc-frontend', 'ufsc_frontend_vars', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'rest_url'   => rest_url( 'ufsc/v1/' ),
            'nonce'      => wp_create_nonce( 'ufsc_frontend_nonce' ),
            'rest_nonce' => wp_create_nonce( 'wp_rest' ),
            'strings'    => array(
                'saving'              => __( 'Enregistrement...', 'ufsc-clubs' ),
                'loading'             => __( 'Chargement...', 'ufsc-clubs' ),
                'error'               => __( 'Une erreur est survenue.', 'ufsc-clubs' ),
                'success'             => __( 'Opération réussie.', 'ufsc-clubs' ),
                'confirm_remove_logo' => __( 'Êtes-vous sûr de vouloir supprimer ce logo ?', 'ufsc-clubs' ),
                'invalid_file_type'   => __( 'Type de fichier non autorisé.', 'ufsc-clubs' ),
                'file_too_large'      => __( 'Fichier trop volumineux.', 'ufsc-clubs' ),
                'invalid_email'       => __( 'Adresse email invalide.', 'ufsc-clubs' ),
                'invalid_phone'       => __( 'Numéro de téléphone invalide.', 'ufsc-clubs' ),
                'invalid_postal_code' => __( 'Code postal invalide.', 'ufsc-clubs' ),
                'characters_remaining'=> __( 'caractères restants', 'ufsc-clubs' ),
                'exporting'           => __( 'Export en cours...', 'ufsc-clubs' ),
                'export'              => __( 'Exporter', 'ufsc-clubs' ),
                'import_preview'      => __( 'Prévisualisation de l\'import', 'ufsc-clubs' ),
                'import_errors'       => __( 'Erreurs détectées', 'ufsc-clubs' ),
                'preview_data'        => __( 'Données à importer', 'ufsc-clubs' ),
                'confirm_import'      => __( 'Confirmer l\'import', 'ufsc-clubs' ),
                'confirm_import_action'=> __( 'Êtes-vous sûr de vouloir importer ces données ?', 'ufsc-clubs' ),
                'name'                => __( 'Nom', 'ufsc-clubs' ),
                'first_name'          => __( 'Prénom', 'ufsc-clubs' ),
                'email'               => __( 'Email', 'ufsc-clubs' ),
                'status'              => __( 'Statut', 'ufsc-clubs' ),
                'ajax_error'          => __( 'Erreur de communication avec le serveur.', 'ufsc-clubs' ),
                'logo_preview'        => __( 'Aperçu du logo', 'ufsc-clubs' ),
                'logo_preview_text'   => __( 'Aperçu du logo à télécharger', 'ufsc-clubs' ),
                'choose_logo'         => __( 'Choisir un logo', 'ufsc-clubs' ),
                'logo_help'           => __( 'Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs' ),
                'button_action'       => __( 'Action', 'ufsc-clubs' ),
                'skip_to_nav'         => __( 'Aller à la navigation', 'ufsc-clubs' ),
                'skip_to_content'     => __( 'Aller au contenu', 'ufsc-clubs' ),
            ),
        ) );
    }
}
UFSC_CL_Bootstrap::instance();
