<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 compatibility for a renewal target reopened from "Mes licences".
 *
 * A failed renewal may leave its current-season target as an editable draft.
 * Reopening that target uses the unified licence form instead of the bulk
 * renewal endpoint. This bridge detects only those annual renewal targets and
 * hands payable ones to the native WooCommerce renewal path already used by
 * the dedicated renewal flow.
 *
 * Historical/source rows are never modified here. Included licences never get
 * a paid cart line. Existing cart lines are preserved.
 */

/** True only for a final unified-form licence submission. */
function ufsc_renewal_draft_cart_is_final_request() {
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

    return 'add_to_cart' === $intent || 'submit_for_validation' === $final_intent;
}

/** Resolve the historical source ID carried by a current-season renewal target. */
function ufsc_renewal_draft_cart_source_id( $row ) {
    $row = is_object( $row ) ? $row : (object) $row;
    foreach ( array( 'previous_licence_id', 'renewed_from_licence_id' ) as $field ) {
        $value = absint( $row->{$field} ?? 0 );
        if ( $value > 0 ) {
            return $value;
        }
    }
    return 0;
}

/** Confirm that the target belongs to the current canonical season. */
function ufsc_renewal_draft_cart_is_current_season( $row ) {
    $row = is_object( $row ) ? $row : (object) $row;
    $current = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $stored = function_exists( 'ufsc_get_licence_season_label' )
        ? (string) ufsc_get_licence_season_label( $row )
        : (string) ( $row->season ?? ( $row->saison ?? '' ) );

    return '' !== $current && str_replace( '/', '-', $current ) === str_replace( '/', '-', $stored );
}

/** Locate the target cart line and report whether its Woo product object is healthy. */
function ufsc_renewal_draft_cart_find_target_line( $licence_id ) {
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'WC' ) || ! WC() || ! WC()->cart ) {
        return null;
    }

    $contents = method_exists( WC()->cart, 'get_cart_contents' )
        ? (array) WC()->cart->get_cart_contents()
        : (array) WC()->cart->get_cart();

    foreach ( $contents as $key => $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
            ? ufsc_extract_licence_ids_from_cart_item( $item )
            : array_filter( array( absint( $item['ufsc_licence_id'] ?? 0 ) ) );
        if ( ! in_array( $licence_id, $ids, true ) ) {
            continue;
        }

        $product = $item['data'] ?? null;
        $valid = is_object( $product ) && ( ! method_exists( $product, 'exists' ) || $product->exists() );
        return array( 'key' => (string) $key, 'valid' => $valid );
    }

    return null;
}

/** Prevent the historical generic licence handoff from running after this bridge. */
function ufsc_renewal_draft_cart_stop_generic_handoff() {
    $_POST['ufsc_submit_action'] = 'continue';
    $_POST['ufsc_final_intent']  = '';
}

/** Keep a failed unpaid target editable and expose a recoverable message. */
function ufsc_renewal_draft_cart_fail( $message, $licence_id, $club_id ) {
    if ( function_exists( 'ufsc_renewal_recovery_keep_editable' ) ) {
        ufsc_renewal_recovery_keep_editable( $licence_id, $club_id );
    }
    if ( function_exists( 'wc_add_notice' ) ) {
        wc_add_notice( sanitize_text_field( (string) $message ), 'error' );
    }
    $GLOBALS['ufsc_renewal_draft_cart_failure'] = sanitize_text_field( (string) $message );
    ufsc_renewal_draft_cart_stop_generic_handoff();
}

/**
 * Handoff a reopened renewal draft after the canonical priority-0 finalizer has
 * already decided included versus payable.
 */
