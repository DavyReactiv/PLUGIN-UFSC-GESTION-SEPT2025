<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 paid licence cart postcondition.
 *
 * The canonical finalization service decides included vs payable. The Unified
 * Handler owns the native WooCommerce insertion. This guard runs only when that
 * handler is already redirecting a final licence request to the WooCommerce cart.
 * It verifies that the payable licence really exists in the native cart after
 * all licence status hooks have run, then persists the session once more.
 *
 * No quota is allocated here and no included licence can be turned into a paid
 * line. On a confirmed cart handoff failure, the unpaid licence is returned to
 * draft only when no WooCommerce order is linked, preventing a stuck en_attente
 * dossier without losing the form data.
 */

/** @return bool */
function ufsc_paid_cart_is_final_licence_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( ! in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
        return false;
    }

    $intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';
    $final_intent = isset( $_POST['ufsc_final_intent'] ) && ! is_array( $_POST['ufsc_final_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_final_intent'] ) )
        : '';

    return 'add_to_cart' === $intent || 'submit_for_validation' === $final_intent;
}

/** Capture a newly-created licence ID for the redirect postcondition. */
function ufsc_paid_cart_capture_created_licence( $licence_id, $club_id = 0 ) {
    if ( ! ufsc_paid_cart_is_final_licence_request() ) {
        return;
    }

    $GLOBALS['ufsc_paid_cart_created_licence_id'] = absint( $licence_id );
    $GLOBALS['ufsc_paid_cart_created_club_id']    = absint( $club_id );
}
add_action( 'ufsc_licence_created', 'ufsc_paid_cart_capture_created_licence', 50, 2 );

/** Resolve the licence targeted by this final request. */
function ufsc_paid_cart_resolve_licence_id() {
    $posted = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    if ( $posted > 0 ) {
        return $posted;
    }

    return absint( $GLOBALS['ufsc_paid_cart_created_licence_id'] ?? 0 );
}

/** True only for the site's canonical WooCommerce cart destination. */
function ufsc_paid_cart_is_cart_redirect( $location ) {
    if ( ! function_exists( 'wc_get_cart_url' ) ) {
        return false;
    }

    $cart_url = (string) wc_get_cart_url();
    $location = (string) $location;
    if ( '' === $cart_url || '' === $location ) {
        return false;
    }

    $cart_parts = wp_parse_url( $cart_url );
    $dest_parts = wp_parse_url( $location );
    if ( ! is_array( $cart_parts ) || ! is_array( $dest_parts ) ) {
        return untrailingslashit( $cart_url ) === untrailingslashit( $location );
    }

    $cart_host = strtolower( (string) ( $cart_parts['host'] ?? '' ) );
    $dest_host = strtolower( (string) ( $dest_parts['host'] ?? '' ) );
    $cart_path = untrailingslashit( (string) ( $cart_parts['path'] ?? '/' ) );
    $dest_path = untrailingslashit( (string) ( $dest_parts['path'] ?? '/' ) );

    return $cart_host === $dest_host && $cart_path === $dest_path;
}

/** Check the live native cart, never a database counter. */
function ufsc_paid_cart_contains_licence( $licence_id ) {
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return false;
    }

    foreach ( (array) WC()->cart->get_cart() as $item ) {
        $ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
            ? ufsc_extract_licence_ids_from_cart_item( (array) $item )
            : array_filter(
                array_merge(
                    array( absint( $item['ufsc_licence_id'] ?? 0 ) ),
                    array_map( 'absint', (array) ( $item['ufsc_license_ids'] ?? array() ) ),
                    array_map( 'absint', (array) ( $item['ufsc_licence_ids'] ?? array() ) )
                )
            );
        if ( in_array( $licence_id, $ids, true ) ) {
            return true;
        }
    }

    return false;
}

/** Log without exposing licence personal data. */
function ufsc_paid_cart_log( $event, $licence_id, $club_id, $level = 'info', $extra = array() ) {
    if ( function_exists( 'ufsc_wc_log' ) ) {
        ufsc_wc_log(
            $event,
            array_merge(
                array(
                    'licence_id' => absint( $licence_id ),
                    'club_id'    => absint( $club_id ),
                ),
                (array) $extra
            ),
            $level
        );
    }
}

/** Return an unpaid failed handoff to draft only when no order is linked. */
function ufsc_paid_cart_revert_failed_handoff_to_draft( $licence_id, $club_id ) {
    $licence_id = absint( $licence_id );
    $club_id    = absint( $club_id );
    if ( $licence_id < 1 || $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return false;
    }

    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) ) {
        $linked = ufsc_is_licence_linked_to_order( $licence_id );
        if ( false !== $linked ) {
            // true = order exists; null = uncertainty. Both fail closed.
            return false;
        }
    } else {
        return false;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    if ( class_exists( 'UFSC_Licence_Status' ) ) {
        $updated = UFSC_Licence_Status::update_status_columns(
            $table,
            array( 'id' => $licence_id, 'club_id' => $club_id ),
            'brouillon',
            array( '%d', '%d' )
        );
    } else {
        $updated = $wpdb->update(
            $table,
            array( 'statut' => 'brouillon' ),
            array( 'id' => $licence_id, 'club_id' => $club_id ),
            array( '%s' ),
            array( '%d', '%d' )
        );
    }

    return false !== $updated;
}

