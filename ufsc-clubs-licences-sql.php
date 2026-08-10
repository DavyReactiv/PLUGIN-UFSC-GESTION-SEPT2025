<?php
/**
 * Plugin Name: UFSC – Clubs & Licences (SQL)
 * Description: Gestion Clubs/Licences connectée aux tables SQL existantes (mapping complet), formulaires complets (admin & front), documents PDF/JPG/PNG, exports CSV, badges colorés, mini-dashboard, shortcodes.
 * Version: 042026
 * Author: Davy – Studio REACTIV (pour l'UFSC)
 * Text Domain: ufsc-clubs
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'UFSC_CL_VERSION', '042026' );
define( 'UFSC_CL_DIR', plugin_dir_path( __FILE__ ) );
define( 'UFSC_CL_URL', plugin_dir_url( __FILE__ ) );

/* Expanded by `git archive` for release ZIPs (see .gitattributes). */
define( 'UFSC_CL_BUILD_ARCHIVE', '$Format:%h$' );
if ( ! function_exists( 'ufsc_get_build_id' ) ) {
	function ufsc_get_build_id() {
		$archive_id = UFSC_CL_BUILD_ARCHIVE;
		if ( preg_match( '/^[0-9a-f]{7,40}$/i', $archive_id ) ) {
			return substr( strtolower( $archive_id ), 0, 12 );
		}
		$head_file = UFSC_CL_DIR . '.git/HEAD';
		if ( is_readable( $head_file ) ) {
			$head = trim( (string) file_get_contents( $head_file ) );
			if ( 0 === strpos( $head, 'ref: ' ) ) {
				$ref_file = UFSC_CL_DIR . '.git/' . substr( $head, 5 );
				$head = is_readable( $ref_file ) ? trim( (string) file_get_contents( $ref_file ) ) : '';
			}
			if ( preg_match( '/^[0-9a-f]{7,40}$/i', $head ) ) {
				return substr( strtolower( $head ), 0, 12 );
			}
		}
		return UFSC_CL_VERSION;
	}
}

require_once UFSC_CL_DIR . 'vendor/autoload.php';
require_once UFSC_CL_DIR.'includes/core/class-utils.php';
require_once UFSC_CL_DIR.'includes/core/column-map.php';
require_once UFSC_CL_DIR.'includes/security/class-ufsc-capabilities.php';
require_once UFSC_CL_DIR.'includes/security/class-ufsc-scope.php';
require_once UFSC_CL_DIR.'includes/permissions/class-ufsc-permissions.php';
require_once UFSC_CL_DIR.'includes/admin/class-admin-menu.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-settings-page.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-diagnostics-admin.php';
require_once UFSC_CL_DIR.'includes/core/class-sql.php';
require_once UFSC_CL_DIR . 'class-sql-admin.php';
require_once UFSC_CL_DIR.'includes/frontend/class-sql-shortcodes.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form-handler.php';
require_once UFSC_CL_DIR.'includes/core/class-uploads.php';
require_once UFSC_CL_DIR.'includes/front/class-ufsc-media.php';
require_once UFSC_CL_DIR.'includes/core/class-permissions.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-badges.php';
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
require_once UFSC_CL_DIR.'includes/core/class-ufsc-category-repository.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-season-service.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-storage-resolver.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-identifier-resolver.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-identifier-service.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-renewal-service.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-season-archive-manager.php';
require_once UFSC_CL_DIR.'includes/core/class-ufsc-licence-payments.php';
require_once UFSC_CL_DIR.'includes/frontend/class-affiliation-form.php';
require_once UFSC_CL_DIR.'includes/admin/list-tables/class-ufsc-licences-list-table.php';
require_once UFSC_CL_DIR.'includes/admin/list-tables/class-ufsc-clubs-list-table.php';

require_once UFSC_CL_DIR.'includes/admin/class-ufsc-club-metaboxes.php';

require_once UFSC_CL_DIR.'includes/front/class-ufsc-stats.php';


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
require_once UFSC_CL_DIR.'includes/admin/class-user-profile-scope-field.php';
require_once UFSC_CL_DIR.'includes/admin/class-ufsc-simplified-admin.php';
require_once UFSC_CL_DIR.'includes/cli/class-wp-cli-commands.php';
//require_once UFSC_CL_DIR.'includes/front/redirect-check.php';

