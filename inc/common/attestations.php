<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Attestation helpers (attachment + legacy URL fallback).
 */

/**
 * Get affiliation attestation data for a club.
 *
 * @param int        $club_id Club ID.
 * @param object|nil $club    Optional club record.
 * @return array{url:string,attachment_id:int,status:string,can_view:bool}
 */
function ufsc_get_affiliation_attestation_data( $club_id, $club = null ) {
    $club_id = (int) $club_id;

    $can_view = current_user_can( 'manage_options' );
    if ( ! $can_view && function_exists( 'ufsc_is_club_validated' ) ) {
        $can_view = ufsc_is_club_validated( $club_id, $club );
    }

    $attachment_id = 0;
    $url           = '';
    $has_attestation_url = false;

    if ( function_exists( 'ufsc_get_clubs_table' ) ) {
        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();
        $columns     = array();

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $columns = (array) ufsc_table_columns( $clubs_table );
        } else {
            $columns = $wpdb->get_col( "DESCRIBE {$clubs_table}", 0 );
        }

        $has_attestation_url = is_array( $columns ) && in_array( 'attestation_url', $columns, true );
    }

    // 1) Direct club field (legacy/fast path)
    if ( $has_attestation_url && $club && ! empty( $club->attestation_url ) ) {
        $url = esc_url_raw( $club->attestation_url );
    }

    // 2) Legacy club field doc_attestation_affiliation (id or URL)
    if ( ! $url && ! $has_attestation_url && $club && ! empty( $club->doc_attestation_affiliation ) ) {
        if ( is_numeric( $club->doc_attestation_affiliation ) ) {
            $attachment_id = (int) $club->doc_attestation_affiliation;
            $url           = wp_get_attachment_url( $attachment_id );
        } elseif ( is_string( $club->doc_attestation_affiliation ) && filter_var( $club->doc_attestation_affiliation, FILTER_VALIDATE_URL ) ) {
            $url = $club->doc_attestation_affiliation;
        }
    }

    // 3) Options (attachment id or URL)
    $option_keys = array(
        'ufsc_club_doc_attestation_affiliation_' . $club_id,
        'ufsc_club_doc_attestation_ufsc_' . $club_id,
        'ufsc_attestation_' . $club_id,
    );

    foreach ( $option_keys as $key ) {
        $value = get_option( $key );
        if ( empty( $value ) ) {
            continue;
        }
        if ( is_numeric( $value ) ) {
            $attachment_id = (int) $value;
            $url           = wp_get_attachment_url( $attachment_id );
            break;
        }
        if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
            $url = $value;
            break;
        }
    }

    // 4) DB fallback: read attestation_url if column exists
    if ( ! $url && $has_attestation_url && function_exists( 'ufsc_get_clubs_table' ) ) {
        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();
        $db_url      = $wpdb->get_var(
            $wpdb->prepare( "SELECT attestation_url FROM {$clubs_table} WHERE id = %d", $club_id )
        );
        if ( $db_url && is_string( $db_url ) ) {
            $url = $db_url;
        }
    }

    // 5) Last resort: PDF generator helper
    if ( ! $url && class_exists( 'UFSC_PDF_Attestations' ) ) {
        $url = UFSC_PDF_Attestations::get_attestation_for_club( $club_id );
    }

    return array(
        'url'           => $url ?: '',
        'attachment_id' => (int) $attachment_id,
        'status'        => $url ? 'available' : 'pending',
        'can_view'      => (bool) $can_view,
    );
}
