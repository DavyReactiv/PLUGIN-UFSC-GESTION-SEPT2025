<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin menu for UFSC Gestion
 * Handles the main menu and sub-menu registration
 */

/**
 * Register UFSC Gestion admin menu
 */
function ufsc_register_admin_menu() {
    // Main menu page
    add_menu_page(
        __( 'UFSC Gestion', 'ufsc-clubs' ),
        __( 'UFSC Gestion', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion',
        'ufsc_render_dashboard_page',
        'dashicons-groups',
        58
    );
    
    // Dashboard submenu (same as main page)
    add_submenu_page(
        'ufsc-gestion',
        __( 'Tableau de bord', 'ufsc-clubs' ),
        __( 'Tableau de bord', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion',
        'ufsc_render_dashboard_page'
    );
    
    // Clubs submenu
    add_submenu_page(
        'ufsc-gestion',
        __( 'Clubs', 'ufsc-clubs' ),
        __( 'Clubs', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion-clubs',
        'ufsc_render_clubs_page'
    );
    
    // Licences submenu
    add_submenu_page(
        'ufsc-gestion',
        __( 'Licences', 'ufsc-clubs' ),
        __( 'Licences', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion-licences',
        'ufsc_render_licences_page'
    );
    
    // Paramètres submenu
    add_submenu_page(
        'ufsc-gestion',
        __( 'Paramètres', 'ufsc-clubs' ),
        __( 'Paramètres', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion-parametres',
        'ufsc_render_settings_page'
    );
    
    // WooCommerce submenu
    add_submenu_page(
        'ufsc-gestion',
        __( 'WooCommerce', 'ufsc-clubs' ),
        __( 'WooCommerce', 'ufsc-clubs' ),
        'manage_options',
        'ufsc-gestion-woocommerce',
        'ufsc_render_woocommerce_settings_page'
    );
}

/**
 * Render dashboard page
 */
function ufsc_render_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'UFSC Gestion - Tableau de bord', 'ufsc-clubs' ); ?></h1>
        
        <div class="ufsc-dashboard-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
            
            <!-- Clubs Card -->
            <div class="ufsc-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'Clubs', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-kpi" style="font-size: 2em; font-weight: bold; color: #2271b1; margin: 10px 0;">
                    <?php echo ufsc_get_clubs_count(); ?>
                </p>
                <p><?php esc_html_e( 'Clubs total dans le système', 'ufsc-clubs' ); ?></p>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-clubs' ); ?>" class="button">
                        <?php esc_html_e( 'Gérer les clubs', 'ufsc-clubs' ); ?>
                    </a>
                </p>
            </div>
            
            <!-- Licences Card -->
            <div class="ufsc-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'Licences', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-kpi" style="font-size: 2em; font-weight: bold; color: #00a32a; margin: 10px 0;">
                    <?php echo ufsc_get_licences_count(); ?>
                </p>
                <p><?php esc_html_e( 'Licences total dans le système', 'ufsc-clubs' ); ?></p>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-licences' ); ?>" class="button">
                        <?php esc_html_e( 'Gérer les licences', 'ufsc-clubs' ); ?>
                    </a>
                </p>
            </div>
            
            <!-- Régions Card -->
            <div class="ufsc-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'Régions', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-kpi" style="font-size: 2em; font-weight: bold; color: #d63638; margin: 10px 0;">
                    <?php echo count( ufsc_get_regions_labels() ); ?>
                </p>
                <p><?php esc_html_e( 'Régions configurées', 'ufsc-clubs' ); ?></p>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-parametres' ); ?>" class="button">
                        <?php esc_html_e( 'Voir les paramètres', 'ufsc-clubs' ); ?>
                    </a>
                </p>
            </div>
            
            <!-- WooCommerce Card -->
            <div class="ufsc-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h3><?php esc_html_e( 'WooCommerce', 'ufsc-clubs' ); ?></h3>
                <p class="ufsc-kpi" style="font-size: 2em; margin: 10px 0;">
                    <?php if ( ufsc_is_woocommerce_active() ): ?>
                        <span style="color: #00a32a;">✓</span>
                    <?php else: ?>
                        <span style="color: #d63638;">✗</span>
                    <?php endif; ?>
                </p>
                <p>
                    <?php if ( ufsc_is_woocommerce_active() ): ?>
                        <?php esc_html_e( 'WooCommerce activé', 'ufsc-clubs' ); ?>
                    <?php else: ?>
                        <?php esc_html_e( 'WooCommerce non activé', 'ufsc-clubs' ); ?>
                    <?php endif; ?>
                </p>
                <p>
                    <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-woocommerce' ); ?>" class="button">
                        <?php esc_html_e( 'Configuration WooCommerce', 'ufsc-clubs' ); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <div class="ufsc-info-section" style="margin-top: 30px; background: #f9f9f9; padding: 20px; border-radius: 5px;">
            <h2><?php esc_html_e( 'Actions rapides', 'ufsc-clubs' ); ?></h2>
            <p>
                <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-clubs&action=new' ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Ajouter un club', 'ufsc-clubs' ); ?>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-licences&action=new' ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Ajouter une licence', 'ufsc-clubs' ); ?>
                </a>
            </p>
            
            <h3><?php esc_html_e( 'Saison courante', 'ufsc-clubs' ); ?></h3>
            <p>
                <?php 
                $wc_settings = ufsc_get_woocommerce_settings();
                echo esc_html( $wc_settings['season'] );
                ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Render clubs page placeholder
 */
function ufsc_render_clubs_page() {
    require_once __DIR__ . '/class-ufsc-gestion-clubs-list-table.php';
    $list_table = new UFSC_Gestion_Clubs_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Gestion des Clubs', 'ufsc-clubs' ); ?></h1>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'Liste des clubs provenant de la base de données.', 'ufsc-clubs' ); ?></p>
            <p><?php esc_html_e( 'Table configurée:', 'ufsc-clubs' ); ?> <strong><?php echo esc_html( ufsc_get_clubs_table() ); ?></strong></p>
        </div>

        <p>
            <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-clubs&action=new' ); ?>" class="button button-primary">
                <?php esc_html_e( 'Ajouter un club', 'ufsc-clubs' ); ?>
            </a>
        </p>

        <form method="get">
            <input type="hidden" name="page" value="ufsc-gestion-clubs" />
            <?php $list_table->display(); ?>
        </form>
    </div>
    <?php
}

/**
 * Render licences page placeholder
 */
function ufsc_render_licences_page() {
    require_once __DIR__ . '/class-ufsc-gestion-licences-list-table.php';
    $list_table = new UFSC_Gestion_Licences_List_Table();
    $list_table->prepare_items();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Gestion des Licences', 'ufsc-clubs' ); ?></h1>
        <div class="notice notice-info">
            <p><?php esc_html_e( 'Liste des licences provenant de la base de données.', 'ufsc-clubs' ); ?></p>
            <p><?php esc_html_e( 'Table configurée:', 'ufsc-clubs' ); ?> <strong><?php echo esc_html( ufsc_get_licences_table() ); ?></strong></p>
        </div>

        <p>
            <a href="<?php echo admin_url( 'admin.php?page=ufsc-gestion-licences&action=new' ); ?>" class="button button-primary">
                <?php esc_html_e( 'Ajouter une licence', 'ufsc-clubs' ); ?>
            </a>
        </p>

        <form method="get">
            <input type="hidden" name="page" value="ufsc-gestion-licences" />
            <?php $list_table->display(); ?>
        </form>

        <h3><?php esc_html_e( 'Actions sur les licences sélectionnées', 'ufsc-clubs' ); ?></h3>
        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>">
            <?php wp_nonce_field( 'ufsc_send_to_payment' ); ?>
            <input type="hidden" name="action" value="ufsc_send_to_payment" />
            <input type="hidden" name="club_id" value="1" />
            <input type="hidden" name="license_ids[]" value="1" />
            <input type="hidden" name="license_ids[]" value="2" />

            <p>
                <input type="submit" class="button button-secondary" value="<?php esc_attr_e( 'Envoyer au paiement (exemple)', 'ufsc-clubs' ); ?>" />
            </p>
            <p class="description">
                <?php esc_html_e( 'Cette action créera une commande WooCommerce pour les licences sélectionnées et enverra un lien de paiement à l\'utilisateur responsable du club.', 'ufsc-clubs' ); ?>
            </p>
        </form>
    </div>
    <?php
}

/**
 * Get clubs count from database
 */
function ufsc_get_clubs_count() {
    global $wpdb;
    
    $clubs_table = ufsc_sanitize_table_name( ufsc_get_clubs_table() );



    

    if ( ! ufsc_table_exists( $clubs_table ) ) {
        return 0;
    }

    $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}`" );
    return (int) $count;
}

/**
 * Get licences count from database
 */
function ufsc_get_licences_count() {
    global $wpdb;
    
    $licences_table = ufsc_sanitize_table_name( ufsc_get_licences_table() );



    
    if ( ! ufsc_table_exists( $licences_table ) ) {
        return 0;
    }

    $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$licences_table}`" );
    return (int) $count;
}

// Register the menu
// add_action( 'admin_menu', 'ufsc_register_admin_menu' );

