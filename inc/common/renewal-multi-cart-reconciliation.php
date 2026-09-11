<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 — keep multi-line carts compatible with reopened renewal drafts.
 *
 * A previous failed attempt can leave a renewal line for the same historical
 * source in the WooCommerce cart while the club is reopening another current-
 * season draft for that same person. The native renewal guard intentionally
 * prevents duplicate payment by source, but without reconciliation that stale
 * line can make the current draft look "already in cart" even though its own
 * target licence ID is absent.
 *
 * This layer removes only the stale cart line(s) that match the SAME club,
 * season and historical source, and only when the current target licence is not
 * already present. Every unrelated product/licence line remains untouched.
 * No licence/history row is deleted and no cart-wide limit is introduced.
 */

/** Resolve a renewal source ID carried by one cart item. */
function ufsc_renewal_multi_cart_item_source_id( $item ) {
    if ( ! is_array( $item ) ) {
        return 0;
    }
    foreach ( array( 'ufsc_renew_from_licence_id', 'ufsc_source_licence_id', 'ufsc_previous_licence_id' ) as $key ) {
        $value = absint( $item[ $key ] ?? 0 );
        if ( $value > 0 ) {
            return $value;
        }
    }
    return 0;
}

/** True only for a renewal cart line matching this exact club/season/source. */
function ufsc_renewal_multi_cart_item_matches_source( $item, $club_id, $season, $source_id ) {
    if ( ! is_array( $item ) ) {
        return false;
    }

    $action = sanitize_key( (string) ( $item['ufsc_action'] ?? '' ) );
    $type   = sanitize_key( (string) ( $item['ufsc_item_type'] ?? '' ) );
    if ( 'renew_licence' !== $action && 'licence_renewal' !== $type ) {
        return false;
    }

    if ( absint( $item['ufsc_club_id'] ?? 0 ) !== absint( $club_id ) ) {
        return false;
    }

    $item_season = sanitize_text_field( (string) ( $item['ufsc_target_season'] ?? ( $item['ufsc_season'] ?? '' ) ) );
    if ( '' === $item_season || str_replace( '/', '-', $item_season ) !== str_replace( '/', '-', (string) $season ) ) {
        return false;
    }

    return absint( $source_id ) > 0 && ufsc_renewal_multi_cart_item_source_id( $item ) === absint( $source_id );
}

/**
 * Remove only stale same-source renewal cart lines before the draft handoff.
 *
 * The current opened draft remains authoritative for this explicit user action.
 * Unrelated cart lines are never removed or replaced.
 */
function ufsc_renewal_multi_cart_reconcile_before_draft_handoff( $club_id ) {
    if ( ! function_exists( 'ufsc_renewal_draft_cart_is_final_request' ) || ! ufsc_renewal_draft_cart_is_final_request() ) {
        return;
    }
    if ( ! function_exists( 'WC' ) || ! WC() || ! WC()->cart || ! method_exists( WC()->cart, 'get_cart' ) ) {
        return;
    }

    $target_id = isset( $_POST['licence_id'] ) && ! is_array( $_POST['licence_id'] )
        ? absint( wp_unslash( $_POST['licence_id'] ) )
        : 0;
    $club_id = absint( $club_id );
    if ( $target_id < 1 || $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    // If the exact target is already there, nothing must be touched.
    if ( function_exists( 'ufsc_renewal_recovery_cart_contains_target' ) && ufsc_renewal_recovery_cart_contains_target( $target_id ) ) {
        return;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $target = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1", $target_id, $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $target || ! function_exists( 'ufsc_renewal_draft_cart_source_id' ) ) {
        return;
    }

    $source_id = absint( ufsc_renewal_draft_cart_source_id( $target ) );
    if ( $source_id < 1 ) {
        return; // Ordinary licence draft: do not touch its cart flow.
    }

    $season = class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $season = str_replace( '/', '-', sanitize_text_field( $season ) );
    if ( '' === $season ) {
        return;
    }

    $removed = array();
    foreach ( (array) WC()->cart->get_cart() as $key => $item ) {
        if ( ! ufsc_renewal_multi_cart_item_matches_source( $item, $club_id, $season, $source_id ) ) {
            continue;
        }

        $ids = function_exists( 'ufsc_extract_licence_ids_from_cart_item' )
            ? ufsc_extract_licence_ids_from_cart_item( (array) $item )
            : array_filter( array( absint( $item['ufsc_licence_id'] ?? 0 ) ) );
        if ( in_array( $target_id, $ids, true ) ) {
            continue;
        }

        // Remove only this exact stale renewal line. Never empty/rebuild the cart.
        if ( method_exists( WC()->cart, 'remove_cart_item' ) && WC()->cart->remove_cart_item( (string) $key ) ) {
            $removed[] = (string) $key;
        }
    }

    if ( $removed && function_exists( 'ufsc_wc_log' ) ) {
        ufsc_wc_log(
            'ufsc_renewal_stale_same_source_cart_line_removed',
            array(
                'club_id'    => $club_id,
                'source_id'  => $source_id,
                'target_id'  => $target_id,
                'line_count' => count( $removed ),
            ),
            'warning'
        );
    }
}
add_action( 'ufsc_licence_updated', 'ufsc_renewal_multi_cart_reconcile_before_draft_handoff', 15, 1 );
