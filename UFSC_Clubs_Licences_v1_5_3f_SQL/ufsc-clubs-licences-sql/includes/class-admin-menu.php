<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Admin_Menu {
    public static function register(){
        add_menu_page( __( 'UFSC – Tableau de bord', 'ufsc-clubs' ), __( 'UFSC – Tableau de bord', 'ufsc-clubs' ), 'manage_options', 'ufsc-dashboard', array( __CLASS__, 'render_dashboard' ), 'dashicons-chart-pie', 58 );
    }
    public static function enqueue_admin( $hook ){
        if ( strpos($hook, 'ufsc') !== false ){
            wp_enqueue_style( 'ufsc-admin', UFSC_CL_URL.'assets/css/admin.css', array(), UFSC_CL_VERSION );
            wp_enqueue_script( 'ufsc-admin', UFSC_CL_URL.'assets/js/admin.js', array('jquery'), UFSC_CL_VERSION, true );
        }
    }
    public static function register_front(){
        wp_register_style( 'ufsc-frontend', UFSC_CL_URL.'assets/css/frontend.css', array(), UFSC_CL_VERSION );
        wp_register_script( 'ufsc-frontend', UFSC_CL_URL.'assets/js/frontend.js', array('jquery'), UFSC_CL_VERSION, true );
    }
    public static function render_dashboard(){
        global $wpdb; $opts = get_option('ufsc_sql_settings', array());
        $t_clubs = isset($opts['table_clubs']) ? $opts['table_clubs'] : 'clubs';
        $t_lics  = isset($opts['table_licences']) ? $opts['table_licences'] : 'licences';
        $clubs_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_clubs`");
        $lics_total  = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics`");
        echo '<div class="wrap"><h1>'.esc_html__('UFSC – Tableau de bord','ufsc-clubs').'</h1>';
        echo UFSC_CL_Utils::kpi_cards(array(
            array('label'=>__('Clubs (SQL)','ufsc-clubs'),'value'=>$clubs_total),
            array('label'=>__('Licences (SQL)','ufsc-clubs'),'value'=>$lics_total),
        ));
        echo '</div>';
    }
}
