<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Season-aware affiliation attestation helper.
 */

/**
 * Get affiliation attestation data for a club.
 *
 * @param int        $club_id Club ID.
 * @param object|nil $club    Optional club record kept for API compatibility.
 * @return array{url:string,attachment_id:int,status:string,can_view:bool,season:string}
 */
function ufsc_get_affiliation_attestation_data( $club_id, $club = null ) {
	unset( $club );
    $club_id = (int) $club_id;
	$current_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );

    $can_view = current_user_can( 'manage_options' );
	if ( ! $can_view && function_exists( 'ufsc_is_club_affiliated_for_season' ) ) {
		$can_view = ufsc_is_club_affiliated_for_season( $club_id, $current_season );
    }

	$url = '';

	// Current attestations are seasonal. Permanent club fields/options are
	// intentionally not promoted into a new season.
	if ( class_exists( 'UFSC_PDF_Attestations' ) && '' !== $current_season ) {
		$url = UFSC_PDF_Attestations::get_attestation_for_club( $club_id, 'affiliation', $current_season );
	}

	return array(
		'url'           => $url ?: '',
		'attachment_id' => 0,
		'status'        => $url ? 'available' : ( $can_view ? 'required' : 'pending_validation' ),
		'can_view'      => (bool) $can_view,
		'season'        => $current_season,
	);

}

/** Return seasonal archives plus labelled legacy references for migration UI. */
function ufsc_get_affiliation_attestation_archives( $club_id ) {
	$club_id = absint( $club_id );
	$archives = class_exists( 'UFSC_PDF_Attestations' ) ? UFSC_PDF_Attestations::get_attestations_for_club( $club_id, 'affiliation' ) : array();
	foreach ( array( 'ufsc_club_doc_attestation_affiliation_', 'ufsc_club_doc_attestation_ufsc_', 'ufsc_attestation_' ) as $prefix ) {
		$value = get_option( $prefix . $club_id );
		$url = is_numeric( $value ) ? wp_get_attachment_url( absint( $value ) ) : ( is_string( $value ) ? esc_url_raw( $value ) : '' );
		if ( $url ) {
			$archives[] = (object) array( 'id' => 0, 'saison' => '', 'status' => 'legacy_unassigned', 'created_at' => '', 'download_url' => $url );
		}
	}
	return $archives;
}
