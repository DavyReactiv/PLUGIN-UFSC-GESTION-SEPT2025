<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 WooCommerce cart integrity guard for UFSC licence renewals.
 *
 * DEV debug showed a renewal reaching ufsc_persist_woocommerce_cart() with a
 * malformed WC cart row: the cart key still existed but the product `data`
 * object was missing. WC_Cart_Totals then fatals on get_tax_class().
 *
 * This guard is deliberately narrow:
 * - only the UFSC bulk-renewal admin-post request gets the in-request repair;
 * - valid cart lines are never replaced or emptied;
 * - a just-added renewal line is snapshotted before Woo totals run, so a later
 *   stale set_quantity()/hook cannot turn it into a quantity-only ghost row;
 * - rows with a product id but no product object are rehydrated with wc_get_product();
 * - an unrecoverable ghost row with neither product nor snapshot is removed only
 *   from the in-memory Woo cart, never from UFSC licence/affiliation tables;
 * - session validation is moved to WooCommerce's supported pre-remove hook
 *   instead of returning `false` from woocommerce_get_cart_item_from_session.
 */

/** True only for the UFSC final renewal request handled through admin-post.php. */
function ufsc_renewal_cart_integrity_is_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( 'ufsc_bulk_renew_licences' !== $action ) {
        return false;
    }

    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
        : '';
    if ( '' === $intent && isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] ) ) {
        $intent = sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) );
    }

    return '' === $intent || in_array( $intent, array( 'add_to_cart', 'submit_for_validation', 'finalize' ), true );
}

/** Keep one valid in-memory copy of a newly-added renewal cart row. */
function ufsc_renewal_cart_integrity_capture_added_item( $cart_item_key ) {
    if ( ! ufsc_renewal_cart_integrity_is_request() || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return;
    }

    $contents = method_exists( WC()->cart, 'get_cart_contents' )
        ? (array) WC()->cart->get_cart_contents()
        : (array) WC()->cart->get_cart();
    if ( empty( $contents[ $cart_item_key ] ) || ! is_array( $contents[ $cart_item_key ] ) ) {
        return;
    }

    $item = $contents[ $cart_item_key ];
    $is_licence = function_exists( 'ufsc_is_licence_cart_item' )
        ? ufsc_is_licence_cart_item( $item )
        : ( ! empty( $item['ufsc_licence_id'] ) || ! empty( $item['ufsc_license_ids'] ) );
    if ( ! $is_licence ) {
        return;
    }

    if ( ! isset( $GLOBALS['ufsc_renewal_cart_integrity_snapshots'] ) || ! is_array( $GLOBALS['ufsc_renewal_cart_integrity_snapshots'] ) ) {
        $GLOBALS['ufsc_renewal_cart_integrity_snapshots'] = array();
    }
    $GLOBALS['ufsc_renewal_cart_integrity_snapshots'][ (string) $cart_item_key ] = $item;
}
add_action( 'woocommerce_add_to_cart', 'ufsc_renewal_cart_integrity_capture_added_item', 1, 1 );

/** Log technical cart repair context without licence personal data. */
function ufsc_renewal_cart_integrity_log( $event, array $context = array(), $level = 'warning' ) {
    if ( function_exists( 'ufsc_wc_log' ) ) {
        ufsc_wc_log( $event, $context, $level );
    }
}

/**
 * Repair malformed Woo rows before WC_Cart_Totals reads `$item['data']`.
 *
 * @param WC_Cart|object $cart WooCommerce cart instance.
 */
