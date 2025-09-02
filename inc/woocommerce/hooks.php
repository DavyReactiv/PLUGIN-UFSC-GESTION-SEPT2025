<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce hooks for UFSC Gestion
 * Handles order processing and integrations
 */

/**
 * Initialize WooCommerce hooks
 */
function ufsc_init_woocommerce_hooks() {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }
    
    // Hook into order processing
    add_action( 'woocommerce_order_status_processing', 'ufsc_handle_order_processing' );
    add_action( 'woocommerce_order_status_completed', 'ufsc_handle_order_completed' );
}

/**
 * Handle order when it reaches processing status
 * 
 * @param int $order_id Order ID
 */
function ufsc_handle_order_processing( $order_id ) {
    ufsc_process_order_items( $order_id );
}

/**
 * Handle order when it reaches completed status
 * 
 * @param int $order_id Order ID
 */
function ufsc_handle_order_completed( $order_id ) {
    ufsc_process_order_items( $order_id );
}

/**
 * Process order items for UFSC products
 * 
 * @param int $order_id Order ID
 */
function ufsc_process_order_items( $order_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }
    
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }
    
    $wc_settings = ufsc_get_woocommerce_settings();
    $affiliation_product_id = $wc_settings['product_affiliation_id'];
    $license_product_id = $wc_settings['product_license_id'];
    
    foreach ( $order->get_items() as $item_id => $item ) {
        $product_id = $item->get_product_id();
        $quantity = $item->get_quantity();
        
        if ( $product_id == $affiliation_product_id ) {
            // Handle affiliation pack
            ufsc_handle_affiliation_pack_payment( $order, $item, $quantity );
        } elseif ( $product_id == $license_product_id ) {
            // Handle additional license
            ufsc_handle_additional_license_payment( $order, $item, $quantity );
        }
    }
}

/**
 * Handle affiliation pack payment
 * 
 * @param WC_Order $order Order object
 * @param WC_Order_Item_Product $item Item object
 * @param int $quantity Quantity purchased
 */
function ufsc_handle_affiliation_pack_payment( $order, $item, $quantity ) {
    $user_id = $order->get_user_id();
    $season = ufsc_get_woocommerce_settings()['season'];
    
    // Get club ID for this user
    $club_id = ufsc_get_user_club_id( $user_id );
    
    if ( $club_id ) {
        // Mark affiliation as paid for the season
        ufsc_mark_affiliation_paid( $club_id, $season );
        
        // Credit included licenses quota
        $included_licenses = ufsc_get_woocommerce_settings()['included_licenses'];
        $total_licenses = $included_licenses * $quantity;
        ufsc_quota_add_included( $club_id, $total_licenses, $season );
        
        // Log the action
        error_log( sprintf( 'UFSC: Affiliation pack processed for club %d, season %s, licenses credited: %d', 
            $club_id, $season, $total_licenses ) );
    }
}

/**
 * Handle additional license payment
 * 
 * @param WC_Order $order Order object
 * @param WC_Order_Item_Product $item Item object
 * @param int $quantity Quantity purchased
 */
function ufsc_handle_additional_license_payment( $order, $item, $quantity ) {
    $user_id = $order->get_user_id();
    $season = ufsc_get_woocommerce_settings()['season'];
    
    // Check if specific license IDs are attached to this line item
    $license_ids = $item->get_meta( '_ufsc_licence_ids' );
    
    if ( ! empty( $license_ids ) && is_array( $license_ids ) ) {
        // Mark specific licenses as paid
        foreach ( $license_ids as $license_id ) {
            ufsc_mark_licence_paid( $license_id, $season );
        }
        
        error_log( sprintf( 'UFSC: Specific licenses marked as paid: %s', implode( ', ', $license_ids ) ) );
    } else {
        // Credit prepaid licenses for future use
        $club_id = ufsc_get_user_club_id( $user_id );
        if ( $club_id ) {
            ufsc_quota_add_paid( $club_id, $quantity, $season );
            
            error_log( sprintf( 'UFSC: Prepaid licenses credited for club %d: %d licenses', $club_id, $quantity ) );
        }
    }
}

// STUB FUNCTIONS - TO BE IMPLEMENTED ACCORDING TO EXISTING DATABASE SCHEMA

/**
 * Get club ID for a user
 * TODO: Implement according to existing database schema
 * 
 * @param int $user_id User ID
 * @return int|false Club ID or false if not found
 */
function ufsc_get_user_club_id( $user_id ) {
    // STUB: This should query the existing database to find which club this user manages
    // Implementation depends on how the relationship between users and clubs is stored
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $club_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$clubs_table} WHERE responsable_id = %d",
        $user_id
    ) );
    
    return $club_id ? (int) $club_id : false;
    */
    
    // Temporary fallback for testing
    return false;
}

/**
 * Mark affiliation as paid for a season
 * TODO: Implement according to existing database schema
 * 
 * @param int $club_id Club ID
 * @param string $season Season identifier
 */
function ufsc_mark_affiliation_paid( $club_id, $season ) {
    // STUB: This should update the club record to mark affiliation as paid for the season
    // Implementation depends on how affiliation payment status is stored
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $wpdb->update(
        $clubs_table,
        array( 'affiliation_paid_' . str_replace( '-', '_', $season ) => 1 ),
        array( 'id' => $club_id ),
        array( '%d' ),
        array( '%d' )
    );
    */
}

/**
 * Mark a specific license as paid
 * TODO: Implement according to existing database schema
 * 
 * @param int $license_id License ID
 * @param string $season Season identifier
 */
function ufsc_mark_licence_paid( $license_id, $season ) {
    // STUB: This should update the license record to mark it as paid for the season
    // Implementation depends on how license payment status is stored
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $licences_table = ufsc_get_licences_table();
    
    $wpdb->update(
        $licences_table,
        array( 'paid_season' => $season, 'is_included' => 0 ),
        array( 'id' => $license_id ),
        array( '%s', '%d' ),
        array( '%d' )
    );
    */
}

/**
 * Add included licenses to club quota
 * TODO: Implement according to existing database schema
 * 
 * @param int $club_id Club ID
 * @param int $quantity Number of licenses to add
 * @param string $season Season identifier
 */
function ufsc_quota_add_included( $club_id, $quantity, $season ) {
    // STUB: This should add included licenses to the club's quota for the season
    // Implementation depends on how quota is tracked in the database
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$clubs_table} 
         SET quota_included = quota_included + %d 
         WHERE id = %d",
        $quantity,
        $club_id
    ) );
    */
}

/**
 * Add paid licenses to club quota
 * TODO: Implement according to existing database schema
 * 
 * @param int $club_id Club ID
 * @param int $quantity Number of licenses to add
 * @param string $season Season identifier
 */
function ufsc_quota_add_paid( $club_id, $quantity, $season ) {
    // STUB: This should add paid licenses to the club's available quota for the season
    // Implementation depends on how quota is tracked in the database
    
    // Example implementation (to be adjusted):
    /*
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $wpdb->query( $wpdb->prepare(
        "UPDATE {$clubs_table} 
         SET quota_paid = quota_paid + %d 
         WHERE id = %d",
        $quantity,
        $club_id
    ) );
    */
}