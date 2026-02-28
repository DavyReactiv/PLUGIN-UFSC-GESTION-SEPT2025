<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC season helpers (01/08 -> 31/07)
 * + storage helpers (season column detection + fallback options)
 * + renewal idempotence markers
 * + renewal copy whitelist
 *
 * NOTE: All functions are wrapped with function_exists() to avoid redeclare issues
 * if this file is included twice for any reason.
 */

if ( ! function_exists( 'ufsc_get_season_for_date' ) ) {
	function ufsc_get_season_for_date( $ts ) {
		$ts    = absint( $ts );
		$month = (int) wp_date( 'n', $ts );
		$year  = (int) wp_date( 'Y', $ts );

		$start_year = ( $month >= 8 ) ? $year : ( $year - 1 );
		return sprintf( '%d-%d', $start_year, $start_year + 1 );
	}
}

if ( ! function_exists( 'ufsc_get_current_season' ) ) {
	function ufsc_get_current_season() {
		$stored = get_option( 'ufsc_current_season', '' );
		$stored = is_string( $stored ) ? sanitize_text_field( $stored ) : '';
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $stored, $matches ) && ( (int) $matches[2] ) === ( (int) $matches[1] + 1 ) ) {
			return $stored;
		}

		return ufsc_get_season_for_date( current_time( 'timestamp' ) );
	}
}

/**
 * Backward/UX helper (label = season string "YYYY-YYYY").
 */
if ( ! function_exists( 'ufsc_get_current_season_label' ) ) {
	function ufsc_get_current_season_label() {
		return ufsc_get_current_season();
	}
}

if ( ! function_exists( 'ufsc_get_next_season' ) ) {
	function ufsc_get_next_season() {
		$stored = get_option( 'ufsc_next_season', '' );
		$stored = is_string( $stored ) ? sanitize_text_field( $stored ) : '';
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $stored, $matches ) && ( (int) $matches[2] ) === ( (int) $matches[1] + 1 ) ) {
			return $stored;
		}

		$current = ufsc_get_current_season();
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $current, $m ) ) {
			return sprintf( '%d-%d', (int) $m[1] + 1, (int) $m[2] + 1 );
		}

		$y = (int) wp_date( 'Y', current_time( 'timestamp' ) );
		return sprintf( '%d-%d', $y, $y + 1 );
	}
}

if ( ! function_exists( 'ufsc_get_renewal_window_day_month' ) ) {
	function ufsc_get_renewal_window_day_month() {
		$settings = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
		$day      = isset( $settings['renewal_window_day'] ) ? absint( $settings['renewal_window_day'] ) : 30;
		$month    = isset( $settings['renewal_window_month'] ) ? absint( $settings['renewal_window_month'] ) : 7;

		if ( $day < 1 || $day > 31 ) { $day = 30; }
		if ( $month < 1 || $month > 12 ) { $month = 7; }

		return array( $day, $month );
	}
}

if ( ! function_exists( 'ufsc_get_renewal_window_start_ts' ) ) {
	function ufsc_get_renewal_window_start_ts() {
		$stored_ts = absint( get_option( 'ufsc_renewal_window_start_ts', 0 ) );
		if ( $stored_ts > 0 ) {
			return $stored_ts;
		}

		$current = ufsc_get_current_season();
		$end     = 0;

		if ( preg_match( '/^(\d{4})-(\d{4})$/', $current, $m ) ) {
			$end = (int) $m[2];
		}
		if ( $end <= 0 ) {
			$end = (int) wp_date( 'Y', current_time( 'timestamp' ) );
		}

		list( $day, $month ) = ufsc_get_renewal_window_day_month();
		return (int) strtotime( sprintf( '%04d-%02d-%02d 00:00:00', $end, $month, $day ) );
	}
}

if ( ! function_exists( 'ufsc_is_renewal_window_open' ) ) {
	function ufsc_is_renewal_window_open() {
		return current_time( 'timestamp' ) >= ufsc_get_renewal_window_start_ts();
	}
}

