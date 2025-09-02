<?php
/**
 * Plugin Name: UFSC Clubs & Licences (SQL)
 * Description: Gestion UFSC (Clubs et Licences) via tables SQL dédiées (CRUD admin + shortcodes front).
 * Version: 1.5.3f
 * Author: UFSC
 * Text Domain: ufsc-clubs
 */

if ( ! defined('ABSPATH') ) { exit; }

define('UFSC_CL_FILE', __FILE__);
define('UFSC_CL_PATH', plugin_dir_path(__FILE__));
define('UFSC_CL_URL',  plugin_dir_url(__FILE__));
define('UFSC_CL_VERSION', '1.5.3f');
define('UFSC_CL_BASENAME', plugin_basename(__FILE__));

// Chargement des classes
require_once UFSC_CL_PATH.'includes/class-utils.php';
require_once UFSC_CL_PATH.'includes/class-sql.php';
require_once UFSC_CL_PATH.'includes/class-sql-admin.php';
require_once UFSC_CL_PATH.'includes/class-sql-shortcodes.php';
require_once UFSC_CL_PATH.'includes/class-admin-menu.php';
require_once UFSC_CL_PATH.'includes/class-sql-public.php';

// i18n + upgrade schema si besoin
add_action('plugins_loaded', function(){
    load_plugin_textdomain('ufsc-clubs', false, dirname(plugin_basename(__FILE__)).'/languages');
    if ( class_exists('UFSC_SQL') ) {
        UFSC_SQL::maybe_upgrade();
    }
});

// Activation: création/MAJ des tables et options par défaut
register_activation_hook(__FILE__, array('UFSC_SQL','install'));

// Init: shortcodes + enregistrement des assets front
add_action('init', function(){
    UFSC_SQL_Shortcodes::register();
    if ( class_exists('UFSC_CL_Admin_Menu') ) {
        UFSC_CL_Admin_Menu::register_front();
    }
});

// Menus Admin
add_action('admin_menu', array('UFSC_SQL_Admin','register_menus'));

// Enqueues Admin (CSS/JS)
add_action('admin_enqueue_scripts', array('UFSC_CL_Admin_Menu','enqueue_admin'));

// Handlers Admin (CRUD back-office)
add_action('admin_post_ufsc_sql_save_club',      array('UFSC_SQL_Admin','handle_save_club'));
add_action('admin_post_ufsc_sql_delete_club',    array('UFSC_SQL_Admin','handle_delete_club'));
add_action('admin_post_ufsc_sql_save_licence',   array('UFSC_SQL_Admin','handle_save_licence'));
add_action('admin_post_ufsc_sql_delete_licence', array('UFSC_SQL_Admin','handle_delete_licence'));

// Handlers Public (formulaire front)
add_action('admin_post_nopriv_ufsc_sql_public_save_licence', array('UFSC_SQL_Public','handle_save_licence'));
add_action('admin_post_ufsc_sql_public_save_licence',        array('UFSC_SQL_Public','handle_save_licence'));

// Lien "Réglages" dans la liste des plugins
add_filter('plugin_action_links_'.UFSC_CL_BASENAME, function($links){
    $links[] = '<a href="'.esc_url(admin_url('admin.php?page=ufsc-sql-settings')).'">'.esc_html__('Réglages', 'ufsc-clubs').'</a>';
    return $links;
});
