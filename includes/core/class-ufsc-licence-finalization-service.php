<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Canonical server-side decision for finalising an already persisted licence.
 *
 * Controllers remain responsible for nonce/capability checks and redirects.
 * This service owns only the business transition:
 * current-season row -> affiliation gate -> quota reservation -> included state
 * or paid hand-off decision. It never mutates an archived source licence and it
 * never creates a WooCommerce cart line itself.
 */
final class UFSC_Licence_Finalization_Service {

	/**
	 * Finalise a persisted licence up to the included/paid decision boundary.
	 *
	 * @param int    $licence_id Licence ID.
	 * @param int    $club_id Expected club ID (0 = resolve from row).
	 * @param string $season Target season (empty = current canonical season).
	 * @param string $context Diagnostic context.
	 * @return array|WP_Error
	 */
	public static function finalize( $licence_id, $club_id = 0, $season = '', $context = 'licence' ) {
		global $wpdb;

		$licence_id = absint( $licence_id );
		$club_id    = absint( $club_id );
		$season     = $season ? str_replace( '/', '-', sanitize_text_field( (string) $season ) ) : self::current_season();
		$context    = sanitize_key( (string) $context );

		if ( $licence_id < 1 || '' === $season || ! function_exists( 'ufsc_get_licences_table' ) ) {
			return new WP_Error( 'ufsc_finalization_context_invalid', __( 'La finalisation de cette licence est indisponible.', 'ufsc-clubs' ) );
		}

		$table = ufsc_get_licences_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
		if ( ! $row ) {
			return new WP_Error( 'ufsc_finalization_not_found', __( 'Licence introuvable.', 'ufsc-clubs' ) );
		}

		$row_club_id = absint( $row->club_id ?? 0 );
		if ( $row_club_id < 1 || ( $club_id > 0 && $club_id !== $row_club_id ) ) {
			return new WP_Error( 'ufsc_finalization_club_mismatch', __( 'Cette licence n’appartient pas au club demandé.', 'ufsc-clubs' ) );
		}
		$club_id = $row_club_id;

		$row_season = function_exists( 'ufsc_get_licence_season_label' )
			? str_replace( '/', '-', trim( (string) ufsc_get_licence_season_label( $row ) ) )
			: str_replace( '/', '-', trim( (string) ( $row->season ?? $row->saison ?? '' ) ) );
		if ( '' !== $row_season && $row_season !== $season ) {
			return new WP_Error( 'ufsc_finalization_historical_source', __( 'Une licence archivée ne peut pas être modifiée. Utilisez le renouvellement pour créer le dossier de la saison courante.', 'ufsc-clubs' ) );
		}

		$gate = function_exists( 'ufsc_club_can_manage_licences_for_season' )
			? ufsc_club_can_manage_licences_for_season( $club_id, $season )
			: array( 'allowed' => false, 'message' => __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );
		if ( empty( $gate['allowed'] ) ) {
			return new WP_Error( 'ufsc_finalization_affiliation_inactive', (string) ( $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) ) );
		}

		self::trace(
			'licence_finalization_start',
			array(
				'licence_id' => $licence_id,
				'club_id'    => $club_id,
				'season'     => $season,
				'context'    => $context,
				'status_before' => function_exists( 'ufsc_get_licence_status_from_record' ) ? ufsc_get_licence_status_from_record( $row ) : (string) ( $row->statut ?? $row->status ?? '' ),
			)
		);

		if ( ! function_exists( 'ufsc_allocate_pack_credit' ) ) {
			return new WP_Error( 'ufsc_finalization_quota_unavailable', __( 'Le quota d’affiliation est indisponible.', 'ufsc-clubs' ) );
		}