if ( ! function_exists( 'ufsc_get_season_bounds' ) ) {
	function ufsc_get_season_bounds( $season ) {
		$season = sanitize_text_field( (string) $season );
		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $m ) ) {
			return array( 0, 0 );
		}

		$start = (int) strtotime( sprintf( '%04d-08-01 00:00:00', (int) $m[1] ) );
		$end   = (int) strtotime( sprintf( '%04d-07-31 23:59:59', (int) $m[2] ) );

		return array( $start, $end );
	}
}

if ( ! function_exists( 'ufsc_get_season_end_year_from_label' ) ) {
	function ufsc_get_season_end_year_from_label( $season_label ) {
		$season_label = sanitize_text_field( (string) $season_label );
		if ( preg_match( '/^(\d{4})-(\d{4})$/', $season_label, $matches ) ) {
			return (int) $matches[2];
		}

		return 0;
	}
}

if ( ! function_exists( 'ufsc_get_detected_season_column' ) ) {
	function ufsc_get_detected_season_column( $table ) {
		static $cache = array();

		$table = (string) $table;
		if ( '' === $table ) {
			return '';
		}

		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}

		$columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
		foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $col ) {
			if ( in_array( $col, $columns, true ) ) {
				$cache[ $table ] = $col;
				return $col;
			}
		}

		$cache[ $table ] = '';
		return '';
	}
}

/**
 * Store options with autoload=no (safe fallback storage for season/idempotence markers).
 */
if ( ! function_exists( 'ufsc_set_option_noautoload' ) ) {
	function ufsc_set_option_noautoload( $key, $value ) {
		global $wpdb;

		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			return;
		}

		if ( false === get_option( $key, false ) ) {
			add_option( $key, $value, '', 'no' );
			return;
		}

		// update_option third param historically "autoload" bool; using false prevents autoload in most versions.
		update_option( $key, $value, false );

		// Ensure autoload=no at DB level to be extra safe.
		if ( isset( $wpdb->options ) ) {
			$wpdb->update(
				$wpdb->options,
				array( 'autoload' => 'no' ),
				array( 'option_name' => $key ),
				array( '%s' ),
				array( '%s' )
			);
		}
	}
}

if ( ! function_exists( 'ufsc_get_option' ) ) {
	function ufsc_get_option( $key, $default = '' ) {
		return get_option( sanitize_key( (string) $key ), $default );
	}
}

/**
 * Licence season helpers (read/write) with DB-column detection + fallback option.
 */
if ( ! function_exists( 'ufsc_get_licence_season' ) ) {
	function ufsc_get_licence_season( $licence ) {
		global $wpdb;

		$licence_id = 0;
		if ( is_numeric( $licence ) ) {
			$licence_id = absint( $licence );
			if ( $licence_id > 0 && function_exists( 'ufsc_get_licences_table' ) ) {
				$table   = ufsc_get_licences_table();
				$licence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
			}
		} else {
			$licence_id = is_object( $licence ) ? absint( $licence->id ?? 0 ) : absint( $licence['id'] ?? 0 );
		}

		$fields = array( 'paid_season', 'season', 'saison', 'season_end_year' );
		$value  = '';
		$field  = '';

		foreach ( $fields as $field ) {
			if ( is_array( $licence ) && isset( $licence[ $field ] ) ) {
				$value = $licence[ $field ];
			} elseif ( is_object( $licence ) && isset( $licence->{$field} ) ) {
				$value = $licence->{$field};
			}

			if ( '' !== $value && null !== $value ) {
				break;
			}
		}

		if ( '' === $value || null === $value ) {
			if ( $licence_id > 0 ) {
				$option_value = ufsc_get_option( 'ufsc_licence_season_' . $licence_id, '' );
				if ( is_string( $option_value ) && '' !== $option_value ) {
					return sanitize_text_field( $option_value );
				}
			}
			return null;
		}

		if ( 'season_end_year' === $field ) {
			$end_year = absint( $value );
			if ( $end_year > 0 ) {
				return sprintf( '%d-%d', $end_year - 1, $end_year );
			}
		}

		return sanitize_text_field( (string) $value );
	}
}

