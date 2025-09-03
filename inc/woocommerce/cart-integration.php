<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce cart integration for UFSC Gestion
 * Handles add-to-cart by URL and meta transfer to orders
 */

/**
 * Initialize cart integration hooks
 */
function ufsc_init_cart_integration() {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }
    
    // Handle add to cart via URL parameters
    add_action( 'init', 'ufsc_handle_add_to_cart_url' );
    
    // Transfer meta data from cart to order
    add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_transfer_cart_meta_to_order', 10, 4 );
}

/**
 * Handle add to cart via URL parameters
 * Supports URLs like: ?ufsc_add_to_cart=product_id&ufsc_club_id=123&ufsc_license_ids=1,2,3
 */
function ufsc_handle_add_to_cart_url() {
    if ( ! isset( $_GET['ufsc_add_to_cart'] ) ) {
        return;
    }
    
    $product_id = absint( $_GET['ufsc_add_to_cart'] );
    if ( ! $product_id ) {
        return;
    }
    
    // Verify product exists
    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->exists() ) {
        wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
        return;
    }
    
    $quantity = isset( $_GET['quantity'] ) ? absint( $_GET['quantity'] ) : 1;
    $cart_item_data = array();
    
    // Add club ID if provided
    if ( isset( $_GET['ufsc_club_id'] ) ) {
        $club_id = absint( $_GET['ufsc_club_id'] );
        if ( $club_id > 0 ) {
            $cart_item_data['ufsc_club_id'] = $club_id;
        }
    }
    
    // Add license IDs if provided
    if ( isset( $_GET['ufsc_license_ids'] ) ) {
        $license_ids_string = sanitize_text_field( $_GET['ufsc_license_ids'] );
        $license_ids = array_filter( array_map( 'absint', explode( ',', $license_ids_string ) ) );
        
        if ( ! empty( $license_ids ) ) {
            $cart_item_data['ufsc_license_ids'] = $license_ids;
            $quantity = count( $license_ids ); // Override quantity to match license count
        }
    }
    
    // Add to cart
    $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );
    
    if ( $cart_item_key ) {
        wc_add_notice( 
            sprintf( __( '%s ajouté au panier', 'ufsc-clubs' ), $product->get_name() ), 
            'success' 
        );
        
        // Redirect to cart or checkout if specified
        $redirect = isset( $_GET['ufsc_redirect'] ) ? sanitize_text_field( $_GET['ufsc_redirect'] ) : '';
        
        switch ( $redirect ) {
            case 'cart':
                wp_safe_redirect( wc_get_cart_url() );
                exit;
                
            case 'checkout':
                wp_safe_redirect( wc_get_checkout_url() );
                exit;
        }
    } else {
        wc_add_notice( __( 'Erreur lors de l\'ajout au panier', 'ufsc-clubs' ), 'error' );
    }
}

/**
 * Transfer custom meta from cart to order line items
 * 
 * @param WC_Order_Item_Product $item Order line item
 * @param string $cart_item_key Cart item key
 * @param array $values Cart item values
 * @param WC_Order $order Order object
 */
function ufsc_transfer_cart_meta_to_order( $item, $cart_item_key, $values, $order ) {
    // Transfer club ID
    if ( isset( $values['ufsc_club_id'] ) ) {
        $item->add_meta_data( '_ufsc_club_id', $values['ufsc_club_id'] );
    }
    
    // Transfer license IDs
    if ( isset( $values['ufsc_license_ids'] ) && is_array( $values['ufsc_license_ids'] ) ) {
        $item->add_meta_data( '_ufsc_licence_ids', $values['ufsc_license_ids'] );
    }
}

/**
 * Display custom cart item data
 * 
 * @param array $item_data Item data
 * @param array $cart_item Cart item
 * @return array Modified item data
 */
function ufsc_display_cart_item_data( $item_data, $cart_item ) {
    // Display club info
    if ( isset( $cart_item['ufsc_club_id'] ) ) {
        $club_id = $cart_item['ufsc_club_id'];
        $club_name = ufsc_get_club_name( $club_id );
        
        if ( $club_name ) {
            $item_data[] = array(
                'key'   => __( 'Club', 'ufsc-clubs' ),
                'value' => $club_name,
            );
        }
    }
    
    // Display license IDs
    if ( isset( $cart_item['ufsc_license_ids'] ) && is_array( $cart_item['ufsc_license_ids'] ) ) {
        $license_count = count( $cart_item['ufsc_license_ids'] );
        $item_data[] = array(
            'key'   => __( 'Licences', 'ufsc-clubs' ),
            'value' => sprintf( __( '%d licence(s) spécifique(s)', 'ufsc-clubs' ), $license_count ),
        );
    }
    
    return $item_data;
}

/**
 * Get club name by ID.
 *

 * Looks up the `nom` column in the configured clubs table. Returns the name
 * or `false` when no matching club exists.
 *
 * @param int $club_id Club ID
 * @return string|false Club name or false if not found

 * Reads the configured clubs table and returns the `nom` column for the
 * requested club. If the club doesn't exist an empty value is returned.
 *
 * @param int $club_id Club ID.
 * @return string|false Club name or false if not found.

 */
function ufsc_get_club_name( $club_id ) {
    global $wpdb;



    if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return false;
    }


    $clubs_table = ufsc_get_clubs_table();

    $club_name = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT nom FROM {$clubs_table} WHERE id = %d",
            $club_id
        )
    );


    return $club_name !== null ? $club_name : false;

    return $club_name ? $club_name : false;

}

/**
 * Add affiliation pack to cart for a club
 * This function can be called from other parts of the plugin
 * 
 * @param int $club_id Club ID
 * @return bool True on success
 */
function ufsc_add_affiliation_to_cart( $club_id ) {
    if ( ! ufsc_is_woocommerce_active() ) {
        return false;
    }
    
    $wc_settings = ufsc_get_woocommerce_settings();
    $product_id = $wc_settings['product_affiliation_id'];
    
    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->exists() ) {
        return false;
    }
    
    $cart_item_data = array(
        'ufsc_club_id' => $club_id
    );
    
    $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );
    
    return $cart_item_key !== false;
}

// Initialize cart integration if WooCommerce is active
add_action( 'plugins_loaded', 'ufsc_init_cart_integration' );

// Hook to display cart item data
add_filter( 'woocommerce_get_item_data', 'ufsc_display_cart_item_data', 10, 2 );