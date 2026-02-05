<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Licence status normalization helpers.
 */

/**
 * Normalize a licence status to a consistent internal value.
 *
 * @param string $status Raw status.
 * @return string Normalized status.
 */
function ufsc_normalize_licence_status( $status ) {
    $status = strtolower( trim( (string) $status ) );
    if ( '' === $status ) {
        return '';
    }

    $map = array(
        'valide'      => 'valide',
        'valid'       => 'valide',
        'validé'      => 'valide',
        'validé'     => 'valide',
        'validee'     => 'valide',
        'validée'     => 'valide',
        'validated'   => 'valide',
        'approved'    => 'valide',
        'active'      => 'valide',
        'actif'       => 'valide',
        'applied'     => 'valide',
        'payee'       => 'valide',
        'payée'       => 'valide',
        'paid'        => 'valide',

        'en_attente'  => 'en_attente',
        'attente'     => 'en_attente',
        'pending'     => 'en_attente',
        'pending_payment' => 'en_attente',
        'a_regler'    => 'en_attente',
        'non_payee'   => 'en_attente',
        'non_payée'   => 'en_attente',
        'brouillon'   => 'en_attente',

        'refuse'      => 'refuse',
        'refusé'      => 'refuse',
        'refusee'     => 'refuse',
        'refusée'     => 'refuse',
        'rejected'    => 'refuse',
        'denied'      => 'refuse',
    );

    return $map[ $status ] ?? $status;
}

/**
 * Get the French label for a licence status.
 *
 * @param string $status Raw or normalized status.
 * @return string
 */
function ufsc_get_licence_status_label_fr( $status ) {
    $normalized = ufsc_normalize_licence_status( $status );
    $labels     = array(
        'valide'     => __( 'Validé', 'ufsc-clubs' ),
        'en_attente' => __( 'En attente', 'ufsc-clubs' ),
        'refuse'     => __( 'Refusé', 'ufsc-clubs' ),
    );

    return $labels[ $normalized ] ?? ( $status !== '' ? $status : __( 'En attente', 'ufsc-clubs' ) );
}

/**
 * Get a normalized status for a licence record.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_status_from_record( $licence ) {
    if ( is_array( $licence ) ) {
        $raw = $licence['statut'] ?? ( $licence['status'] ?? '' );
    } else {
        $raw = $licence->statut ?? ( $licence->status ?? '' );
    }

    return ufsc_normalize_licence_status( $raw );
}

/**
 * Determine if a licence can be edited.
 *
 * @param string $status Raw status.
 * @return bool
 */
function ufsc_is_editable_licence_status( $status ) {
    $normalized = ufsc_normalize_licence_status( $status );
    return in_array( $normalized, array( 'en_attente' ), true );
}

/**
 * Check if a licence payment can be retried based on latest Woo order status.
 *
 * @param int $licence_id Licence ID.
 * @return bool
 */
function ufsc_can_retry_licence_payment( $licence_id ) {
    if ( ! function_exists( 'ufsc_get_latest_licence_order_status' ) ) {
        return false;
    }

    $status = ufsc_get_latest_licence_order_status( $licence_id );
    return in_array( $status, array( 'failed', 'cancelled' ), true );
}
