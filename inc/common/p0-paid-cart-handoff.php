<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Paid-licence handoff hardening.
 *
 * Keep the existing finalizer as the single business authority for quota,
 * affiliation, ownership and season checks. This wrapper only prepares the
 * canonical WooCommerce intent expected by ufsc_handle_add_to_cart_secure().
 */
function ufsc_p0_paid_cart_handoff() {
    $_POST['_ufsc_nonce'] = wp_create_nonce( 'ufsc_add_to_cart_action' );
    $_POST['ufsc_action'] = 'new_licence';

    if ( ! function_exists( 'ufsc_p0_handle_finalize_licence' ) ) {
        wp_die( esc_html__( 'Le gestionnaire de finalisation de licence est indisponible.', 'ufsc-clubs' ) );
    }

    ufsc_p0_handle_finalize_licence();
}

remove_action( 'admin_post_ufsc_p0_finalize_licence', 'ufsc_p0_handle_finalize_licence' );
add_action( 'admin_post_ufsc_p0_finalize_licence', 'ufsc_p0_paid_cart_handoff' );
