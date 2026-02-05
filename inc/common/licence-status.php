<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Licence status normalization helpers.
 */

/**
 * Get raw status value from a licence record.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_status_raw( $licence ) {
    if ( is_array( $licence ) ) {
        return (string) ( $licence['statut'] ?? ( $licence['status'] ?? '' ) );
    }

    if ( is_object( $licence ) ) {
        return (string) ( $licence->statut ?? ( $licence->status ?? '' ) );
    }

    return '';
}

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
        // Canonical
        'brouillon'   => 'brouillon',
        'non_payee'   => 'non_payee',
        'non_payée'   => 'non_payee',
        'en_attente'  => 'en_attente',
        'valide'      => 'valide',
        'refuse'      => 'refuse',

        // Legacy mappings
        'draft'       => 'brouillon',
        'pending'     => 'en_attente',
        'pending_payment' => 'en_attente',
        'attente'     => 'en_attente',
        'a_regler'    => 'en_attente',
        'paid'        => 'en_attente',
        'payee'       => 'en_attente',
        'payée'       => 'en_attente',

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

        'refusé'      => 'refuse',
        'refusee'     => 'refuse',
        'refusée'     => 'refuse',
        'rejected'    => 'refuse',
        'denied'      => 'refuse',
    );

    return $map[ $status ] ?? $status;
}

/**
 * Normalize a raw status value.
 *
 * @param string $raw Raw status.
 * @return string
 */
function ufsc_get_licence_status_norm( $raw ) {
    return ufsc_normalize_licence_status( $raw );
}

/**
 * Get raw status values that map to a normalized status.
 *
 * @param string $normalized Normalized status.
 * @return array
 */
function ufsc_get_licence_status_raw_values_for_norm( $normalized ) {
    $normalized = ufsc_get_licence_status_norm( $normalized );

    $map = array(
        'brouillon'  => array( 'brouillon', 'draft' ),
        'non_payee'  => array( 'non_payee', 'non_payée' ),
        'en_attente' => array( 'en_attente', 'attente', 'pending', 'pending_payment', 'a_regler', 'paid', 'payee', 'payée' ),
        'valide'     => array( 'valide', 'valid', 'validé', 'validé', 'validee', 'validée', 'validated', 'approved', 'active', 'actif', 'applied' ),
        'refuse'     => array( 'refuse', 'refusé', 'refusee', 'refusée', 'rejected', 'denied' ),
    );

    return $map[ $normalized ] ?? array( $normalized );
}

/**
 * Get the French label for a licence status.
 *
 * @param string $status Raw or normalized status.
 * @return string
 */
function ufsc_get_licence_status_label_fr( $status ) {
    $normalized = ufsc_get_licence_status_norm( $status );
    $labels     = array(
        'brouillon'  => __( 'Brouillon', 'ufsc-clubs' ),
        'non_payee'  => __( 'Non payée', 'ufsc-clubs' ),
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
    $raw = ufsc_get_licence_status_raw( $licence );
    return ufsc_get_licence_status_norm( $raw );
}

/**
 * Determine if a licence can be edited.
 *
 * @param string $status Raw status.
 * @return bool
 */
function ufsc_is_editable_licence_status( $status ) {
    $normalized = ufsc_get_licence_status_norm( $status );
    return in_array( $normalized, array( 'brouillon', 'non_payee', 'en_attente' ), true );
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