function ufsc_renewal_cart_integrity_repair_before_totals( $cart ) {
    if ( ! ufsc_renewal_cart_integrity_is_request() || ! $cart || ! is_object( $cart ) ) {
        return;
    }

    $contents = method_exists( $cart, 'get_cart_contents' )
        ? (array) $cart->get_cart_contents()
        : ( method_exists( $cart, 'get_cart' ) ? (array) $cart->get_cart() : array() );
    if ( ! $contents ) {
        return;
    }

    $snapshots = isset( $GLOBALS['ufsc_renewal_cart_integrity_snapshots'] ) && is_array( $GLOBALS['ufsc_renewal_cart_integrity_snapshots'] )
        ? $GLOBALS['ufsc_renewal_cart_integrity_snapshots']
        : array();
    $changed = false;

    foreach ( $contents as $key => $item ) {
        if ( ! is_array( $item ) ) {
            if ( isset( $snapshots[ $key ] ) && is_array( $snapshots[ $key ] ) ) {
                $contents[ $key ] = $snapshots[ $key ];
                $changed = true;
                ufsc_renewal_cart_integrity_log( 'ufsc_renewal_cart_row_restored', array( 'cart_key_hash' => hash( 'sha256', (string) $key ), 'reason' => 'non_array' ) );
            } else {
                unset( $contents[ $key ] );
                $changed = true;
                ufsc_renewal_cart_integrity_log( 'ufsc_renewal_cart_orphan_removed', array( 'cart_key_hash' => hash( 'sha256', (string) $key ), 'reason' => 'non_array' ), 'error' );
            }
            continue;
        }

        $product = $item['data'] ?? null;
        $valid_product = is_object( $product )
            && ( ! class_exists( 'WC_Product' ) || $product instanceof WC_Product )
            && ( ! method_exists( $product, 'exists' ) || $product->exists() );
        if ( $valid_product ) {
            continue;
        }

        // First preference: restore the exact row captured immediately after add_to_cart.
        if ( isset( $snapshots[ $key ] ) && is_array( $snapshots[ $key ] ) ) {
            $snapshot_product = $snapshots[ $key ]['data'] ?? null;
            if ( is_object( $snapshot_product ) ) {
                $current_quantity = isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1;
                $contents[ $key ] = $snapshots[ $key ];
                $contents[ $key ]['quantity'] = $current_quantity;
                $changed = true;
                ufsc_renewal_cart_integrity_log( 'ufsc_renewal_cart_row_restored', array( 'cart_key_hash' => hash( 'sha256', (string) $key ), 'reason' => 'snapshot' ) );
                continue;
            }
        }

        // Second preference: rehydrate the WC_Product from the existing product identifiers.
        $product_id = absint( $item['variation_id'] ?? 0 ) ?: absint( $item['product_id'] ?? 0 );
        $rehydrated = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
        if ( $rehydrated && is_object( $rehydrated ) && ( ! method_exists( $rehydrated, 'exists' ) || $rehydrated->exists() ) ) {
            $contents[ $key ]['data'] = $rehydrated;
            $changed = true;
            ufsc_renewal_cart_integrity_log( 'ufsc_renewal_cart_product_rehydrated', array( 'cart_key_hash' => hash( 'sha256', (string) $key ), 'product_id' => $product_id ) );
            continue;
        }

        // A quantity-only or otherwise unidentifiable row is not a payable cart item.
        // Drop only this in-memory ghost so valid existing cart lines survive.
        unset( $contents[ $key ] );
        $changed = true;
        ufsc_renewal_cart_integrity_log( 'ufsc_renewal_cart_orphan_removed', array( 'cart_key_hash' => hash( 'sha256', (string) $key ), 'reason' => 'missing_product' ), 'error' );
    }

    if ( $changed && method_exists( $cart, 'set_cart_contents' ) ) {
        $cart->set_cart_contents( $contents );
    }
}
add_action( 'woocommerce_before_calculate_totals', 'ufsc_renewal_cart_integrity_repair_before_totals', 1, 1 );

/**
 * WooCommerce expects woocommerce_get_cart_item_from_session to return an array.
 * Move UFSC invalid-item removal to the supported pre-remove contract.
 */
function ufsc_renewal_cart_integrity_fix_session_hook() {
    remove_filter( 'woocommerce_get_cart_item_from_session', 'ufsc_validate_licence_affiliation_cart_session', 20 );
    add_filter( 'woocommerce_pre_remove_cart_item_from_session', 'ufsc_renewal_cart_integrity_pre_remove_session_item', 20, 4 );
}
add_action( 'init', 'ufsc_renewal_cart_integrity_fix_session_hook', -100 );

/**
 * @param bool       $remove Existing Woo removal decision.
 * @param string     $key Cart item key.
 * @param array      $values Session values.
 * @param WC_Product $product Product object supplied by WooCommerce.
 * @return bool
 */
function ufsc_renewal_cart_integrity_pre_remove_session_item( $remove, $key, $values, $product ) {
    unset( $key );
    if ( $remove || ! is_array( $values ) || ! function_exists( 'ufsc_validate_licence_affiliation_cart_item' ) ) {
        return (bool) $remove;
    }

    $item = array_merge( $values, array( 'data' => $product ) );
    $is_licence = function_exists( 'ufsc_is_licence_cart_item' )
        ? ufsc_is_licence_cart_item( $item )
        : ( ! empty( $item['ufsc_licence_id'] ) || ! empty( $item['ufsc_license_ids'] ) );
    if ( ! $is_licence ) {
        return false;
    }

    return ! ufsc_validate_licence_affiliation_cart_item( $item, 'cart_session_restore' );
}
