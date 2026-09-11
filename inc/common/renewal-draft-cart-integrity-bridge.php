<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 bridge for a current-season renewal draft reopened through the unified
 * licence form.
 *
 * The debug-confirmed cart integrity guard historically covered only the bulk
 * renewal endpoint. A reopened renewal draft posts through ufsc_save_licence /
 * ufsc_update_licence, so its newly-created Woo row was not snapshotted or
 * repaired on that route.
 *
 * This bridge is intentionally narrow: only an existing current-season licence
 * carrying renewal lineage is observed, and only during final cart submission.
 * No licence/history row, quota, order, price or unrelated cart line is changed.
 */

/** True only for a final unified-form request targeting a renewal draft. */
function ufsc_renewal_draft_integrity_is_request() {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Observer only; the canonical admin-post handler verifies the nonce.
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( ! in_array( $action, array( 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
        return false;
    }

    $intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';
    $final_intent = isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) )
        : '';
    if ( 'add_to_cart' !== $intent && 'submit_for_validation' !== $final_intent ) {
        return false;
    }

    $licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return false;
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    global $wpdb;
    $table = ufsc_get_licences_table();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $licence_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $row ) {
        return false;
    }

    $source_id = function_exists( 'ufsc_renewal_draft_cart_source_id' )
        ? absint( ufsc_renewal_draft_cart_source_id( $row ) )
        : absint( $row->previous_licence_id ?? ( $row->renewed_from_licence_id ?? 0 ) );
    if ( $source_id < 1 ) {
        return false;
    }

    if ( function_exists( 'ufsc_renewal_draft_cart_is_current_season' ) && ! ufsc_renewal_draft_cart_is_current_season( $row ) ) {
        return false;
    }

    return true;
}

/** Read the exact cart row by key without touching other lines. */
function ufsc_renewal_draft_integrity_cart_row( $cart_item_key ) {
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return null;
    }
    $contents = method_exists( WC()->cart, 'get_cart_contents' )
        ? (array) WC()->cart->get_cart_contents()
        : (array) WC()->cart->get_cart();
    return isset( $contents[ $cart_item_key ] ) && is_array( $contents[ $cart_item_key ] )
        ? $contents[ $cart_item_key ]
        : null;
}

/** Capture the exact native Woo row before any later add-to-cart hook can damage it. */
function ufsc_renewal_draft_integrity_capture_added_item( $cart_item_key ) {
    if ( ! ufsc_renewal_draft_integrity_is_request() ) {
        return;
    }

    $item = ufsc_renewal_draft_integrity_cart_row( $cart_item_key );
    if ( ! is_array( $item ) ) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Observer inside the already-verified request lifecycle.
    $target_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    $ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
        ? ufsc_extract_licence_ids_from_cart_item( $item )
        : array_filter( array( absint( $item['ufsc_licence_id'] ?? 0 ) ) );
    if ( $target_id < 1 || ! in_array( $target_id, $ids, true ) ) {
        return;
    }

    if ( ! isset( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] ) || ! is_array( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] ) ) {
        $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] = array();
    }
    $GLOBALS['ufsc_renewal_draft_integrity_snapshots'][ (string) $cart_item_key ] = $item;
}
add_action( 'woocommerce_add_to_cart', 'ufsc_renewal_draft_integrity_capture_added_item', -999, 1 );

/** Restore one captured row by key when it lost its product or licence metadata. */
function ufsc_renewal_draft_integrity_restore_row( $cart, $cart_item_key ) {
    if ( ! $cart || ! is_object( $cart ) ) {
        return false;
    }
    $snapshots = isset( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] ) && is_array( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] )
        ? $GLOBALS['ufsc_renewal_draft_integrity_snapshots']
        : array();
    if ( empty( $snapshots[ $cart_item_key ] ) || ! is_array( $snapshots[ $cart_item_key ] ) ) {
        return false;
    }

    $contents = method_exists( $cart, 'get_cart_contents' )
        ? (array) $cart->get_cart_contents()
        : ( method_exists( $cart, 'get_cart' ) ? (array) $cart->get_cart() : array() );
    if ( ! array_key_exists( $cart_item_key, $contents ) ) {
        return false;
    }

    $current = is_array( $contents[ $cart_item_key ] ) ? $contents[ $cart_item_key ] : array();
    $snapshot = $snapshots[ $cart_item_key ];
    $product = $current['data'] ?? null;
    $valid_product = is_object( $product ) && ( ! method_exists( $product, 'exists' ) || $product->exists() );
    $current_ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
        ? ufsc_extract_licence_ids_from_cart_item( $current )
        : array_filter( array( absint( $current['ufsc_licence_id'] ?? 0 ) ) );
    $snapshot_ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
        ? ufsc_extract_licence_ids_from_cart_item( $snapshot )
        : array_filter( array( absint( $snapshot['ufsc_licence_id'] ?? 0 ) ) );

    if ( $valid_product && $current_ids === $snapshot_ids ) {
        return false;
    }

    $quantity = isset( $current['quantity'] ) ? max( 1, absint( $current['quantity'] ) ) : 1;
    $contents[ $cart_item_key ] = $snapshot;
    $contents[ $cart_item_key ]['quantity'] = $quantity;
    if ( method_exists( $cart, 'set_cart_contents' ) ) {
        $cart->set_cart_contents( $contents );
    } else {
        return false;
    }

    if ( function_exists( 'ufsc_wc_log' ) ) {
        ufsc_wc_log(
            'ufsc_renewal_draft_cart_row_restored',
            array(
                'cart_key_hash' => hash( 'sha256', (string) $cart_item_key ),
                'licence_ids'   => array_map( 'absint', $snapshot_ids ),
            ),
            'warning'
        );
    }
    return true;
}

/**
 * Repair immediately at the end of add_to_cart(), before the caller verifies
 * target presence. This closes the gap that the before-totals repair cannot.
 */
function ufsc_renewal_draft_integrity_repair_after_add( $cart_item_key ) {
    if ( ! ufsc_renewal_draft_integrity_is_request() || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return;
    }
    ufsc_renewal_draft_integrity_restore_row( WC()->cart, (string) $cart_item_key );
}
add_action( 'woocommerce_add_to_cart', 'ufsc_renewal_draft_integrity_repair_after_add', 999999, 1 );

/** Second safety net before WooCommerce calculates totals/persists the session. */
function ufsc_renewal_draft_integrity_repair_before_totals( $cart ) {
    if ( ! ufsc_renewal_draft_integrity_is_request() ) {
        return;
    }
    $snapshots = isset( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] ) && is_array( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] )
        ? array_keys( $GLOBALS['ufsc_renewal_draft_integrity_snapshots'] )
        : array();
    foreach ( $snapshots as $key ) {
        ufsc_renewal_draft_integrity_restore_row( $cart, (string) $key );
    }
}
add_action( 'woocommerce_before_calculate_totals', 'ufsc_renewal_draft_integrity_repair_before_totals', 2, 1 );
