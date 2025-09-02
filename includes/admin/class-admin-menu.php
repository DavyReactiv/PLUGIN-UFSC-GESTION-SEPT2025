<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Admin_Menu {
    public static function register(){
        // Menu principal unifié UFSC
        add_menu_page( 
            __( 'UFSC Gestion', 'ufsc-clubs' ), 
            __( 'UFSC Gestion', 'ufsc-clubs' ), 
            'manage_options', 
            'ufsc-dashboard', 
            array( __CLASS__, 'render_dashboard' ), 
            'dashicons-groups', 
            58 
        );
        
        // Sous-menus organisés
        add_submenu_page( 
            'ufsc-dashboard', 
            __('Tableau de bord','ufsc-clubs'), 
            __('Tableau de bord','ufsc-clubs'), 
            'manage_options', 
            'ufsc-dashboard', 
            array( __CLASS__, 'render_dashboard' ) 
        );
        
        add_submenu_page( 
            'ufsc-dashboard', 
            __('Clubs','ufsc-clubs'), 
            __('Clubs','ufsc-clubs'), 
            'manage_options', 
            'ufsc-clubs', 
            array( 'UFSC_SQL_Admin', 'render_clubs' ) 
        );
        
        add_submenu_page( 
            'ufsc-dashboard', 
            __('Licences','ufsc-clubs'), 
            __('Licences','ufsc-clubs'), 
            'manage_options', 
            'ufsc-licences', 
            array( 'UFSC_SQL_Admin', 'render_licences' ) 
        );
        
        add_submenu_page( 
            'ufsc-dashboard', 
            __('Exports','ufsc-clubs'), 
            __('Exports','ufsc-clubs'), 
            'manage_options', 
            'ufsc-exports', 
            array( 'UFSC_SQL_Admin', 'render_exports' ) 
        );
        
        add_submenu_page( 
            'ufsc-dashboard', 
            __('Paramètres','ufsc-clubs'), 
            __('Paramètres','ufsc-clubs'), 
            'manage_options', 
            'ufsc-settings', 
            array( 'UFSC_SQL_Admin', 'render_settings' ) 
        );
        
        add_submenu_page( 
            'ufsc-dashboard', 
            __('WooCommerce','ufsc-clubs'), 
            __('WooCommerce','ufsc-clubs'), 
            'manage_options', 
            'ufsc-woocommerce', 
            array( 'UFSC_SQL_Admin', 'render_woocommerce_settings' ) 
        );
    }
    public static function enqueue_admin( $hook ){
        if ( strpos($hook, 'ufsc') !== false ){
            wp_enqueue_style( 'ufsc-admin', UFSC_CL_URL.'assets/admin/css/admin.css', array(), UFSC_CL_VERSION );
            wp_enqueue_script( 'ufsc-admin', UFSC_CL_URL.'assets/admin/js/admin.js', array('jquery'), UFSC_CL_VERSION, true );
        }
    }
    public static function register_front(){
        wp_register_style( 'ufsc-frontend', UFSC_CL_URL.'assets/frontend/css/frontend.css', array(), UFSC_CL_VERSION );
        wp_register_script( 'ufsc-frontend', UFSC_CL_URL.'assets/frontend/js/frontend.js', array('jquery'), UFSC_CL_VERSION, true );
    }
    public static function render_dashboard(){
        global $wpdb; 
        $opts = get_option('ufsc_sql_settings', array());
        $t_clubs = isset($opts['table_clubs']) ? $opts['table_clubs'] : 'clubs';
        $t_lics  = isset($opts['table_licences']) ? $opts['table_licences'] : 'licences';
        
        echo '<div class="wrap">';
        
        // Header moderne
        echo '<div class="ufsc-header">';
        echo '<h1>'.esc_html__('UFSC – Gestion des Clubs et Licences','ufsc-clubs').'</h1>';
        echo '<p>'.esc_html__('Tableau de bord de gestion des clubs et licences sportives UFSC','ufsc-clubs').'</p>';
        echo '</div>';
        
        // Vérification des tables avant d'afficher les KPI
        $tables_exist = true;
        $clubs_total = 0;
        $lics_total = 0;
        $clubs_actifs = 0;
        $lics_actives = 0;
        
        try {
            // Vérifier si les tables existent
            $club_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$t_clubs'") === $t_clubs;
            $licence_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$t_lics'") === $t_lics;
            
            if ($club_table_exists && $licence_table_exists) {
                $clubs_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_clubs`");
                $lics_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics`");
                $clubs_actifs = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_clubs` WHERE statut = 'valide'");
                $lics_actives = (int) $wpdb->get_var("SELECT COUNT(*) FROM `$t_lics` WHERE statut = 'valide'");
            } else {
                $tables_exist = false;
            }
        } catch (Exception $e) {
            $tables_exist = false;
        }
        
        if (!$tables_exist) {
            echo '<div class="ufsc-alert error">';
            echo '<strong>'.esc_html__('Configuration requise','ufsc-clubs').'</strong><br>';
            echo esc_html__('Les tables de données ne sont pas encore configurées.','ufsc-clubs').' ';
            echo '<a href="'.admin_url('admin.php?page=ufsc-settings').'">'.esc_html__('Configurer maintenant','ufsc-clubs').'</a>';
            echo '</div>';
        }
        
        // Cartes KPI améliorées
        echo UFSC_CL_Utils::kpi_cards(array(
            array('label'=>__('Clubs Total','ufsc-clubs'),'value'=>$clubs_total),
            array('label'=>__('Clubs Actifs','ufsc-clubs'),'value'=>$clubs_actifs),
            array('label'=>__('Licences Total','ufsc-clubs'),'value'=>$lics_total),
            array('label'=>__('Licences Actives','ufsc-clubs'),'value'=>$lics_actives),
        ));
        
        // Actions rapides
        echo '<div class="ufsc-quick-actions" style="margin-top: 30px;">';
        echo '<h2>'.esc_html__('Actions rapides','ufsc-clubs').'</h2>';
        echo '<div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 15px;">';
        echo '<a href="'.admin_url('admin.php?page=ufsc-clubs&action=new').'" class="button button-primary ufsc-primary">'.esc_html__('Nouveau Club','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-licences&action=new').'" class="button button-primary ufsc-primary">'.esc_html__('Nouvelle Licence','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-clubs').'" class="button">'.esc_html__('Gérer les Clubs','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-licences').'" class="button">'.esc_html__('Gérer les Licences','ufsc-clubs').'</a>';
        echo '<a href="'.admin_url('admin.php?page=ufsc-settings').'" class="button">'.esc_html__('Réglages','ufsc-clubs').'</a>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
    }
}
