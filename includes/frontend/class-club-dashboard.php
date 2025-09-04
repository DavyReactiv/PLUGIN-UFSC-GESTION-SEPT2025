<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Club Dashboard Class
 * Handles the club dashboard shortcode with enhanced features
 */
class UFSC_Club_Dashboard {

    /**
     * Initialize dashboard functionality
     */
    public static function init() {
        add_shortcode( 'ufsc_club_dashboard', array( __CLASS__, 'render_shortcode' ) );
        
        // WooCommerce integration
        if ( self::is_woocommerce_active() ) {
            add_rewrite_endpoint( 'ufsc-tableau-de-bord', EP_ROOT | EP_PAGES );
            add_filter( 'woocommerce_get_query_vars', function( $vars ) {
                $vars['ufsc-tableau-de-bord'] = 'ufsc-tableau-de-bord';
                return $vars;
            } );
            add_action( 'woocommerce_account_ufsc-tableau-de-bord_endpoint', array( __CLASS__, 'render_woocommerce_dashboard' ) );
            add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_woocommerce_menu_item' ) );
        }
    }

    /**
     * Render the club dashboard shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string Dashboard HTML
     */
    public static function render_shortcode( $atts = array() ) {
        $atts = shortcode_atts( array(
            'show_sections' => 'header,kpi,actions,charts,documents,notifications,audit'
        ), $atts, 'ufsc_club_dashboard' );

        if ( ! is_user_logged_in() ) {
            return '<div class="ufsc-message ufsc-error">' . 
                   esc_html__( 'Vous devez être connecté pour accéder au tableau de bord.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        $user_id = get_current_user_id();
        $club = self::get_user_club( $user_id );

        if ( ! $club ) {
            return '<div class="ufsc-message ufsc-info">' . 
                   esc_html__( 'Aucun club associé à votre compte. Veuillez contacter l\'administrateur ou créer un club.', 'ufsc-clubs' ) . 
                   '</div>';
        }

        // Check attestation d'affiliation
        $attestation_affiliation = self::get_attestation_affiliation( $club->id );
        
        // Enqueue scripts and styles
        self::enqueue_dashboard_assets();
        
        // Prepare template variables
        $template_vars = array(
            'club' => $club,
            'attestation_affiliation' => $attestation_affiliation,
            'sections' => explode( ',', $atts['show_sections'] ),
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'ufsc_club_dashboard' ),
        );
        
        // Load template
        ob_start();
        extract( $template_vars );
        include UFSC_CL_DIR . 'templates/frontend/club-dashboard.php';
        return ob_get_clean();
    }
    /**
     * Render dashboard for WooCommerce account page
     */
    public static function render_woocommerce_dashboard() {
        echo self::render_shortcode();
    }

    /**
     * Add dashboard menu item to WooCommerce account
     */
    public static function add_woocommerce_menu_item( $items ) {
        $new_items = array();
        
        foreach ( $items as $key => $item ) {
            $new_items[ $key ] = $item;
            
            // Add after dashboard
            if ( $key === 'dashboard' ) {
                $new_items['ufsc-tableau-de-bord'] = __( 'Mon Club', 'ufsc-clubs' );
            }
        }
        
        return $new_items;
    }

    /**
     * Get club for current user
     * 
     * @param int $user_id User ID
     * @return object|null Club object or null
     */
    private static function get_user_club( $user_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        
        $responsable_col = ufsc_club_col( 'responsable_id' );
        if ( ! $responsable_col ) {
            return null; // Fallback if column mapping not available
        }

        $club = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE `{$responsable_col}` = %d LIMIT 1",
            $user_id
        ) );
        
        return $club;
    }

    /**

     * Render status badge
     * 
     * @param string $status Status value
     * @return string Badge HTML
     */
    private static function render_status_badge( $status ) {
        return UFSC_Badge_Helper::render_status_badge( $status );
    }

    /**
    
     * Check if user can edit club
     * 
     * @param int $user_id User ID
     * @param int $club_id Club ID
     * @return bool
     */
    private static function user_can_edit_club( $user_id, $club_id ) {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        
        $responsable_col = ufsc_club_col( 'responsable_id' );
        if ( ! $responsable_col ) {
            return false; // Fallback if column mapping not available
        }

        $result = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE id = %d AND `{$responsable_col}` = %d",
            $club_id,
            $user_id
        ) );
        
        return ! is_null( $result );
    }

    /**
     * Get club edit URL
     * 
     * @param int $club_id Club ID
     * @return string Edit URL
     */
    private static function get_club_edit_url( $club_id ) {
        // Check if there's a frontend edit form
        $edit_page = get_option( 'ufsc_club_edit_page' );
        
        if ( $edit_page ) {
            return add_query_arg( 'club_id', $club_id, get_permalink( $edit_page ) );
        }
        
        // Fallback to admin edit
        if ( current_user_can( 'manage_options' ) ) {
            return admin_url( 'admin.php?page=ufsc-sql-clubs&action=edit&id=' . $club_id );
        }
        
        return '#';
    }

    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    private static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Get attestation d'affiliation for club
     * 
     * @param int $club_id Club ID
     * @return string|false URL to attestation file or false
     */
    private static function get_attestation_affiliation( $club_id ) {
        return UFSC_PDF_Attestations::get_attestation_for_club( $club_id, 'affiliation' );
    }

    /**
     * Enqueue dashboard assets
     */
    private static function enqueue_dashboard_assets() {
        // Enqueue Chart.js from CDN or local
        wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', array(), '3.9.1', true );
        
        // Enqueue custom dashboard CSS
        wp_enqueue_style( 'ufsc-dashboard', UFSC_CL_URL . 'assets/css/frontend-dashboard.css', array(), UFSC_CL_VERSION );
        
        // Enqueue custom dashboard JS
        wp_enqueue_script( 'ufsc-dashboard', UFSC_CL_URL . 'assets/js/frontend-dashboard.js', array( 'jquery', 'chart-js' ), UFSC_CL_VERSION, true );
        
        // Localize script for AJAX
        wp_localize_script( 'ufsc-dashboard', 'ufsc_dashboard_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ufsc_club_dashboard' ),
            'club_id' => self::get_user_club_id( get_current_user_id() ),
            'rest_url' => rest_url( 'ufsc/v1/' ),
            'strings' => array(
                'loading' => __( 'Chargement...', 'ufsc-clubs' ),
                'error' => __( 'Erreur lors du chargement', 'ufsc-clubs' ),
                'no_data' => __( 'Aucune donnée disponible', 'ufsc-clubs' ),
                'confirm_status_change' => __( 'Confirmer le changement de statut ?', 'ufsc-clubs' ),
                'success_updated' => __( 'Mis à jour avec succès', 'ufsc-clubs' ),
            )
        ) );
    }

    /**
     * Get user's club ID
     * 
     * @param int $user_id User ID
     * @return int|false Club ID or false
     */
    private static function get_user_club_id( $user_id ) {
        $club = self::get_user_club( $user_id );
        return $club ? $club->id : false;
    }

}