if ( ! function_exists( 'ufsc_get_licence_season_label' ) ) {
	function ufsc_get_licence_season_label( $licence ) {
		$season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : null;
		if ( is_string( $season ) && '' !== trim( $season ) ) {
			return sanitize_text_field( $season );
		}

		$date_fields = array( 'paid_date', 'date_creation', 'date_inscription', 'date_achat' );
		foreach ( $date_fields as $field ) {
			$date_value = '';
			if ( is_array( $licence ) && ! empty( $licence[ $field ] ) ) {
				$date_value = (string) $licence[ $field ];
			} elseif ( is_object( $licence ) && ! empty( $licence->{$field} ) ) {
				$date_value = (string) $licence->{$field};
			}

			$ts = $date_value ? strtotime( $date_value ) : 0;
			if ( $ts > 0 ) {
				return ufsc_get_season_for_date( $ts );
			}
		}

		return ufsc_get_current_season();
	}
}

if ( ! function_exists( 'ufsc_set_licence_season' ) ) {
	function ufsc_set_licence_season( $licence_id, $season ) {
		global $wpdb;

		$licence_id = absint( $licence_id );
		$season     = sanitize_text_field( (string) $season );

		if ( $licence_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
			return;
		}

		$table       = ufsc_get_licences_table();
		$season_col  = ufsc_get_detected_season_column( $table );
		$update_data = array();
		$formats     = array();

		if ( 'season_end_year' === $season_col ) {
			$update_data['season_end_year'] = (int) ufsc_get_season_end_year_from_label( $season );
			$formats[]                      = '%d';
		} elseif ( '' !== $season_col ) {
			$update_data[ $season_col ] = $season;
			$formats[]                  = '%s';
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( $table, $update_data, array( 'id' => $licence_id ), $formats, array( '%d' ) );
		}

		// Fallback marker (autoload=no).
		ufsc_set_option_noautoload( 'ufsc_licence_season_' . $licence_id, $season );
	}
}

/**
 * Affiliation season helpers (club-level) with DB-column detection + fallback option.
 */
if ( ! function_exists( 'ufsc_get_affiliation_season' ) ) {
	function ufsc_get_affiliation_season( $club_id, $season = '' ) {
		global $wpdb;

		$club_id = absint( $club_id );
		$season  = sanitize_text_field( (string) $season );

		if ( $club_id <= 0 || ! function_exists( 'ufsc_get_clubs_table' ) ) {
			return null;
		}

		$table      = ufsc_get_clubs_table();
		$season_col = ufsc_get_detected_season_column( $table );

		if ( '' !== $season_col ) {
			$raw = $wpdb->get_var( $wpdb->prepare( "SELECT `{$season_col}` FROM `{$table}` WHERE id = %d", $club_id ) );

			if ( 'season_end_year' === $season_col ) {
				$end = absint( $raw );
				if ( $end > 0 ) {
					return sprintf( '%d-%d', $end - 1, $end );
				}
			} elseif ( is_string( $raw ) && '' !== $raw ) {
				return sanitize_text_field( $raw );
			}
		}

		// Option fallback: if season specified, check it directly.
		if ( '' !== $season ) {
			$option_value = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id . '_' . $season, '' );
			return ( is_string( $option_value ) && '' !== $option_value ) ? sanitize_text_field( $option_value ) : null;
		}

		// Try current and next season as candidates.
		foreach ( array( ufsc_get_current_season(), ufsc_get_next_season() ) as $candidate ) {
			$option_value = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id . '_' . $candidate, '' );
			if ( is_string( $option_value ) && '' !== $option_value ) {
				return sanitize_text_field( $option_value );
			}
		}

		// Legacy fallback.
		$legacy = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id, '' );
		if ( is_string( $legacy ) && '' !== $legacy ) {
			return sanitize_text_field( $legacy );
		}

		$club_status = $wpdb->get_var( $wpdb->prepare( "SELECT `statut` FROM `{$table}` WHERE id = %d", $club_id ) );
		$club_status = strtolower( trim( (string) $club_status ) );
		if ( in_array( $club_status, array( 'actif', 'active', 'valide' ), true ) ) {
			return ufsc_get_current_season();
		}

		return null;
	}
}

