<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Whether a licence role is subject to the honorability workflow.
 *
 * Only explicitly listed leadership and coaching roles are built in. Integrations
 * may extend the filtered list without duplicating role rules in callers.
 */
function ufsc_role_requires_honorability( $role ) {
	$normalized = sanitize_title( remove_accents( trim( preg_replace( '/\s+/', ' ', (string) $role ) ) ) );
	$required_roles = array(
		'president', 'secretaire', 'tresorier', 'membre-du-bureau', 'bureau', 'dirigeant',
		'encadrant', 'entraineur', 'coach', 'educateur', 'responsable-technique',
	);
	$required_roles = (array) apply_filters( 'ufsc_honorability_required_roles', $required_roles );
	$required = in_array( $normalized, array_map( 'sanitize_title', $required_roles ), true );
	return (bool) apply_filters( 'ufsc_role_requires_honorability', $required, $normalized, $role );
}

/** Return the canonical annual affiliation presentation used by admin/front. */
function ufsc_get_annual_affiliation_status( $affiliation ) {
	$raw_status = $affiliation ? ( $affiliation->status ?? $affiliation->statut ?? '' ) : '';
	$status = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::normalize_status( $raw_status ) : sanitize_key( (string) $raw_status );
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

function ufsc_honorability_document_option_key( $licence_id, $season ) {
	return 'ufsc_honorability_attestation_' . absint( $licence_id ) . '_' . sanitize_key( (string) $season );
}

function ufsc_get_honorability_document( $licence_id, $season = '' ) {
	$season = $season ?: ( class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '' );
	$record = get_option( ufsc_honorability_document_option_key( $licence_id, $season ), array() );
	$record = is_array( $record ) ? $record : array();
	return array_merge( array(
		'type' => 'honorability_attestation', 'licence_id' => absint( $licence_id ), 'club_id' => 0,
		'season' => $season, 'role' => '', 'attachment_id' => 0, 'status' => 'missing',
		'uploaded_at' => '', 'uploaded_by' => 0, 'decided_at' => '', 'decided_by' => 0,
		'reason' => '', 'history' => array(),
	), $record );
}

/** Persist a WordPress attachment and preserve every replaced version. */
function ufsc_save_honorability_document( $licence_id, $club_id, $season, $role, $attachment_id, $user_id = 0 ) {
	$licence_id = absint( $licence_id ); $club_id = absint( $club_id ); $attachment_id = absint( $attachment_id );
	if ( ! $licence_id || ! $club_id || ! $attachment_id || ! get_post( $attachment_id ) ) {
		return new WP_Error( 'invalid_honorability_document', __( 'Document d’honorabilité invalide.', 'ufsc-clubs' ) );
	}
	$current = ufsc_get_honorability_document( $licence_id, $season );
	$history = is_array( $current['history'] ) ? $current['history'] : array();
	if ( ! empty( $current['attachment_id'] ) ) {
		$history[] = array_intersect_key( $current, array_flip( array( 'attachment_id', 'status', 'uploaded_at', 'uploaded_by', 'decided_at', 'decided_by', 'reason' ) ) );
	}
	$record = array(
		'type' => 'honorability_attestation', 'licence_id' => $licence_id, 'club_id' => $club_id,
		'season' => sanitize_text_field( $season ), 'role' => sanitize_text_field( $role ),
		'attachment_id' => $attachment_id, 'status' => 'pending', 'uploaded_at' => current_time( 'mysql' ),
		'uploaded_by' => absint( $user_id ?: get_current_user_id() ), 'decided_at' => '', 'decided_by' => 0,
		'reason' => '', 'history' => $history,
	);
	update_option( ufsc_honorability_document_option_key( $licence_id, $season ), $record, false );
	return $record;
}

function ufsc_decide_honorability_document( $licence_id, $season, $status, $reason = '', $admin_id = 0 ) {
	$allowed = array( 'validated', 'rejected', 'correction_required', 'expired' );
	$status = sanitize_key( $status ); $reason = trim( sanitize_textarea_field( $reason ) );
	$record = ufsc_get_honorability_document( $licence_id, $season );
	if ( ! in_array( $status, $allowed, true ) || empty( $record['attachment_id'] ) ) {
		return new WP_Error( 'invalid_honorability_decision', __( 'Décision documentaire invalide.', 'ufsc-clubs' ) );
	}
	if ( in_array( $status, array( 'rejected', 'correction_required' ), true ) && '' === $reason ) {
		return new WP_Error( 'honorability_reason_required', __( 'Un motif est obligatoire pour refuser ou demander une correction.', 'ufsc-clubs' ) );
	}
	$record['status'] = $status; $record['reason'] = $reason; $record['decided_at'] = current_time( 'mysql' );
	$record['decided_by'] = absint( $admin_id ?: get_current_user_id() );
	update_option( ufsc_honorability_document_option_key( $licence_id, $season ), $record, false );
	return $record;
}

function ufsc_is_honorability_document_complete( $licence_id, $season ) {
	$record = ufsc_get_honorability_document( $licence_id, $season );
	return ! empty( $record['attachment_id'] ) && 'validated' === $record['status'];
}

/** Central final-validation gate; checkout intentionally does not call this. */
function ufsc_can_validate_licence( $licence_id, &$reasons = array() ) {
	global $wpdb;
	$reasons = array(); $licence_id = absint( $licence_id );
	$table = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : '';
	$row = $table ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) ) : null;
	if ( ! $row ) { $reasons[] = __( 'Licence introuvable.', 'ufsc-clubs' ); return false; }
	$role = $row->role ?? ( $row->fonction ?? 'pratiquant' );
	$season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence_id ) : '';
	if ( ufsc_role_requires_honorability( $role ) && ! ufsc_is_honorability_document_complete( $licence_id, $season ) ) {
		$reasons[] = __( 'Validation impossible : l’attestation d’honorabilité obligatoire est absente ou non validée.', 'ufsc-clubs' );
	}
	$reasons = (array) apply_filters( 'ufsc_can_validate_licence_reasons', $reasons, $row, $season );
	return empty( $reasons );
}

function ufsc_get_honorability_document_kpis( $licences, $season ) {
	$stats = array( 'required' => 0, 'validated' => 0, 'pending' => 0, 'rejected' => 0, 'correction_required' => 0, 'missing' => 0, 'complete' => 0, 'incomplete' => 0 );
	foreach ( (array) $licences as $licence ) {
		$licence_season = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $licence ) : ( is_object( $licence ) ? ( $licence->season ?? $licence->saison ?? '' ) : ( $licence['season'] ?? $licence['saison'] ?? '' ) );
		$licence_season = str_replace( '/', '-', trim( (string) $licence_season ) );
		if ( $licence_season && $season && $licence_season !== $season ) { continue; }
		$role = is_object( $licence ) ? ( $licence->role ?? 'pratiquant' ) : ( $licence['role'] ?? 'pratiquant' );
		$id = absint( is_object( $licence ) ? ( $licence->id ?? 0 ) : ( $licence['id'] ?? 0 ) );
		if ( ! ufsc_role_requires_honorability( $role ) ) { continue; }
		$stats['required']++; $record = ufsc_get_honorability_document( $id, $season );
		$key = isset( $stats[ $record['status'] ] ) ? $record['status'] : 'missing'; $stats[ $key ]++;
		if ( 'validated' === $record['status'] ) { $stats['complete']++; } else { $stats['incomplete']++; }
	}
	return $stats;
}
