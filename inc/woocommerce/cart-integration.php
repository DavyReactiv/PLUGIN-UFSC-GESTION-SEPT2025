<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WooCommerce cart integration for UFSC Gestion
 * Handles secure add-to-cart and meta transfer to orders
 */

/**
 * Initialize cart integration hooks
 */
function ufsc_init_cart_integration() {
    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }
    
    // Handle secure add to cart requests
    add_action( 'admin_post_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );
    add_action( 'admin_post_nopriv_ufsc_add_to_cart', 'ufsc_handle_add_to_cart_secure' );
    
    // Transfer meta data from cart to order
    add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_transfer_cart_meta_to_order', 10, 4 );
}

/**
 * Handle secure add to cart requests posted via admin-post.php
 */
function ufsc_handle_add_to_cart_secure() {
    // Verify nonce
    $nonce = isset( $_POST['_ufsc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ufsc_nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ufsc_add_to_cart_action' ) ) {
        wc_add_notice( __( 'Action non autorisée', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wc_add_notice( __( 'Vous devez être connecté pour effectuer cette action', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
    if ( ! $product_id ) {
        wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->exists() ) {
        wc_add_notice( __( 'Produit non trouvé', 'ufsc-clubs' ), 'error' );
        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
        exit;
    }

    $quantity       = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;
    $cart_item_data = array();

    // Add club ID if provided
    if ( isset( $_POST['ufsc_club_id'] ) ) {
        $club_id = absint( $_POST['ufsc_club_id'] );
        if ( $club_id > 0 ) {
            $cart_item_data['ufsc_club_id'] = $club_id;
        }
    }

    // Add license IDs if provided
    if ( isset( $_POST['ufsc_license_ids'] ) ) {
        $license_ids_string = sanitize_text_field( wp_unslash( $_POST['ufsc_license_ids'] ) );
        $license_ids        = array_filter( array_map( 'absint', explode( ',', $license_ids_string ) ) );

        if ( ! empty( $license_ids ) ) {
            $cart_item_data['ufsc_license_ids'] = $license_ids;
            $quantity                           = count( $license_ids ); // Override quantity to match license count
        }
    }

    // Add to cart
    if ( function_exists( 'wc_load_cart' ) ) {
        wc_load_cart();
    }
    $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, 0, array(), $cart_item_data );

    if ( $cart_item_key ) {
        wc_add_notice(
            sprintf( __( '%s ajouté au panier', 'ufsc-clubs' ), $product->get_name() ),
            'success'
        );
    } else {
        wc_add_notice( __( 'Erreur lors de l\'ajout au panier', 'ufsc-clubs' ), 'error' );
    }

    // Redirect back to the referring page
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : wc_get_cart_url() );
    exit;
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

    // Transfer personal data
    if ( isset( $values['ufsc_nom'] ) ) {
        $item->add_meta_data( '_ufsc_nom', sanitize_text_field( $values['ufsc_nom'] ) );
    }
    if ( isset( $values['ufsc_prenom'] ) ) {
        $item->add_meta_data( '_ufsc_prenom', sanitize_text_field( $values['ufsc_prenom'] ) );
    }
    if ( isset( $values['ufsc_date_naissance'] ) ) {
        $item->add_meta_data( '_ufsc_date_naissance', sanitize_text_field( $values['ufsc_date_naissance'] ) );
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

    // Display personal data
    if ( isset( $cart_item['ufsc_nom'] ) ) {
        $item_data[] = array(
            'key'   => __( 'Nom', 'ufsc-clubs' ),
            'value' => sanitize_text_field( $cart_item['ufsc_nom'] ),
        );
    }
    if ( isset( $cart_item['ufsc_prenom'] ) ) {
        $item_data[] = array(
            'key'   => __( 'Prénom', 'ufsc-clubs' ),
            'value' => sanitize_text_field( $cart_item['ufsc_prenom'] ),
        );
    }
    if ( isset( $cart_item['ufsc_date_naissance'] ) && $cart_item['ufsc_date_naissance'] ) {
        $item_data[] = array(
            'key'   => __( 'Date de naissance', 'ufsc-clubs' ),
            'value' => sanitize_text_field( $cart_item['ufsc_date_naissance'] ),
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


    return ! empty( $club_name ) ? $club_name : false;

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