if ( ! function_exists( 'ufsc_set_affiliation_season' ) ) {
	function ufsc_set_affiliation_season( $club_id, $season ) {
		global $wpdb;

		$club_id = absint( $club_id );
		$season  = sanitize_text_field( (string) $season );

		if ( $club_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! function_exists( 'ufsc_get_clubs_table' ) ) {
			return;
		}

		$table       = ufsc_get_clubs_table();
		$season_col  = ufsc_get_detected_season_column( $table );
		$update_data = array();
		$formats     = array();

		if ( 'season_end_year' === $season_col ) {
			$update_data['season_end_year'] = (int) ufsc_get_season_end_year_from_label( $season );
			$formats[]                      = '%d';
		} elseif ( '' !== $season_col ) {
			$update_data[ $season_col ] = $season;
			$formats[]                  = '%s';
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( $table, $update_data, array( 'id' => $club_id ), $formats, array( '%d' ) );
		}

		ufsc_set_option_noautoload( 'ufsc_affiliation_season_' . $club_id . '_' . $season, $season );
	}
}

/**
 * Renewal idempotence markers (anti-double renew).
 */
if ( ! function_exists( 'ufsc_get_renewed_licence_marker' ) ) {
	function ufsc_get_renewed_licence_marker( $source_licence_id, $target_season ) {
		$key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
		return absint( ufsc_get_option( $key, 0 ) );
	}
}

if ( ! function_exists( 'ufsc_mark_renewed_licence_marker' ) ) {
	function ufsc_mark_renewed_licence_marker( $source_licence_id, $target_season, $new_licence_id ) {
		$key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
		ufsc_set_option_noautoload( $key, absint( $new_licence_id ) );
	}
}

if ( ! function_exists( 'ufsc_is_affiliation_renewed' ) ) {
	function ufsc_is_affiliation_renewed( $club_id, $target_season ) {
		$key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
		return (bool) ufsc_get_option( $key, 0 );
	}
}

if ( ! function_exists( 'ufsc_mark_affiliation_renewed' ) ) {
	function ufsc_mark_affiliation_renewed( $club_id, $target_season ) {
		$key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
		ufsc_set_option_noautoload( $key, 1 );
	}
}

/**
 * Whitelist of fields allowed to be copied when renewing a licence.
 */
if ( ! function_exists( 'ufsc_get_renewal_copy_fields' ) ) {
	function ufsc_get_renewal_copy_fields() {
		return array(
			'nom',
			'nom_licence',
			'prenom',
			'email',
			'adresse',
			'code_postal',
			'ville',
			'tel_fixe',
			'tel_mobile',
			'date_naissance',
			'sexe',
			'nationalite',
			'competition',
			'surclassement',
			'piece_identite',
			'photo_identite',
		);
	}
}

if ( ! function_exists( 'ufsc_backfill_licences_season' ) ) {
	function ufsc_backfill_licences_season( $limit = 200 ) {
		global $wpdb;

		if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
			return 0;
		}

		$table = ufsc_get_licences_table();
		$limit = max( 1, absint( $limit ) );
		$season_col = ufsc_get_detected_season_column( $table );
		if ( '' !== $season_col ) {
			if ( 'season_end_year' === $season_col ) {
				$sql = "SELECT * FROM `{$table}` WHERE (`season_end_year` IS NULL OR `season_end_year` = 0) ORDER BY id ASC LIMIT %d";
			} else {
				$sql = "SELECT * FROM `{$table}` WHERE (`{$season_col}` IS NULL OR `{$season_col}` = '') ORDER BY id ASC LIMIT %d";
			}
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $limit ) );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT %d", $limit ) );
		}
		if ( empty( $rows ) ) {
			return 0;
		}

		$updated = 0;
		foreach ( $rows as $row ) {
			$licence_id = absint( $row->id ?? 0 );
			if ( $licence_id <= 0 ) {
				continue;
			}

			$existing = ufsc_get_licence_season( $row );
			if ( is_string( $existing ) && '' !== trim( $existing ) ) {
				continue;
			}

			$season = ufsc_get_licence_season_label( $row );
			if ( is_string( $season ) && '' !== trim( $season ) ) {
				ufsc_set_licence_season( $licence_id, $season );
				$updated++;
			}
		}

		return $updated;
	}
}

if ( ! function_exists( 'ufsc_maybe_backfill_licences_season' ) ) {
	function ufsc_maybe_backfill_licences_season() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
		}

		ufsc_backfill_licences_season( 200 );
	}
	add_action( 'admin_init', 'ufsc_maybe_backfill_licences_season' );
}