function ufsc_renewal_draft_cart_on_updated( $club_id ) {
    static $running = false;
    if ( $running || ! ufsc_renewal_draft_cart_is_final_request() ) {
        return;
    }

    $licence_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    $club_id = absint( $club_id );
    if ( $licence_id < 1 || $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    $user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    if ( ! current_user_can( 'manage_options' ) && $user_club !== $club_id ) {
        return;
    }

    $running = true;
    global $wpdb;
    $table  = ufsc_get_licences_table();
    $target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1", $licence_id, $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $target || ! ufsc_renewal_draft_cart_is_current_season( $target ) ) {
        $running = false;
        return;
    }

    $source_id = ufsc_renewal_draft_cart_source_id( $target );
    if ( $source_id < 1 ) {
        $running = false;
        return; // Ordinary current-season licence: leave the established flow untouched.
    }

    // Included renewals are owned by the canonical finalization service and
    // must never create a WooCommerce line.
    $payment_status = sanitize_key( (string) ( $target->payment_status ?? '' ) );
    if ( ! empty( $target->is_included ) || in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true ) ) {
        $running = false;
        return;
    }

    $source = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1", $source_id, $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $source ) {
        ufsc_renewal_draft_cart_fail( __( 'La licence historique liée à ce renouvellement est introuvable. Le brouillon a été conservé.', 'ufsc-clubs' ), $licence_id, $club_id );
        $running = false;
        return;
    }

    // Repair only a stale line for this exact target; never empty the cart.
    $line = ufsc_renewal_draft_cart_find_target_line( $licence_id );
    if ( is_array( $line ) && empty( $line['valid'] ) ) {
        if ( ! method_exists( WC()->cart, 'remove_cart_item' ) || ! WC()->cart->remove_cart_item( $line['key'] ) ) {
            ufsc_renewal_draft_cart_fail( __( 'Une ancienne ligne panier invalide empêche ce renouvellement. Le brouillon a été conservé.', 'ufsc-clubs' ), $licence_id, $club_id );
            $running = false;
            return;
        }
        $line = null;
    }

    if ( is_array( $line ) && ! empty( $line['valid'] ) ) {
        $result = array( 'existing' => true, 'target_id' => $licence_id );
    } elseif ( function_exists( 'ufsc_renewal_native_handoff_add_target' ) ) {
        $season = class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
        $result = ufsc_renewal_native_handoff_add_target( $source, $target, $club_id, str_replace( '/', '-', $season ) );
    } else {
        $result = new WP_Error( 'ufsc_renewal_draft_native_handoff_missing', __( 'Le service panier du renouvellement est indisponible.', 'ufsc-clubs' ) );
    }

    if ( is_wp_error( $result ) ) {
        ufsc_renewal_draft_cart_fail( $result->get_error_message(), $licence_id, $club_id );
        $running = false;
        return;
    }

    $persisted = function_exists( 'ufsc_persist_woocommerce_cart' ) ? ufsc_persist_woocommerce_cart() : true;
    $confirmed = function_exists( 'ufsc_renewal_recovery_cart_contains_target' )
        ? ufsc_renewal_recovery_cart_contains_target( $licence_id )
        : ( null !== ufsc_renewal_draft_cart_find_target_line( $licence_id ) );
    if ( is_wp_error( $persisted ) || ! $confirmed ) {
        $message = is_wp_error( $persisted )
            ? $persisted->get_error_message()
            : __( 'La licence renouvelée n’a pas pu être confirmée dans le panier. Le brouillon a été conservé.', 'ufsc-clubs' );
        ufsc_renewal_draft_cart_fail( $message, $licence_id, $club_id );
        $running = false;
        return;
    }

    if ( class_exists( 'UFSC_Licence_Status' ) ) {
        UFSC_Licence_Status::update_status_columns(
            $table,
            array( 'id' => $licence_id, 'club_id' => $club_id ),
            'en_attente',
            array( '%d', '%d' )
        );
    }

    $GLOBALS['ufsc_renewal_draft_cart_success'] = $licence_id;
    ufsc_renewal_draft_cart_stop_generic_handoff();
    if ( function_exists( 'wc_add_notice' ) ) {
        wc_add_notice( __( 'Licence renouvelée ajoutée au panier. Vous pouvez maintenant finaliser la commande.', 'ufsc-clubs' ), 'success' );
    }
    $running = false;
}
add_action( 'ufsc_licence_updated', 'ufsc_renewal_draft_cart_on_updated', 20, 1 );

/** Send only a confirmed native handoff to the Woo cart. */
function ufsc_renewal_draft_cart_redirect( $location, $status = 302 ) {
    unset( $status );
    if ( empty( $GLOBALS['ufsc_renewal_draft_cart_success'] ) || ! function_exists( 'wc_get_cart_url' ) ) {
        return $location;
    }
    return wc_get_cart_url();
}
add_filter( 'wp_redirect', 'ufsc_renewal_draft_cart_redirect', 998, 2 );
