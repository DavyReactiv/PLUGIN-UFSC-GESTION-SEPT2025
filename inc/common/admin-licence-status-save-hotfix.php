<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production hotfix — isolate canonical admin licence edits from front-only
 * finalisation/cart observers.
 *
 * Scope:
 * - only admin-post action `ufsc_sql_save_licence` for an existing licence;
 * - does not write to SQL itself;
 * - does not alter quota, payment, cart or historical rows;
 * - leaves the canonical UFSC_SQL_Admin save handler and validation trace active.
 */

/**
 * Return true only for the canonical existing-licence admin save request.
 *
 * @return bool
 */
function ufsc_admin_licence_status_hotfix_is_save_request() {
    if ( ! is_admin() || 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] )
        ? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
        : '';
    if ( 'ufsc_sql_save_licence' !== $action ) {
        return false;
    }

    $licence_id = isset( $_POST['id'] ) && ! is_array( $_POST['id'] )
        ? absint( wp_unslash( $_POST['id'] ) )
        : 0;

    return $licence_id > 0;
}

/**
 * An admin edit/validation must never carry a public finalisation intent.
 * Removing these transient request flags prevents front finalisers from treating
 * an admin status edit as a submit/add-to-cart operation. No stored field is
 * changed by this function.
 */
function ufsc_admin_licence_status_hotfix_clear_front_intents() {
    if ( ! ufsc_admin_licence_status_hotfix_is_save_request() ) {
        return;
    }

    foreach ( array( 'ufsc_submit_action', 'ufsc_final_intent', 'ufsc_request_type', 'ufsc_operation_type' ) as $key ) {
        unset( $_POST[ $key ], $_REQUEST[ $key ] );
    }
}
add_action( 'admin_init', 'ufsc_admin_licence_status_hotfix_clear_front_intents', -80 );

/**
 * Remove only observers whose responsibility is public finalisation, quota/cart
 * handoff or renewal reconciliation. Cache invalidation, canonical admin save
 * and validation chronology remain registered.
 */
function ufsc_admin_licence_status_hotfix_detach_front_observers() {
    if ( ! ufsc_admin_licence_status_hotfix_is_save_request() ) {
        return;
    }

    if ( class_exists( 'UFSC_Licence_Finalization_Runtime' ) ) {
        remove_action(
            'ufsc_licence_updated',
            array( 'UFSC_Licence_Finalization_Runtime', 'finalize_updated_licence' ),
            0
        );
    }

    $callbacks = array(
        array( 'ufsc_structural_finalize_updated_request', 1 ),
        array( 'ufsc_portal_hotfix_restore_updated_intent', 1 ),
        array( 'ufsc_renewal_multi_cart_reconcile_before_draft_handoff', 15 ),
        array( 'ufsc_renewal_draft_cart_on_updated', 20 ),
        array( 'ufsc_journey_capture_pending_submission', 50 ),
    );

    foreach ( $callbacks as $callback ) {
        if ( function_exists( $callback[0] ) ) {
            remove_action( 'ufsc_licence_updated', $callback[0], $callback[1] );
        }
    }
}
add_action( 'admin_init', 'ufsc_admin_licence_status_hotfix_detach_front_observers', 999 );

/**
 * Log a fatal occurring during the exact admin licence save request. The log
 * contains only technical identifiers/status, never the member's personal data.
 */
function ufsc_admin_licence_status_hotfix_register_fatal_diagnostic() {
    if ( ! ufsc_admin_licence_status_hotfix_is_save_request() ) {
        return;
    }

    $licence_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    $status     = isset( $_POST['statut'] ) && ! is_array( $_POST['statut'] )
        ? sanitize_key( wp_unslash( $_POST['statut'] ) )
        : '';

    register_shutdown_function(
        static function() use ( $licence_id, $status ) {
            $error = error_get_last();
            if ( ! is_array( $error ) || ! in_array( (int) ( $error['type'] ?? 0 ), array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true ) ) {
                return;
            }

            $message = sanitize_text_field( (string) ( $error['message'] ?? 'fatal error' ) );
            $file    = basename( (string) ( $error['file'] ?? '' ) );
            $line    = absint( $error['line'] ?? 0 );
            error_log(
                sprintf(
                    '[UFSC Gestion] Fatal admin licence save: licence_id=%d status=%s file=%s line=%d message=%s',
                    $licence_id,
                    $status,
                    $file,
                    $line,
                    $message
                )
            );
        }
    );
}
add_action( 'admin_init', 'ufsc_admin_licence_status_hotfix_register_fatal_diagnostic', -70 );