// New UFSC Gestion modules
require_once UFSC_CL_DIR.'inc/common/regions.php';
require_once UFSC_CL_DIR.'inc/common/statuts.php';
require_once UFSC_CL_DIR.'inc/common/status.php';
require_once UFSC_CL_DIR.'inc/common/logging.php';
require_once UFSC_CL_DIR.'inc/common/season.php';
require_once UFSC_CL_DIR.'inc/common/seasons.php';
require_once UFSC_CL_DIR.'inc/common/feature-flags.php';
require_once UFSC_CL_DIR.'inc/common/licence-status.php';
require_once UFSC_CL_DIR.'inc/common/fighter-level.php';
require_once UFSC_CL_DIR.'inc/common/licence-documents.php';
require_once UFSC_CL_DIR.'inc/common/compliance.php';
require_once UFSC_CL_DIR.'inc/common/attestations.php';
require_once UFSC_CL_DIR.'inc/common/tables.php';
require_once UFSC_CL_DIR.'inc/common/functions.php';
require_once UFSC_CL_DIR.'inc/common/diagnostics.php';
require_once UFSC_CL_DIR.'inc/settings.php';
require_once UFSC_CL_DIR.'inc/form-license-sanitizer.php';
require_once UFSC_CL_DIR.'inc/woocommerce/settings-woocommerce.php';
require_once UFSC_CL_DIR.'inc/woocommerce/hooks.php';
require_once UFSC_CL_DIR.'inc/woocommerce/admin-actions.php';
require_once UFSC_CL_DIR.'inc/woocommerce/cart-integration.php';
// require_once UFSC_CL_DIR.'inc/admin/menu.php'; // Removed - using unified menu system in includes/admin/class-admin-menu.php
require_once UFSC_CL_DIR.'includes/woo/class-ufsc-woo-sync.php';
require_once UFSC_CL_DIR.'includes/communication/class-ufsc-mail-installer.php';
require_once UFSC_CL_DIR.'includes/communication/class-ufsc-mail-service.php';

