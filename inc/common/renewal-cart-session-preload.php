<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 — bootstrap WooCommerce cart early for admin-post licence finalisations.
 *
 * admin-post.php is an admin request, so WooCommerce may not create its front
 * cart automatically. Our historical fallback creates the cart later, during
 * the admin-post handler, which is after `wp_loaded`. In that situation the
 * newly-created WC_Cart_Session misses its normal `wp_loaded` callback and the
 * stored cart is not loaded before a renewal is appended.
 *
 * Bootstrap at wp_loaded priority 1 instead. WC_Cart_Session registers its own
 * session loader at priority 10, so WooCommerce can restore the existing cart
 * through its native path before any UFSC final handler runs.
 *
 * No database row, quota, order or licence status is mutated here.
 */

/** Return the posted finalisation intent, normalized across licence forms. */
function ufsc_cart_preload_posted_intent() {
    foreach ( array( 'ufsc_renew_intent', 'ufsc_renew_intent_fallback', 'ufsc_submit_action', 'ufsc_final_intent' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && ! is_array( $_POST[ $key ] ) ) {
            $intent = sanitize_key( wp_unslash( $_POST[ $key ] ) );
            if ( '' !== $intent ) {
                return $intent;
            }
        }
    }
    return '';
}

/** Limit the preload to POST requests that can really hand a licence to Woo. */
function ufsc_cart_preload_is_final_licence_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';

    if ( ! in_array( $action, array( 'ufsc_bulk_renew_licences', 'ufsc_save_licence', 'ufsc_update_licence', 'ufsc_add_licence' ), true ) ) {
        return false;
    }

    return in_array(
        ufsc_cart_preload_posted_intent(),
        array( 'add_to_cart', 'submit_for_validation', 'finalize' ),
        true
    );
}

/**
 * Create the native Woo cart early enough for WC_Cart_Session to restore the
 * previous session at its normal wp_loaded priority 10.
 */
function ufsc_cart_preload_before_finalisation() {
    if ( ! ufsc_cart_preload_is_final_licence_request() ) {
        return;
    }
    if ( ! function_exists( 'WC' ) || ! WC() ) {
        return;
    }

    // If Woo already restored the session there is nothing to do.
    if ( function_exists( 'did_action' ) && did_action( 'woocommerce_load_cart_from_session' ) ) {
        return;
    }

    // A cart created before this priority already owns a WC_Cart_Session whose
    // priority-10 wp_loaded callback is still due to run in this request.
    if ( WC()->cart ) {
        return;
    }

    if ( ! function_exists( 'wc_load_cart' ) ) {
        return;
    }

    try {
        wc_load_cart();
    } catch ( Throwable $error ) {
        if ( function_exists( 'ufsc_wc_log' ) ) {
            ufsc_wc_log(
                'ufsc_cart_preload_failed',
                array(
                    'error_class'   => get_class( $error ),
                    'error_message' => sanitize_text_field( $error->getMessage() ),
                ),
                'error'
            );
        }
    }
}
add_action( 'wp_loaded', 'ufsc_cart_preload_before_finalisation', 1 );
