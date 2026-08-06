<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Read-only helpers for season archives.
 *
 * Schema ownership intentionally remains in UFSC_DB_Migrations so this manager
 * does not create or rename licence columns used by UFSC Gestion or by the
 * separate Licence & Compétition plugin.
 */
class UFSC_Season_Archive_Manager {
	const ADMIN_STATUSES = array( 'a_renouveler', 'pending_payment', 'pending_validation', 'correction_required', 'active', 'rejected', 'suspended' );

    /**
     * Return the annual affiliations table name.
     *
     * @return string
     */
    public static function get_affiliations_table() {
        global $wpdb;

        if ( class_exists( 'UFSC_Storage_Resolver' ) ) {
            return UFSC_Storage_Resolver::get_annual_affiliations_table();
        }

        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'get_affiliation_seasons_table_name' ) ) {
            return UFSC_DB_Migrations::get_affiliation_seasons_table_name();
        }

        // Safe bootstrap fallback; the migration class remains the schema owner.
        return $wpdb->prefix . 'ufsc_affiliations_seasons';
    }

    /**
     * Ensure archive storage exists by delegating to the migration owner.
     *
     * @return void
     */
    public static function maybe_migrate() {
        if ( class_exists( 'UFSC_DB_Migrations' ) && method_exists( 'UFSC_DB_Migrations', 'ensure_season_archive_tables' ) ) {
            UFSC_DB_Migrations::ensure_season_archive_tables();
        }
    }

    /**
     * Licence lineage columns are owned by UFSC_DB_Migrations.
     *
     * @return string[] Existing lineage columns for compatibility reads.
     */
    public static function get_existing_licence_lineage_columns() {
        if ( ! function_exists( 'ufsc_get_licences_table' ) ) {
            return array();
        }

        $table   = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        return array_values( array_intersect( array( 'previous_licence_id', 'renewed_from_licence_id' ), $columns ) );
    }

    /** Normalize legacy season representations to the canonical YYYY-YYYY label. */
    public static function normalize_season( $value, $column = 'season' ) {
        $value = trim( str_replace( '/', '-', (string) $value ) );
        if ( preg_match( '/^(\d{4})-(\d{4})$/', $value, $m ) && (int) $m[2] === (int) $m[1] + 1 ) { return $m[1] . '-' . $m[2]; }
        if ( preg_match( '/^\d{4}$/', $value ) ) {
            $year = (int) $value;
            $current = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '';
            if ( preg_match( '/^(\d{4})-(\d{4})$/', $current, $m ) && in_array( $year, array( (int) $m[1], (int) $m[2] ), true ) ) { return $current; }
            return 'season_end_year' === $column ? ( $year - 1 ) . '-' . $year : $year . '-' . ( $year + 1 );
        }
        return '';
    }

    /** Normalize only explicitly supported annual statuses. */
    public static function normalize_status( $status ) {
        $key = sanitize_title( remove_accents( trim( (string) $status ) ) );
        $active = array( 'active' => 'active', 'actif' => 'active', 'validated' => 'validated', 'valide' => 'validated', 'validee' => 'validated', 'approved' => 'validated', 'approuve' => 'validated', 'approuvee' => 'validated' );
        return $active[$key] ?? str_replace( '-', '_', $key );
    }

    /** Canonical, read-only resolver shared by every annual-affiliation consumer. */
    public static function resolve_affiliation( $club_id, $season ) {
        global $wpdb;
        $club_id = absint( $club_id );
        $requested = sanitize_text_field( (string) $season );
        $normalized = self::normalize_season( $requested );
        $table = self::get_affiliations_table();
        $columns = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_columns( $table ) : ( function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : ( method_exists( $wpdb, 'get_col' ) ? (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 ) : array( 'id', 'club_id', 'season', 'status', 'payment_status', 'wc_order_id' ) ) );
        $result = array( 'row' => null, 'club_id' => $club_id, 'requested_season' => $requested, 'season' => $normalized, 'source_table' => $table, 'source_column' => '', 'columns' => $columns, 'rows_found' => 0, 'duplicate_count' => 0, 'code' => 'affiliation_resolution_error' );
        $club_column = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        $season_columns = array_values( array_intersect( array( 'season', 'saison', 'paid_season', 'season_end_year' ), $columns ) );
        if ( $club_id < 1 || '' === $normalized || '' === $club_column || empty( $season_columns ) ) { return $result; }
        $start = (int) substr( $normalized, 0, 4 ); $end = $start + 1;
        $rows = array(); $season_column = $season_columns[0];
        foreach ( $season_columns as $candidate_column ) {
            $values = 'season_end_year' === $candidate_column ? array( (string) $end, (string) $start ) : array( $normalized, (string) $start, (string) $end );
            $placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
            $args = array_merge( array( $club_id ), $values );
            if ( method_exists( $wpdb, 'get_results' ) ) {
                $candidate_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_column}`=%d AND CAST(`{$candidate_column}` AS CHAR) IN ({$placeholders})", $args ) );
                $candidate_rows = is_array( $candidate_rows ) ? $candidate_rows : array();
            } else {
                $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_column}`=%d AND `{$candidate_column}`=%s LIMIT 1", $club_id, $normalized ) );
                $candidate_rows = $row ? array( $row ) : array();
            }
            if ( $candidate_rows ) { $rows = $candidate_rows; $season_column = $candidate_column; break; }
        }
        $status_column = in_array( 'status', $columns, true ) ? 'status' : ( in_array( 'statut', $columns, true ) ? 'statut' : '' );
        usort( $rows, static function( $a, $b ) use ( $status_column ) {
            $rank = static function( $row ) use ( $status_column ) { $status = $status_column ? self::normalize_status( $row->{$status_column} ?? '' ) : ''; if ( in_array( $status, array( 'active', 'validated' ), true ) ) return 30; if ( in_array( $status, array( 'pending_payment', 'pending_validation', 'pending' ), true ) ) return 20; return 10; };
            $difference = $rank( $b ) - $rank( $a );
            return $difference ?: ( absint( $b->id ?? $b->affiliation_id ?? 0 ) - absint( $a->id ?? $a->affiliation_id ?? 0 ) );
        } );
        $result['row'] = $rows[0] ?? null; $result['rows_found'] = count( $rows ); $result['duplicate_count'] = max( 0, count( $rows ) - 1 ); $result['source_column'] = $season_column; $result['status_column'] = $status_column; $result['code'] = $result['row'] ? 'affiliation_found' : 'affiliation_missing';
        return $result;
    }

    /** Return one annual affiliation without consulting the permanent club row. */
    public static function get_affiliation( $club_id, $season ) {
        $resolved = self::resolve_affiliation( $club_id, $season );
        return $resolved['row'];
    }

    /**
     * Record a paid annual renewal. The unique (club_id, season) key and this
     * upsert make repeated WooCommerce callbacks idempotent.
     */
    public static function record_paid_renewal( $club_id, $season, $order_id, $product_id, $previous_affiliation_id = 0 ) {
        global $wpdb;

        $club_id = absint( $club_id );
        $order_id = absint( $order_id );
        $product_id = absint( $product_id );
        $previous_affiliation_id = absint( $previous_affiliation_id );
        $season = sanitize_text_field( (string) $season );
        if ( $club_id <= 0 || $order_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            return false;
        }

        $table = self::get_affiliations_table();
        $now   = current_time( 'mysql' );
        $sql   = "INSERT INTO `{$table}` (club_id, season, status, payment_status, wc_order_id, previous_affiliation_id, product_id, request_type, requested_at, paid_at, created_at, updated_at)
            VALUES (%d, %s, 'pending_validation', 'paid', %d, NULLIF(%d, 0), %d, 'renewal', %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE payment_status = 'paid', wc_order_id = VALUES(wc_order_id), product_id = VALUES(product_id), paid_at = COALESCE(paid_at, VALUES(paid_at)), updated_at = VALUES(updated_at)";

        return false !== $wpdb->query( $wpdb->prepare( $sql, $club_id, $season, $order_id, $previous_affiliation_id, $product_id, $now, $now, $now, $now ) );
    }

	/** Create or update one annual row from the club administration screen. */
	public static function save_admin_affiliation( $club_id, $season, $values, $user_id ) {
		global $wpdb;
		$club_id = absint( $club_id );
		$user_id = absint( $user_id );
		$season  = sanitize_text_field( (string) $season );
		$status  = sanitize_key( (string) ( $values['status'] ?? 'a_renouveler' ) );
		$reason  = sanitize_textarea_field( (string) ( $values['decision_reason'] ?? '' ) );
		if ( $club_id < 1 || $user_id < 1 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! in_array( $status, self::ADMIN_STATUSES, true ) ) {
			return new WP_Error( 'invalid_annual_affiliation', __( 'Données d’affiliation annuelle invalides.', 'ufsc-clubs' ) );
		}
		if ( in_array( $status, array( 'correction_required', 'rejected', 'suspended' ), true ) && '' === trim( $reason ) ) {
			return new WP_Error( 'annual_reason_required', __( 'Un motif est obligatoire pour cette décision.', 'ufsc-clubs' ) );
		}
		$existing = self::get_affiliation( $club_id, $season );
		$now      = current_time( 'mysql' );
		$history  = $existing && ! empty( $existing->review_history ) ? json_decode( $existing->review_history, true ) : array();
		$history  = is_array( $history ) ? $history : array();
		$history[] = array( 'status' => $status, 'reason' => $reason, 'user_id' => $user_id, 'date' => $now );
		$data = array(
			'club_id' => $club_id, 'season' => $season, 'status' => $status,
			'payment_status' => sanitize_key( (string) ( $values['payment_status'] ?? '' ) ),
			'wc_order_id' => absint( $values['wc_order_id'] ?? 0 ) ?: null,
			'previous_affiliation_id' => absint( $values['previous_affiliation_id'] ?? 0 ) ?: null,
			'request_type' => sanitize_key( (string) ( $values['request_type'] ?? 'offline' ) ),
			'num_affiliation' => sanitize_text_field( (string) ( $values['num_affiliation'] ?? '' ) ) ?: null,
			'requested_at' => sanitize_text_field( (string) ( $values['requested_at'] ?? '' ) ) ?: $now,
			'paid_at' => sanitize_text_field( (string) ( $values['paid_at'] ?? '' ) ) ?: null,
			'validated_at' => 'active' === $status ? $now : ( $existing->validated_at ?? null ),
			'validated_by' => 'active' === $status ? $user_id : ( $existing->validated_by ?? null ),
			'decision_reason' => $reason ?: null, 'review_history' => wp_json_encode( $history ), 'updated_at' => $now,
		);
		if ( $existing ) {
			$result = $wpdb->update( self::get_affiliations_table(), $data, array( 'id' => absint( $existing->id ) ) );
		} else {
			$data['created_at'] = $now;
			$result = $wpdb->insert( self::get_affiliations_table(), $data );
		}
		return false === $result ? new WP_Error( 'annual_affiliation_write_failed', __( 'Échec de l’enregistrement annuel.', 'ufsc-clubs' ) ) : true;
	}
}
