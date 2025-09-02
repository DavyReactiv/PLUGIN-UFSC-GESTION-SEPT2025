<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce admin actions for UFSC Gestion
 * Handles creation of orders for additional licenses
 */

/**
 * Check if club should be charged for additional licenses
 * TODO: Implement according to existing database schema
 * 
 * @param int $club_id Club ID
 * @param string $season Season identifier
 * @return bool True if club has exhausted included quota
 */
function ufsc_should_charge_license( $club_id, $season ) {
    // STUB: This should check if the club has exhausted its included quota
    // Implementation depends on how quota tracking is implemented in the database
    
    // Example logic (to be implemented according to actual schema):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    $licences_table = ufsc_get_licences_table();
    
    // Get total credits from paid packs for this season
    $pack_credits = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(quota_included), 0) FROM {$clubs_table} 
         WHERE id = %d AND affiliation_paid_season = %s",
        $club_id,
        $season
    ) );
    
    // Get total paid additional licenses
    $paid_additional = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(quota_paid), 0) FROM {$clubs_table} 
         WHERE id = %d",
        $club_id
    ) );
    
    // Get total licenses used (both included and paid)
    $used_licenses = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$licences_table} 
         WHERE club_id = %d AND (is_included = 1 OR paid_season = %s)",
        $club_id,
        $season
    ) );
    
    $total_available = $pack_credits + $paid_additional;
    return $used_licenses >= $total_available;
    */
    
    // Temporary implementation for testing
    return false;
}

/**
 * Create WooCommerce order for additional licenses
 * 
 * @param int $club_id Club ID
 * @param array $license_ids Array of license IDs to attach to order
 * @param int $user_id User ID to assign the order to
 * @return int|false Order ID on success, false on failure
 */
function ufsc_create_additional_license_order( $club_id, $license_ids = array(), $user_id = 0 ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return false;
    }
    
    $wc_settings = ufsc_get_woocommerce_settings();
    $license_product_id = $wc_settings['product_license_id'];
    
    // Validate product exists
    $product = wc_get_product( $license_product_id );
    if ( ! $product || ! $product->exists() ) {
        return false;
    }
    
    $quantity = max( 1, count( $license_ids ) );
    
    try {
        // Create order
        $order = wc_create_order();
        
        if ( ! $order ) {
            return false;
        }
        
        // Set customer
        if ( $user_id > 0 ) {
            $order->set_customer_id( $user_id );
        }
        
        // Add product to order
        $item_id = $order->add_product( $product, $quantity );
        
        if ( ! $item_id ) {
            $order->delete( true );
            return false;
        }
        
        // Attach license IDs as meta if provided
        if ( ! empty( $license_ids ) ) {
            wc_add_order_item_meta( $item_id, '_ufsc_licence_ids', $license_ids );
        }
        
        // Add club ID as meta
        wc_add_order_item_meta( $item_id, '_ufsc_club_id', $club_id );
        
        // Calculate totals
        $order->calculate_totals();
        
        // Set order status
        $order->update_status( 'pending', __( 'Commande créée pour licences UFSC additionnelles', 'ufsc-clubs' ) );
        
        // Add order note
        $order->add_order_note( sprintf( 
            __( 'Commande créée automatiquement pour %d licence(s) additionnelle(s) - Club ID: %d', 'ufsc-clubs' ),
            $quantity,
            $club_id
        ) );
        
        return $order->get_id();
        
    } catch ( Exception $e ) {
        error_log( 'UFSC: Error creating additional license order: ' . $e->getMessage() );
        return false;
    }
}

/**
 * Get payment URL for an order
 * 
 * @param int $order_id Order ID
 * @return string Payment URL
 */
function ufsc_get_order_payment_url( $order_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return '';
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return '';
    }
    
    return $order->get_checkout_payment_url();
}

/**
 * Send payment link email to user
 * 
 * @param int $order_id Order ID
 * @param int $user_id User ID
 * @param array $license_ids License IDs associated with the order
 * @return bool True on success
 */
function ufsc_send_payment_link_email( $order_id, $user_id, $license_ids = array() ) {
    $user = get_user_by( 'id', $user_id );
    if ( ! $user ) {
        return false;
    }
    
    $payment_url = ufsc_get_order_payment_url( $order_id );
    if ( empty( $payment_url ) ) {
        return false;
    }
    
    $license_count = count( $license_ids );
    $subject = sprintf( 
        __( 'Paiement requis pour %d licence(s) UFSC additionnelle(s)', 'ufsc-clubs' ),
        $license_count 
    );
    
    $message = sprintf(
        __( 'Bonjour %s,

Une commande a été créée pour %d licence(s) UFSC additionnelle(s).

Pour finaliser votre commande, veuillez procéder au paiement en cliquant sur le lien suivant :
%s

Commande #%d

Cordialement,
L\'équipe UFSC', 'ufsc-clubs' ),
        $user->display_name,
        $license_count,
        $payment_url,
        $order_id
    );
    
    return wp_mail( $user->user_email, $subject, $message );
}

/**
 * Handle admin action to send licenses to payment
 * This function should be called from admin interface
 */
function ufsc_handle_admin_send_to_payment() {
    // Verify nonce and capabilities
    if ( ! check_admin_referer( 'ufsc_send_to_payment' ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'Erreur de sécurité', 'ufsc-clubs' ) );
    }
    
    $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
    $license_ids = isset( $_POST['license_ids'] ) && is_array( $_POST['license_ids'] ) 
        ? array_map( 'absint', $_POST['license_ids'] ) 
        : array();
    
    if ( ! $club_id || empty( $license_ids ) ) {
        wp_die( __( 'Données manquantes', 'ufsc-clubs' ) );
    }
    
    // Get user ID for this club (stub - implement according to your schema)
    $user_id = ufsc_get_club_responsible_user_id( $club_id );
    if ( ! $user_id ) {
        wp_die( __( 'Utilisateur responsable du club non trouvé', 'ufsc-clubs' ) );
    }
    
    // Create order
    $order_id = ufsc_create_additional_license_order( $club_id, $license_ids, $user_id );
    if ( ! $order_id ) {
        wp_die( __( 'Erreur lors de la création de la commande', 'ufsc-clubs' ) );
    }
    
    // Send email
    $email_sent = ufsc_send_payment_link_email( $order_id, $user_id, $license_ids );
    
    $redirect_url = admin_url( 'admin.php?page=ufsc-licences' );
    $message = $email_sent 
        ? __( 'Commande créée et email envoyé avec succès', 'ufsc-clubs' )
        : __( 'Commande créée mais erreur lors de l\'envoi de l\'email', 'ufsc-clubs' );
    
    wp_safe_redirect( add_query_arg( array( 'message' => urlencode( $message ) ), $redirect_url ) );
    exit;
}

/**
 * Get responsible user ID for a club
 * TODO: Implement according to existing database schema
 * 
 * @param int $club_id Club ID
 * @return int|false User ID or false if not found
 */
function ufsc_get_club_responsible_user_id( $club_id ) {
    // STUB: This should query the existing database to find the responsible user for this club
    // Implementation depends on how the relationship is stored
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT responsable_id FROM {$clubs_table} WHERE id = %d",
        $club_id
    ) );
    
    return $user_id ? (int) $user_id : false;
    */
    
    // Temporary fallback
    return false;
}

// Register admin action
add_action( 'admin_post_ufsc_send_to_payment', 'ufsc_handle_admin_send_to_payment' );