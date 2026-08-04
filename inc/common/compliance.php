<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Whether a licence role is subject to the honorability workflow.
 *
 * Pratiquant is deliberately the sole built-in exemption. Integrations may
 * alter the decision without duplicating role lists in forms or handlers.
 */
function ufsc_role_requires_honorability( $role ) {
	$normalized = sanitize_title( remove_accents( trim( (string) $role ) ) );
	$required   = '' !== $normalized && 'pratiquant' !== $normalized;

	return (bool) apply_filters( 'ufsc_role_requires_honorability', $required, $normalized, $role );
}

/** Return the canonical annual affiliation presentation used by admin/front. */
function ufsc_get_annual_affiliation_status( $affiliation ) {
	$status = $affiliation && isset( $affiliation->status ) ? sanitize_key( (string) $affiliation->status ) : '';
	$map = array(
		''                    => array( 'key' => 'a_renouveler', 'label' => __( 'À renouveler', 'ufsc-clubs' ) ),
		'pending_payment'     => array( 'key' => 'pending_payment', 'label' => __( 'En attente de paiement', 'ufsc-clubs' ) ),
		'pending_validation'  => array( 'key' => 'pending_validation', 'label' => __( 'En attente de validation', 'ufsc-clubs' ) ),
		'pending'             => array( 'key' => 'pending_validation', 'label' => __( 'En attente de validation', 'ufsc-clubs' ) ),
		'active'              => array( 'key' => 'active', 'label' => __( 'Affilié', 'ufsc-clubs' ) ),
		'validated'           => array( 'key' => 'active', 'label' => __( 'Affilié', 'ufsc-clubs' ) ),
		'rejected'            => array( 'key' => 'rejected', 'label' => __( 'Refusé', 'ufsc-clubs' ) ),
		'suspended'           => array( 'key' => 'suspended', 'label' => __( 'Suspendu', 'ufsc-clubs' ) ),
		'correction_required' => array( 'key' => 'correction_required', 'label' => __( 'À corriger', 'ufsc-clubs' ) ),
	);

	return $map[ $status ] ?? array( 'key' => $status, 'label' => ucfirst( str_replace( '_', ' ', $status ) ) );
}