		$role       = sanitize_key( (string) ( $row->role ?? '' ) );
		$allocation = ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role );
		if ( is_wp_error( $allocation ) ) {
			self::trace( 'licence_finalization_quota_error', array( 'licence_id' => $licence_id, 'club_id' => $club_id, 'season' => $season, 'error_code' => $allocation->get_error_code() ) );
			return $allocation;
		}

		if ( empty( $allocation['included'] ) ) {
			self::trace( 'licence_finalization_paid', array( 'licence_id' => $licence_id, 'club_id' => $club_id, 'season' => $season ) );
			return array(
				'licence_id' => $licence_id,
				'club_id'    => $club_id,
				'season'     => $season,
				'included'   => false,
				'payable'    => true,
				'bucket'     => 'payante',
				'status'     => function_exists( 'ufsc_get_licence_status_from_record' ) ? ufsc_get_licence_status_from_record( $row ) : (string) ( $row->statut ?? $row->status ?? '' ),
			);
		}

		if ( empty( $allocation['reserved'] ) ) {
			return new WP_Error( 'ufsc_finalization_quota_unconfirmed', __( 'La licence est éligible au quota, mais la réservation n’a pas été confirmée.', 'ufsc-clubs' ) );
		}

		$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "DESCRIBE `{$table}`" );
		$status_result = class_exists( 'UFSC_Licence_Status' )
			? UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id, 'club_id' => $club_id ), 'en_attente', array( '%d', '%d' ) )
			: $wpdb->update( $table, array( 'statut' => 'en_attente' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
		if ( false === $status_result ) {
			return new WP_Error( 'ufsc_finalization_status_failed', __( 'La licence a été conservée, mais son passage en attente de validation a échoué.', 'ufsc-clubs' ) );
		}

		if ( in_array( 'payment_status', $columns, true ) ) {
			$payment_result = $wpdb->update( $table, array( 'payment_status' => 'included' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
			if ( false === $payment_result ) {
				return new WP_Error( 'ufsc_finalization_payment_marker_failed', __( 'La licence est en attente, mais le marqueur de quota inclus n’a pas pu être enregistré.', 'ufsc-clubs' ) );
			}
		}

		if ( in_array( 'submitted_at', $columns, true ) ) {
			$current_submitted = (string) $wpdb->get_var( $wpdb->prepare( "SELECT submitted_at FROM `{$table}` WHERE id = %d", $licence_id ) );
			if ( '' === trim( $current_submitted ) || '0000-00-00 00:00:00' === $current_submitted ) {
				$wpdb->update( $table, array( 'submitted_at' => current_time( 'mysql' ) ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
			}
		}

		$confirmed = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d AND club_id = %d", $licence_id, $club_id ) );
		$confirmed_status = $confirmed && function_exists( 'ufsc_get_licence_status_from_record' )
			? ufsc_get_licence_status_from_record( $confirmed )
			: ( $confirmed ? sanitize_key( (string) ( $confirmed->statut ?? $confirmed->status ?? '' ) ) : '' );
		if ( 'en_attente' !== $confirmed_status ) {
			return new WP_Error( 'ufsc_finalization_status_unconfirmed', __( 'Le changement de statut n’a pas pu être confirmé. Aucun succès n’est annoncé.', 'ufsc-clubs' ) );
		}

		if ( function_exists( 'ufsc_journey_record_submission' ) ) {
			ufsc_journey_record_submission( $licence_id, $club_id, $season, $context );
		}

		self::trace( 'licence_finalization_included', array( 'licence_id' => $licence_id, 'club_id' => $club_id, 'season' => $season, 'status_after' => $confirmed_status, 'included' => true ) );

		return array(
			'licence_id' => $licence_id,
			'club_id'    => $club_id,
			'season'     => $season,
			'included'   => true,
			'payable'    => false,
			'bucket'     => sanitize_key( (string) ( $allocation['bucket'] ?? 'libre' ) ),
			'status'     => 'en_attente',
		);
	}

	private static function current_season() {
		return class_exists( 'UFSC_Season_Service' )
			? str_replace( '/', '-', (string) UFSC_Season_Service::get_current_season() )
			: ( function_exists( 'ufsc_get_current_season' ) ? str_replace( '/', '-', (string) ufsc_get_current_season() ) : '' );
	}

	private static function trace( $event, array $context ) {
		if ( class_exists( 'UFSC_Debug_Trace' ) ) {
			UFSC_Debug_Trace::record( $event, $context );
		}
	}
}
