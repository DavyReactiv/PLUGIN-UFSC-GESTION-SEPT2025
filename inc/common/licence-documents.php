<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Licence document helpers (certificat PDF).
 */

/**
 * Get licence document data.
 *
 * @param object|array $licence Licence record.
 * @return array{url:string,attachment_id:int,status:string}
 */
function ufsc_get_licence_document_data( $licence ) {
    $licence_id = 0;
    $url        = '';
    $attachment = 0;

    if ( is_array( $licence ) ) {
        $licence_id = isset( $licence['id'] ) ? (int) $licence['id'] : 0;
        $url        = $licence['certificat_url'] ?? ( $licence['attestation_url'] ?? '' );
    } elseif ( is_object( $licence ) ) {
        $licence_id = isset( $licence->id ) ? (int) $licence->id : 0;
        $url        = $licence->certificat_url ?? ( $licence->attestation_url ?? '' );
    }

    if ( $url && is_numeric( $url ) ) {
        $attachment = (int) $url;
        $url        = wp_get_attachment_url( $attachment );
    }

    if ( ! $url && $licence_id ) {
        $attachment = (int) get_option( 'ufsc_licence_document_' . $licence_id );
        if ( $attachment ) {
            $url = wp_get_attachment_url( $attachment );
        }
    }

    return array(
        'url'           => $url ? esc_url_raw( $url ) : '',
        'attachment_id' => (int) $attachment,
        'status'        => $url ? 'available' : 'missing',
    );
}

/**
 * Check if a user can manage a licence document.
 *
 * @param int $licence_id Licence ID.
 * @param int $club_id Club ID.
 * @return bool
 */
function ufsc_can_manage_licence_document( $licence_id, $club_id ) {
    if ( current_user_can( 'manage_options' ) ) {
        return true;
    }

    if ( ! is_user_logged_in() ) {
        return false;
    }

    $user_club_id = function_exists( 'ufsc_get_user_club_id' ) ? ufsc_get_user_club_id( get_current_user_id() ) : 0;
    return $user_club_id && $club_id && (int) $user_club_id === (int) $club_id;
}
