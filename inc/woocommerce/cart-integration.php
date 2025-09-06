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

    // Transfer meta data from cart to order
    add_action( 'woocommerce_checkout_create_order_line_item', 'ufsc_transfer_cart_meta_to_order', 10, 4 );

    // Enforce single quantity for licence product
    add_filter( 'woocommerce_quantity_input_args', 'ufsc_limit_licence_quantity', 10, 2 );

    // Validate licence items on add to cart
    add_filter( 'woocommerce_add_to_cart_validation', 'ufsc_validate_licence_cart_item', 10, 6 );

    // Validate quantity changes in cart
    add_filter( 'woocommerce_update_cart_validation', 'ufsc_validate_cart_update_quantity', 10, 4 );

    // Inform users that each licence is nominative on product page
    add_action( 'woocommerce_single_product_summary', 'ufsc_licence_nominative_notice', 25 );

    // Store custom meta on add to cart
    add_filter( 'woocommerce_add_cart_item_data', 'ufsc_add_cart_item_data', 10, 2 );

    // Redirect after add to cart if configured
    add_filter( 'woocommerce_add_to_cart_redirect', 'ufsc_redirect_after_add_to_cart' );
}

/**
 * Capture UFSC meta when items are added to cart.
 *
 * @param array $cart_item_data Existing cart item data.
 * @param int   $product_id     Product ID being added.
 * @return array
 */
function ufsc_add_cart_item_data( $cart_item_data, $product_id ) {
    if ( isset( $_REQUEST['ufsc_club_id'] ) ) {
        $club_id = absint( $_REQUEST['ufsc_club_id'] );
        if ( $club_id > 0 ) {
            $cart_item_data['ufsc_club_id'] = $club_id;
        }
    }

    if ( isset( $_REQUEST['ufsc_license_ids'] ) ) {
        $ids_string = sanitize_text_field( wp_unslash( $_REQUEST['ufsc_license_ids'] ) );
        $ids        = array_filter( array_map( 'absint', explode( ',', $ids_string ) ) );
        if ( ! empty( $ids ) ) {
            $cart_item_data['ufsc_license_ids'] = $ids;
        }
    }

    if ( isset( $_REQUEST['ufsc_licence_id'] ) ) {
        $cart_item_data['ufsc_licence_id'] = absint( $_REQUEST['ufsc_licence_id'] );
    }

    if ( isset( $_REQUEST['ufsc_nom'] ) ) {
        $cart_item_data['ufsc_nom'] = sanitize_text_field( wp_unslash( $_REQUEST['ufsc_nom'] ) );
    }

    if ( isset( $_REQUEST['ufsc_prenom'] ) ) {
        $cart_item_data['ufsc_prenom'] = sanitize_text_field( wp_unslash( $_REQUEST['ufsc_prenom'] ) );
    }

    if ( isset( $_REQUEST['ufsc_date_naissance'] ) ) {
        $cart_item_data['ufsc_date_naissance'] = sanitize_text_field( wp_unslash( $_REQUEST['ufsc_date_naissance'] ) );
    }

    return $cart_item_data;
}

/**
 * Redirect after add to cart based on option.
 *
 * @param string $url Default redirect URL.
 * @return string
 */
function ufsc_redirect_after_add_to_cart( $url ) {
    $settings = ufsc_get_woocommerce_settings();
    $choice   = isset( $settings['redirect_after_add_to_cart'] ) ? $settings['redirect_after_add_to_cart'] : 'none';

    if ( 'cart' === $choice && function_exists( 'wc_get_cart_url' ) ) {
        return wc_get_cart_url();
    }

    if ( 'checkout' === $choice && function_exists( 'wc_get_checkout_url' ) ) {
        return wc_get_checkout_url();
    }

    return $url;
}

/**
 * Limit quantity input for licence product 2934 to 1.
 *
 * @param array      $args    Quantity input arguments.
 * @param WC_Product $product Product object.
 * @return array Modified arguments.
 */
function ufsc_limit_licence_quantity( $args, $product ) {
    if ( $product && (int) $product->get_id() === 2934 ) {
        $args['max_value']   = 1;
        $args['min_value']   = 1;
        $args['input_value'] = 1;
    }
    return $args;
}

/**
 * Validate licence cart item before it is added to the cart.
 *
 * Ensures product 2934 is limited to quantity 1 and that ufsc_licence_id is
 * unique in the cart.
 *
 * @param bool  $passed         Whether validation passed.
 * @param int   $product_id     Product ID being added.
 * @param int   $quantity       Quantity requested.
 * @param int   $variation_id   Variation ID.
 * @param array $variations     Variation data.
 * @param array $cart_item_data Additional cart item data.
 * @return bool Validation result.
 */
function ufsc_validate_licence_cart_item( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
    if ( (int) $product_id === 2934 && $quantity > 1 ) {
        wc_add_notice( __( 'Chaque licence est nominative. Vous ne pouvez en ajouter qu\'une seule à la fois.', 'ufsc-clubs' ), 'error' );
        return false;
    }

    if ( isset( $cart_item_data['ufsc_licence_id'] ) ) {
        $new_id = (int) $cart_item_data['ufsc_licence_id'];
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( isset( $item['ufsc_licence_id'] ) && (int) $item['ufsc_licence_id'] === $new_id ) {
                wc_add_notice( __( 'Chaque licence est nominative. Cette licence est déjà dans votre panier.', 'ufsc-clubs' ), 'error' );
                return false;
            }
        }
    }

    return $passed;
}

/**
 * Validate quantity updates directly in the cart.
 *
 * @param bool  $passed        Whether validation passed.
 * @param string $cart_item_key Cart item key.
 * @param array $values         Cart item values.
 * @param int   $quantity       New quantity.
 * @return bool
 */