add_action('init', function () {
    load_plugin_textdomain('ufsc-clubs', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

final class UFSC_CL_Bootstrap {
    private static $instance = null;
    public static function instance(){ if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct(){
        register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );

        add_action( 'admin_menu', array( 'UFSC_CL_Admin_Menu', 'register' ) );
        add_action( 'admin_menu', array( 'UFSC_Diagnostics_Admin', 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( 'UFSC_CL_Admin_Menu', 'enqueue_admin' ) );
        add_action( 'wp_enqueue_scripts', array( 'UFSC_CL_Admin_Menu', 'register_front' ) );

        // SQL Admin CRUD actions (pages cachées mais enregistrées pour les actions directes)
        add_action( 'admin_menu', array( 'UFSC_SQL_Admin', 'register_hidden_pages' ) );
        add_action( 'admin_init', array( 'UFSC_SQL_Admin', 'maybe_send_admin_nocache_headers' ), 0 );
        add_action( 'admin_enqueue_scripts', array( 'UFSC_SQL_Admin', 'enqueue_admin_assets' ) );
        add_action( 'admin_post_ufsc_sql_save_club', array( 'UFSC_SQL_Admin', 'handle_save_club' ) );
        add_action( 'admin_post_ufsc_sql_delete_club', array( 'UFSC_SQL_Admin', 'handle_delete_club' ) );
        add_action( 'admin_post_ufsc_sql_save_licence', array( 'UFSC_SQL_Admin', 'handle_save_licence' ) );
        add_action( 'admin_post_ufsc_sql_delete_licence', array( 'UFSC_SQL_Admin', 'handle_delete_licence' ) );
        add_action( 'admin_post_ufsc_sql_trash_licence', array( 'UFSC_SQL_Admin', 'handle_trash_licence' ) );
        add_action( 'admin_post_ufsc_sql_restore_licence', array( 'UFSC_SQL_Admin', 'handle_restore_licence' ) );
        add_action( 'admin_post_ufsc_sql_force_delete_licence', array( 'UFSC_SQL_Admin', 'handle_force_delete_licence' ) );
        add_action( 'admin_post_ufsc_send_license_payment', array( 'UFSC_SQL_Admin', 'handle_send_license_payment' ) );
        add_action( 'admin_post_ufsc_export_data', array( 'UFSC_SQL_Admin', 'handle_export_data' ) );
        add_action( 'admin_post_ufsc_generate_identifier', array( 'UFSC_Identifier_Service', 'handle_generate_request' ) );
        add_action( 'admin_post_ufsc_save_asptt_identifier', array( 'UFSC_Identifier_Service', 'handle_asptt_request' ) );

        add_action('admin_post_ufsc_import_data', array('UFSC_SQL_Admin', 'handle_import_data'));
        add_action('admin_post_ufsc_process_import', array('UFSC_SQL_Admin', 'handle_process_import'));

        // action bulk action
        add_action('admin_init', array( 'UFSC_SQL_Admin', 'handle_bulk_actions' ));

        add_action('admin_init', array( 'UFSC_Clubs_List_Table', 'handle_bulk_actions' ));

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
        add_action( 'init', array( 'UFSC_Capabilities', 'register_caps' ) );
        add_action( 'init', array( 'UFSC_Permissions', 'init' ) );
        add_action( 'init', array( 'UFSC_Simplified_Admin', 'init' ) );
        add_action( 'init', array( 'UFSC_User_Profile_Scope_Field', 'init' ) );
        add_action( 'plugins_loaded', array( 'UFSC_DB_Migrations', 'run_migrations' ) );

        // Initialize UFSC Gestion WooCommerce hooks
        add_action( 'plugins_loaded', 'ufsc_init_woocommerce_hooks' );
        add_action( 'plugins_loaded', array( 'UFSC_Woo_Sync', 'init' ) );
        add_action( 'plugins_loaded', array( 'UFSC_Mail_Service', 'init' ) );

        // Initialize frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'localize_frontend_scripts' ) );
        add_filter( 'body_class', array( $this, 'add_frontend_portal_body_class' ) );


    }
    public function on_activate(){

        if ( ! wp_next_scheduled( 'ufsc_daily' ) ) {
            wp_schedule_event( time(), 'daily', 'ufsc_daily' );
        }
        if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
            ufsc_flush_table_columns_cache();
        }
        UFSC_Capabilities::register_caps();
        UFSC_Permissions::register_roles_and_caps();
        UFSC_DB_Migrations::run_migrations();
        if ( class_exists( 'UFSC_Licence_Payments' ) ) { UFSC_Licence_Payments::maybe_migrate(); }
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
     * Tag pages that render the club portal so Astra/Elementor wrappers can be widened safely.
     * The class is scoped to known UFSC club shortcodes only; it is not applied globally.
     */
    public function add_frontend_portal_body_class( $classes ) {
        global $post;
        $classes = is_array( $classes ) ? $classes : array();
        if ( $post && (
            has_shortcode( $post->post_content, 'ufsc_club_dashboard' ) ||
            has_shortcode( $post->post_content, 'ufsc_club_profile' )
        ) ) {
            $classes[] = 'ufsc-club-portal-page';
        }
        return array_values( array_unique( $classes ) );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        $should_enqueue = false;

        // Guard against early execution before the main query is available.
        if ( ! did_action( 'wp' ) ) {
            return;
        }

        if ( $post && has_shortcode( $post->post_content, 'ufsc_club_dashboard' ) ) {
            $should_enqueue = true;
        } elseif ( $post && (
            has_shortcode( $post->post_content, 'ufsc_club_licences' ) ||
            has_shortcode( $post->post_content, 'ufsc_club_stats' ) ||
            has_shortcode( $post->post_content, 'ufsc_club_profile' ) ||
            has_shortcode( $post->post_content, 'ufsc_add_licence' )
        ) ) { $should_enqueue = true; }

        if ( ! $should_enqueue && is_user_logged_in() ) {
            if ( function_exists('is_account_page') && is_account_page() ) {
                $should_enqueue = true;
            } else {
                $should_enqueue = is_page( array( 'tableau-de-bord', 'club-dashboard', 'mon-club', 'mon-compte', 'my-account' ) );
            }
        }

        if ( $should_enqueue ) {
            $dashboard_js  = UFSC_CL_DIR . 'assets/js/frontend-dashboard.js';
            $dashboard_css = UFSC_CL_DIR . 'assets/css/ufsc-front.css';
            $js_version    = file_exists( $dashboard_js ) ? (string) filemtime( $dashboard_js ) : UFSC_CL_VERSION;
            $css_version   = file_exists( $dashboard_css ) ? (string) filemtime( $dashboard_css ) : UFSC_CL_VERSION;
            wp_enqueue_style('ufsc-frontend', UFSC_CL_URL . 'assets/frontend/css/frontend.css', array(), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/frontend/css/frontend.css' ) : UFSC_CL_VERSION );
            wp_enqueue_style( 'ufsc-renewal-runtime', UFSC_CL_URL . 'assets/css/ufsc-front.css', array( 'ufsc-frontend' ), $css_version );
            wp_enqueue_script('ufsc-frontend', UFSC_CL_URL . 'assets/frontend/js/frontend.js', array('jquery'), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/frontend/js/frontend.js' ) : UFSC_CL_VERSION, true );
            wp_enqueue_script( 'ufsc-renewal-runtime', UFSC_CL_URL . 'assets/js/frontend-dashboard.js', array( 'jquery', 'ufsc-frontend' ), $js_version, true );
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( '[UFSC Gestion] renewal assets js=%s css=%s', $js_version, $css_version ) );
            }
        }
    }

    /**
     * Localize frontend scripts with data and translations
     */
    public function localize_frontend_scripts() {
        if ( wp_script_is( 'ufsc-frontend', 'enqueued' ) ) {
            // This value is display/runtime context only. Mutating endpoints must
            // (and do) resolve the canonical club again from the authenticated user.
            $club_id = is_user_logged_in() && function_exists( 'ufsc_get_user_club_id' )
                ? absint( ufsc_get_user_club_id( get_current_user_id() ) )
                : 0;
            wp_localize_script( 'ufsc-frontend', 'ufsc_frontend_vars', array(
                'club_id' => $club_id,
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'rest_url' => rest_url( 'ufsc/v1/' ),
                'nonce' => wp_create_nonce( 'ufsc_frontend_nonce' ),
                'rest_nonce' => wp_create_nonce( 'wp_rest' ),
                'strings' => array(
                    'saving' => __( 'Enregistrement...', 'ufsc-clubs' ),
                    'loading' => __( 'Chargement...', 'ufsc-clubs' ),
                    'error' => __( 'Une erreur est survenue.', 'ufsc-clubs' ),
                    'success' => __( 'Opération réussie.', 'ufsc-clubs' ),
                    'confirm_remove_logo' => __( 'Êtes-vous sûr de vouloir supprimer ce logo ?', 'ufsc-clubs' ),
                    'invalid_file_type' => __( 'Type de fichier non autorisé.', 'ufsc-clubs' ),
                    'file_too_large' => __( 'Fichier trop volumineux.', 'ufsc-clubs' ),
                    'invalid_email' => __( 'Adresse email invalide.', 'ufsc-clubs' ),
                    'invalid_phone' => __( 'Numéro de téléphone invalide.', 'ufsc-clubs' ),
                    'invalid_postal_code' => __( 'Code postal invalide.', 'ufsc-clubs' ),
                    'characters_remaining' => __( 'caractères restants', 'ufsc-clubs' ),
                    'exporting' => __( 'Export en cours...', 'ufsc-clubs' ),
                    'export' => __( 'Exporter', 'ufsc-clubs' ),
                    'import_preview' => __( 'Prévisualisation de l\'import', 'ufsc-clubs' ),
                    'import_errors' => __( 'Erreurs détectées', 'ufsc-clubs' ),
                    'preview_data' => __( 'Données à importer', 'ufsc-clubs' ),
                    'confirm_import' => __( 'Confirmer l\'import', 'ufsc-clubs' ),
                    'confirm_import_action' => __( 'Êtes-vous sûr de vouloir importer ces données ?', 'ufsc-clubs' ),
                    'name' => __( 'Nom', 'ufsc-clubs' ),
                    'first_name' => __( 'Prénom', 'ufsc-clubs' ),
                    'email' => __( 'Email', 'ufsc-clubs' ),
                    'adresse' => __( 'Adresse', 'ufsc-clubs' ),
                    'ville' => __( 'Ville', 'ufsc-clubs' ),
                    'code_postal' => __( 'Code postal', 'ufsc-clubs' ),
                    'sexe' => __( 'Sexe', 'ufsc-clubs' ),
                    'date_naissance' => __( 'Date naissance', 'ufsc-clubs' ),
                    'region' => __( 'Région', 'ufsc-clubs' ),
                    'tel_mobile' => __( 'Téléphone', 'ufsc-clubs' ),
                    'status' => __( 'Statut', 'ufsc-clubs' ),
                    'ajax_error' => __( 'Erreur de communication avec le serveur.', 'ufsc-clubs' ),
                    'logo_preview' => __( 'Aperçu du logo', 'ufsc-clubs' ),
                    'logo_preview_text' => __( 'Aperçu du logo à télécharger', 'ufsc-clubs' ),
                    'choose_logo' => __( 'Choisir un logo', 'ufsc-clubs' ),
                    'logo_help' => __( 'Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs' ),
                    'button_action' => __( 'Action', 'ufsc-clubs' ),
                    'skip_to_nav' => __( 'Aller à la navigation', 'ufsc-clubs' ),
                    'skip_to_content' => __( 'Aller au contenu', 'ufsc-clubs' )
                )
            ) );

            if ( wp_script_is( 'ufsc-renewal-runtime', 'enqueued' ) ) {
                wp_localize_script( 'ufsc-renewal-runtime', 'ufsc_dashboard_vars', array(
                    'club_id' => $club_id,
                    'rest_url' => rest_url( 'ufsc/v1/' ),
                ) );
            }
        }
    }
}
UFSC_CL_Bootstrap::instance();
