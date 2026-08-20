<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Restore the renewal submit intent before the canonical admin-post handler runs.
 *
 * The front-end keeps the normal submit button name/value, and additionally sends
 * a dedicated fallback field because legacy DOM/listener layers can make browsers
 * submit the selected licence IDs without the clicked submitter. The real handler
 * still owns nonce, authentication, club ownership, season and profile validation.
 */
function ufsc_production_normalize_renewal_intent_post() {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Routing normalization only; the admin-post handler verifies the nonce immediately afterwards.
    $method = isset( $_SERVER['REQUEST_METHOD'] )
        ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) )
        : '';
    if ( 'POST' !== $method ) {
        return;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( 'ufsc_bulk_renew_licences' !== $action ) {
        return;
    }

    $raw_intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) )
        : '';
    $fallback = isset( $_POST['ufsc_renew_intent_fallback'] ) && ! is_array( $_POST['ufsc_renew_intent_fallback'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent_fallback'] ) )
        : '';

    $allowed = array( 'cancel', 'save_draft', 'verify', 'add_to_cart', 'submit_for_validation', 'finalize' );
    $resolved = in_array( $raw_intent, $allowed, true )
        ? $raw_intent
        : ( in_array( $fallback, $allowed, true ) ? $fallback : '' );

    if ( in_array( $resolved, array( 'submit_for_validation', 'finalize' ), true ) ) {
        $resolved = 'add_to_cart';
    }

    if ( '' !== $resolved ) {
        $_POST['ufsc_renew_intent'] = $resolved;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $ids = array();
        foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $ids_key ) {
            if ( isset( $_POST[ $ids_key ] ) && is_array( $_POST[ $ids_key ] ) ) {
                $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST[ $ids_key ] ) ) ) ) );
                break;
            }
        }
        error_log(
            '[UFSC Gestion] renewal INTENT ' . wp_json_encode(
                array(
                    'raw'      => $raw_intent,
                    'fallback' => $fallback,
                    'resolved' => $resolved,
                    'club_id'  => isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0,
                    'sources'  => $ids,
                )
            )
        );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing
}
add_action( 'admin_init', 'ufsc_production_normalize_renewal_intent_post', -100 );
