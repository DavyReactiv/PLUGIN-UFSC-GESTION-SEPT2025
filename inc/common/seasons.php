<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compatibility wrapper: season logic now lives in inc/common/season.php.
 *
 * IMPORTANT:
 * - Do NOT re-declare season helpers here (avoid fatal "Cannot redeclare").
 * - Keep this file as a shim for legacy includes/calls.
 */

require_once __DIR__ . '/season.php';

if ( ! function_exists( 'ufsc_get_current_season_label_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_current_season_label().
	 */
	function ufsc_get_current_season_label_legacy() {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_current_season_label' );
		return ufsc_get_current_season_label();
	}
}

/**
 * Legacy fallbacks (only if old code expects these symbols).
 * We do NOT redeclare the real implementations; we proxy to the new ones.
 */

if ( ! function_exists( 'ufsc_get_detected_season_column_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_detected_season_column().
	 */
	function ufsc_get_detected_season_column_legacy( $table ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_detected_season_column' );
		return ufsc_get_detected_season_column( $table );
	}
}

if ( ! function_exists( 'ufsc_get_licence_season_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_licence_season().
	 */
	function ufsc_get_licence_season_legacy( $licence ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_licence_season' );
		return ufsc_get_licence_season( $licence );
	}
}

if ( ! function_exists( 'ufsc_set_licence_season_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_set_licence_season().
	 */
	function ufsc_set_licence_season_legacy( $licence_id, $season ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_set_licence_season' );
		return ufsc_set_licence_season( $licence_id, $season );
	}
}

if ( ! function_exists( 'ufsc_get_affiliation_season_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_affiliation_season().
	 */
	function ufsc_get_affiliation_season_legacy( $club_id, $season = '' ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_affiliation_season' );
		return ufsc_get_affiliation_season( $club_id, $season );
	}
}

if ( ! function_exists( 'ufsc_set_affiliation_season_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_set_affiliation_season().
	 */
	function ufsc_set_affiliation_season_legacy( $club_id, $season ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_set_affiliation_season' );
		return ufsc_set_affiliation_season( $club_id, $season );
	}
}

if ( ! function_exists( 'ufsc_get_season_end_year_from_label_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_season_end_year_from_label().
	 */
	function ufsc_get_season_end_year_from_label_legacy( $season_label ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_season_end_year_from_label' );
		return ufsc_get_season_end_year_from_label( $season_label );
	}
}

if ( ! function_exists( 'ufsc_get_renewed_licence_marker_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_get_renewed_licence_marker().
	 */
	function ufsc_get_renewed_licence_marker_legacy( $source_licence_id, $target_season ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_renewed_licence_marker' );
		return ufsc_get_renewed_licence_marker( $source_licence_id, $target_season );
	}
}

if ( ! function_exists( 'ufsc_mark_renewed_licence_marker_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_mark_renewed_licence_marker().
	 */
	function ufsc_mark_renewed_licence_marker_legacy( $source_licence_id, $target_season, $new_licence_id ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_mark_renewed_licence_marker' );
		return ufsc_mark_renewed_licence_marker( $source_licence_id, $target_season, $new_licence_id );
	}
}

if ( ! function_exists( 'ufsc_is_affiliation_renewed_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_is_affiliation_renewed().
	 */
	function ufsc_is_affiliation_renewed_legacy( $club_id, $target_season ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_is_affiliation_renewed' );
		return ufsc_is_affiliation_renewed( $club_id, $target_season );
	}
}

if ( ! function_exists( 'ufsc_mark_affiliation_renewed_legacy' ) ) {
	/**
	 * @deprecated 1.5.8 Use ufsc_mark_affiliation_renewed().
	 */
	function ufsc_mark_affiliation_renewed_legacy( $club_id, $target_season ) {
		_deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_mark_affiliation_renewed' );
		return ufsc_mark_affiliation_renewed( $club_id, $target_season );
	}
}