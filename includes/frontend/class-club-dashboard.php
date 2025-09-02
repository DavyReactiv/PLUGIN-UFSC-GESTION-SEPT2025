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
            'show_sections' => 'basic,region,status,quota'
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

        $sections = explode( ',', $atts['show_sections'] );
        
        ob_start();
        ?>
        <div class="ufsc-club-dashboard">
            <div class="ufsc-dashboard-header">
                <h2><?php echo esc_html__( 'Tableau de bord du club', 'ufsc-clubs' ); ?></h2>
            </div>

            <?php if ( in_array( 'basic', $sections ) ) : ?>
            <div class="ufsc-dashboard-section ufsc-club-info">
                <h3><?php echo esc_html__( 'Informations du club', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-info-grid">
                    <div class="ufsc-info-item">
                        <label><?php echo esc_html__( 'Nom du club', 'ufsc-clubs' ); ?> :</label>
                        <span class="ufsc-club-name"><?php echo esc_html( $club->nom ); ?></span>
                    </div>
                    <div class="ufsc-info-item">
                        <label><?php echo esc_html__( 'Numéro d\'affiliation', 'ufsc-clubs' ); ?> :</label>
                        <span class="ufsc-club-affiliation">
                            <?php echo $club->num_affiliation ? esc_html( $club->num_affiliation ) : '<em>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</em>'; ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( in_array( 'region', $sections ) && ! empty( $club->region ) ) : ?>
            <div class="ufsc-dashboard-section ufsc-club-region">
                <h3><?php echo esc_html__( 'Région', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-info-item">
                    <span class="ufsc-region-badge"><?php echo esc_html( $club->region ); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( in_array( 'status', $sections ) ) : ?>
            <div class="ufsc-dashboard-section ufsc-club-status">
                <h3><?php echo esc_html__( 'Statut', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-info-item">
                    <?php echo self::render_status_badge( $club->statut ); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ( in_array( 'quota', $sections ) && isset( $club->quota_licences ) ) : ?>
            <div class="ufsc-dashboard-section ufsc-club-quota">
                <h3><?php echo esc_html__( 'Quota de licences', 'ufsc-clubs' ); ?></h3>
                <div class="ufsc-info-item">
                    <span class="ufsc-quota-number"><?php echo (int) $club->quota_licences; ?></span>
                    <span class="ufsc-quota-label"><?php echo esc_html__( 'licences autorisées', 'ufsc-clubs' ); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <?php 
            // Allow other plugins to add dashboard sections
            do_action( 'ufsc_club_dashboard_sections', $club, $sections );
            ?>

            <div class="ufsc-dashboard-actions">
                <?php if ( current_user_can( 'edit_posts' ) || self::user_can_edit_club( $user_id, $club->id ) ) : ?>
                <a href="<?php echo esc_url( self::get_club_edit_url( $club->id ) ); ?>" class="button button-primary">
                    <?php echo esc_html__( 'Modifier les informations', 'ufsc-clubs' ); ?>
                </a>
                <?php endif; ?>
                
                <?php 
                // Additional action buttons
                do_action( 'ufsc_club_dashboard_actions', $club );
                ?>
            </div>
        </div>

        <style>
        .ufsc-club-dashboard {
            max-width: 800px;
            margin: 0 auto;
        }
        .ufsc-dashboard-header h2 {
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .ufsc-dashboard-section {
            background: #f9f9f9;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        .ufsc-dashboard-section h3 {
            margin-top: 0;
            color: #333;
        }
        .ufsc-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
        }
        .ufsc-info-item {
            display: flex;
            flex-direction: column;
        }
        .ufsc-info-item label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }
        .ufsc-club-name {
            font-size: 1.2em;
            font-weight: bold;
            color: #0073aa;
        }
        .ufsc-club-affiliation {
            font-family: monospace;
            font-size: 1.1em;
            background: #fff;
            padding: 5px 10px;
            border-radius: 3px;
            border: 1px solid #ddd;
        }
        .ufsc-region-badge {
            background: #0073aa;
            color: white;
            padding: 5px 15px;
            border-radius: 15px;
            display: inline-block;
        }
        .ufsc-quota-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        .ufsc-quota-label {
            color: #666;
            margin-left: 10px;
        }
        .ufsc-dashboard-actions {
            text-align: center;
            margin-top: 30px;
        }
        .ufsc-message {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .ufsc-message.ufsc-error {
            background: #ffeaa7;
            border-left: 4px solid #fdcb6e;
        }
        .ufsc-message.ufsc-info {
            background: #dff0ff;
            border-left: 4px solid #0073aa;
        }
        </style>
        <?php

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
        
        $club = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM `{$table}` WHERE responsable_id = %d LIMIT 1", 
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
        return UFSC_Badges::render_club_badge( $status );
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
        
        $result = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM `{$table}` WHERE id = %d AND responsable_id = %d", 
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
}