function ufsc_validate_cart_update_quantity( $passed, $cart_item_key, $values, $quantity ) {
    if ( (int) $values['product_id'] === 2934 && $quantity > 1 ) {
        wc_add_notice( __( 'Chaque licence est nominative. Vous ne pouvez en ajouter qu\'une seule à la fois.', 'ufsc-clubs' ), 'error' );
        return false;
    }
    return $passed;
}

/**
 * Display UX notice on product page for licence product.
 */
function ufsc_licence_nominative_notice() {
    global $product;
    if ( $product && (int) $product->get_id() === 2934 ) {
        echo '<p class="ufsc-licence-note">' . esc_html__( 'Chaque licence est nominative. Ajoutez-les une par une.', 'ufsc-clubs' ) . '</p>';
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

    // Transfer single licence ID
    if ( isset( $values['ufsc_licence_id'] ) ) {
        $item->add_meta_data( '_ufsc_licence_id', absint( $values['ufsc_licence_id'] ) );
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

    // Display single licence ID
    if ( isset( $cart_item['ufsc_licence_id'] ) ) {
        $item_data[] = array(
            'key'   => __( 'Licence', 'ufsc-clubs' ),
            'value' => '#' . intval( $cart_item['ufsc_licence_id'] ),
        );
    }

    // Display holder full name
    if ( isset( $cart_item['ufsc_nom'] ) || isset( $cart_item['ufsc_prenom'] ) ) {
        $nom    = isset( $cart_item['ufsc_nom'] ) ? sanitize_text_field( $cart_item['ufsc_nom'] ) : '';
        $prenom = isset( $cart_item['ufsc_prenom'] ) ? sanitize_text_field( $cart_item['ufsc_prenom'] ) : '';
        $full   = trim( $nom . ' ' . $prenom );
        if ( $full ) {
            $item_data[] = array(
                'key'   => __( 'Titulaire', 'ufsc-clubs' ),
                'value' => $full,
            );
        }
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
    if ( $cart_item_key ) {
        wc_add_to_cart_message( array( $product_id => 1 ), true );
    }

    return $cart_item_key !== false;
}

/**
 * Apply included licence quota to cart items.
 *
 * Sets licence product price to zero when the club still has included
 * licences available. Flagged items receive the ufsc_consumes_included
 * marker so the quota can be updated on successful order.
 *
 * @param WC_Cart $cart Cart object.
 */
function ufsc_apply_included_quota_to_cart( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( ! ufsc_is_woocommerce_active() ) {
        return;
    }

    $count = 0;
    foreach ( $cart->get_cart() as $key => $item ) {
        if ( empty( $item['is_included'] ) ) {
            continue;
        }

        $product = $item['data'];
        if ( $count < 10 ) {
            $product->set_price( 0 );
            $cart->cart_contents[ $key ]['ufsc_consumes_included'] = 1;
        } else {
            $product->set_price( $product->get_regular_price() );
            $cart->cart_contents[ $key ]['ufsc_consumes_included'] = 0;
        }
        $count++;
    }
}

add_action( 'woocommerce_before_calculate_totals', 'ufsc_apply_included_quota_to_cart', 10, 1 );

// Initialize cart integration if WooCommerce is active
add_action( 'plugins_loaded', 'ufsc_init_cart_integration' );

// Hook to display cart item data
add_filter( 'woocommerce_get_item_data', 'ufsc_display_cart_item_data', 10, 2 );

if ( ! function_exists( 'ufsc_redirect_with_notice' ) ) {
    /**
     * Redirect helper that appends notice query args.
     *
     * @param string $message      Message to show.
     * @param string $type         Notice type: success|error.
     * @param string $redirect_url Optional redirect URL.
     */
    function ufsc_redirect_with_notice( $message, $type = 'success', $redirect_url = '' ) {
        $redirect_url = $redirect_url ?: ( wp_get_referer() ?: home_url() );
        $key          = ( 'error' === $type ) ? 'ufsc_error' : 'ufsc_message';
        $redirect_url = add_query_arg( $key, rawurlencode( $message ), $redirect_url );
        wp_safe_redirect( $redirect_url );
        exit;
    }
}

/**
 * Handle club affiliation form submission.
 *
 * Processes required documents and adds the affiliation product to cart.
 */
function ufsc_club_affiliation_submit() {
    check_admin_referer( 'ufsc_club_affiliation_submit' );

    if ( ! current_user_can( 'read' ) ) {
        ufsc_redirect_with_notice( __( 'Vous devez être connecté', 'ufsc-clubs' ), 'error' );
    }

    $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;

    $uploads = UFSC_Uploads::handle_required_docs( $_FILES );
    if ( is_wp_error( $uploads ) ) {
        ufsc_redirect_with_notice( $uploads->get_error_message(), 'error' );
    }

    $added = false;
    if ( function_exists( 'WC' ) ) {
        function_exists( 'wc_load_cart' ) && wc_load_cart();
        $cart_item_key = WC()->cart->add_to_cart( 4823, 1, 0, array(), array( 'ufsc_club_id' => $club_id ) );
        if ( $cart_item_key ) {
            wc_add_to_cart_message( array( 4823 => 1 ), true );
            $added = true;
        }
    }

    if ( ! $added ) {
        ufsc_redirect_with_notice( __( 'Impossible d\'ajouter le produit au panier.', 'ufsc-clubs' ), 'error' );
    }

    $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url();
    ufsc_redirect_with_notice( __( 'Produit d\'affiliation ajouté au panier.', 'ufsc-clubs' ), 'success', $cart_url );
}

add_action( 'admin_post_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
add_action( 'admin_post_nopriv_ufsc_club_affiliation_submit', 'ufsc_club_affiliation_submit' );