/** Build a safe failure redirect that keeps the licence editable. */
function ufsc_paid_cart_failure_redirect( $message, $licence_id, $club_id ) {
    ufsc_paid_cart_revert_failed_handoff_to_draft( $licence_id, $club_id );
    ufsc_paid_cart_log( 'ufsc_paid_cart_postcondition_failed', $licence_id, $club_id, 'error' );

    if ( function_exists( 'wc_add_notice' ) ) {
        wc_add_notice( $message, 'error' );
    }

    $fallback = wp_get_referer() ?: home_url( '/' );
    return add_query_arg(
        array(
            'ufsc_error' => rawurlencode( $message ),
            'licence_id' => absint( $licence_id ),
        ),
        $fallback
    );
}

/**
 * Enforce: payable finalisation -> exactly one native cart line -> persisted session.
 *
 * This is intentionally a redirect postcondition. It does not compete with the
 * canonical finalization service or the normal handler; it only checks their
 * declared successful cart destination after all status hooks have completed.
 */
function ufsc_paid_cart_enforce_redirect_postcondition( $location, $status = 302 ) {
    unset( $status );
    static $running = false;

    if ( $running || ! ufsc_paid_cart_is_final_licence_request() || ! ufsc_paid_cart_is_cart_redirect( $location ) ) {
        return $location;
    }

    $licence_id = ufsc_paid_cart_resolve_licence_id();
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return $location;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $licence_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $row ) {
        return $location;
    }

    $club_id = absint( $row->club_id ?? ( $GLOBALS['ufsc_paid_cart_created_club_id'] ?? 0 ) );
    if ( $club_id < 1 ) {
        return $location;
    }

    // Included licences must never be forced into WooCommerce.
    if ( ! empty( $row->is_included ) ) {
        return $location;
    }

    // If a real order already owns this licence, never create a second payment path.
    if ( function_exists( 'ufsc_is_licence_linked_to_order' ) ) {
        $linked = ufsc_is_licence_linked_to_order( $licence_id );
        if ( true === $linked ) {
            return $location;
        }
    }

    $running = true;

    $ready = function_exists( 'ufsc_ensure_woocommerce_cart' )
        ? ufsc_ensure_woocommerce_cart()
        : new WP_Error( 'ufsc_cart_unavailable', __( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ) );
    if ( is_wp_error( $ready ) ) {
        $running = false;
        return ufsc_paid_cart_failure_redirect( $ready->get_error_message(), $licence_id, $club_id );
    }

    if ( ! ufsc_paid_cart_contains_licence( $licence_id ) ) {
        $resolution = function_exists( 'ufsc_get_licence_product_resolution' )
            ? ufsc_get_licence_product_resolution()
            : array();
        $product_id = absint( $resolution['configured_id'] ?? ( function_exists( 'ufsc_get_licence_product_id' ) ? ufsc_get_licence_product_id() : 0 ) );
        if ( $product_id < 1 || ( isset( $resolution['valid'] ) && empty( $resolution['valid'] ) ) ) {
            $message = function_exists( 'ufsc_get_licence_product_message' )
                ? ufsc_get_licence_product_message( $resolution )
                : __( 'Le produit Licence UFSC est indisponible.', 'ufsc-clubs' );
            $running = false;
            return ufsc_paid_cart_failure_redirect( $message, $licence_id, $club_id );
        }

        $add_result = function_exists( 'ufsc_add_licence_ids_to_cart_idempotent' )
            ? ufsc_add_licence_ids_to_cart_idempotent(
                $product_id,
                $club_id,
                array( $licence_id ),
                array(
                    'ufsc_operation_type' => 'new_licence',
                    'ufsc_request_type'   => 'new',
                    'ufsc_season'         => function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $row ) : '',
                    'ufsc_target_season'  => function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $row ) : '',
                )
            )
            : new WP_Error( 'ufsc_cart_helper_missing', __( 'Le service panier des licences est indisponible.', 'ufsc-clubs' ) );

        if ( is_wp_error( $add_result ) || ! ufsc_paid_cart_contains_licence( $licence_id ) ) {
            $message = is_wp_error( $add_result )
                ? $add_result->get_error_message()
                : __( 'La licence a été conservée mais son ajout au panier n’a pas pu être confirmé. Réessayez depuis la fiche licence.', 'ufsc-clubs' );
            $running = false;
            return ufsc_paid_cart_failure_redirect( $message, $licence_id, $club_id );
        }
    }

    // Persist again after the handler has moved the dossier to en_attente. This
    // closes the admin-post/session gap observed on DEV without altering quota.
    $persisted = function_exists( 'ufsc_persist_woocommerce_cart' )
        ? ufsc_persist_woocommerce_cart()
        : new WP_Error( 'ufsc_cart_persist_missing', __( 'La session du panier est indisponible.', 'ufsc-clubs' ) );
    if ( is_wp_error( $persisted ) || ! ufsc_paid_cart_contains_licence( $licence_id ) ) {
        $message = is_wp_error( $persisted )
            ? $persisted->get_error_message()
            : __( 'La licence n’a pas pu être conservée dans le panier. Réessayez depuis la fiche licence.', 'ufsc-clubs' );
        $running = false;
        return ufsc_paid_cart_failure_redirect( $message, $licence_id, $club_id );
    }

    ufsc_paid_cart_log( 'ufsc_paid_cart_postcondition_ok', $licence_id, $club_id, 'info' );
    $running = false;

    return function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : $location;
}
add_filter( 'wp_redirect', 'ufsc_paid_cart_enforce_redirect_postcondition', 999, 2 );
