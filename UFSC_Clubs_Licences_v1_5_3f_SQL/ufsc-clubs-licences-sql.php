<?php
/**
 * Plugin Name: UFSC – Clubs & Licences (SQL)
 * Description: Gestion Clubs/Licences connectée aux tables SQL existantes (mapping complet), formulaires complets (admin & front), documents PDF/JPG/PNG, exports CSV, badges colorés, mini-dashboard, shortcodes.
 * Version: 1.5.3ff
 * Author: Davy – Studio REACTIV (pour l'UFSC)
 * Text Domain: ufsc-clubs
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'UFSC_CL_VERSION', '1.5.3ff' );
define( 'UFSC_CL_DIR', plugin_dir_path( __FILE__ ) );
define( 'UFSC_CL_URL', plugin_dir_url( __FILE__ ) );

require_once UFSC_CL_DIR.'includes/core/class-utils.php';
require_once UFSC_CL_DIR.'includes/admin/class-admin-menu.php';
require_once UFSC_CL_DIR.'includes/core/class-sql.php';
require_once UFSC_CL_DIR.'includes/admin/class-sql-admin.php';
require_once UFSC_CL_DIR.'includes/frontend/class-sql-shortcodes.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form.php';
require_once UFSC_CL_DIR.'includes/frontend/class-club-form-handler.php';
require_once UFSC_CL_DIR.'includes/core/class-uploads.php';
require_once UFSC_CL_DIR.'includes/core/class-permissions.php';

final class UFSC_CL_Bootstrap {
    private static $instance = null;
    public static function instance(){ if ( null === self::$instance ) self::$instance = new self(); return self::$instance; }
    private function __construct(){
        register_activation_hook( __FILE__, array( $this, 'on_activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'on_deactivate' ) );

        add_action( 'admin_menu', array( 'UFSC_CL_Admin_Menu', 'register' ) );
        add_action( 'admin_enqueue_scripts', array( 'UFSC_CL_Admin_Menu', 'enqueue_admin' ) );
        add_action( 'wp_enqueue_scripts', array( 'UFSC_CL_Admin_Menu', 'register_front' ) );

        // SQL Admin CRUD actions (pas de menu séparé - intégré dans le menu principal)
        // add_action( 'admin_menu', array( 'UFSC_SQL_Admin', 'register_menus' ) ); // Désactivé - menu unifié maintenant
        add_action( 'admin_post_ufsc_sql_save_club', array( 'UFSC_SQL_Admin', 'handle_save_club' ) );
        add_action( 'admin_post_ufsc_sql_delete_club', array( 'UFSC_SQL_Admin', 'handle_delete_club' ) );
        add_action( 'admin_post_ufsc_sql_save_licence', array( 'UFSC_SQL_Admin', 'handle_save_licence' ) );
        add_action( 'admin_post_ufsc_sql_delete_licence', array( 'UFSC_SQL_Admin', 'handle_delete_licence' ) );

        // Shortcodes front
        add_action( 'init', array( 'UFSC_SQL_Shortcodes', 'register_shortcodes' ) );
    }
    public function on_activate(){ flush_rewrite_rules(); }
    public function on_deactivate(){ flush_rewrite_rules(); }
}
UFSC_CL_Bootstrap::instance();
