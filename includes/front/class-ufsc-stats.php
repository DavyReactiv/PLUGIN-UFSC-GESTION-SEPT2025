<?php
/**
 * Canonical UFSC statistics helper for frontend dashboards.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Stats {
    private $wpdb;
    private $table;

    public function __construct( $wpdb = null ) {
        if ( $wpdb ) { $this->wpdb = $wpdb; } else { global $wpdb; $this->wpdb = $wpdb; }
        $this->table = $this->wpdb->prefix . 'ufsc_licences';
    }

    public function get_gender_counts() {
        $sql = "SELECT gender, COUNT(*) AS total FROM {$this->table} WHERE ( status = %s OR paid = %d ) GROUP BY gender";
        return $this->wpdb->get_results( $this->wpdb->prepare( $sql, 'active', 1 ), ARRAY_A );
    }

    public function get_practice_counts() {
        $sql = "SELECT practice, COUNT(*) AS total FROM {$this->table} WHERE ( status = %s OR paid = %d ) GROUP BY practice";
        return $this->wpdb->get_results( $this->wpdb->prepare( $sql, 'active', 1 ), ARRAY_A );
    }

    public function get_age_group_counts() {
        $sql = "SELECT age_group, COUNT(*) AS total FROM (
                    SELECT CASE
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) < 18 THEN '0-17'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 18 AND 25 THEN '18-25'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 26 AND 35 THEN '26-35'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 36 AND 45 THEN '36-45'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 46 AND 60 THEN '46-60'
                        ELSE '60+'
                    END AS age_group
                    FROM {$this->table}
                    WHERE ( status = %s OR paid = %d ) AND birthdate IS NOT NULL
                ) AS derived GROUP BY age_group";
        return $this->wpdb->get_results( $this->wpdb->prepare( $sql, 'active', 1 ), ARRAY_A );
    }

    /**
     * Aggregate the exact row set shown by the current-season licence list.
     * Every partition is exhaustive so reconciliation can be asserted.
     */
    public static function get_club_stats( $club_id, $season = null ) {
        global $wpdb;
        $table = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences';
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "DESCRIBE `{$table}`" );
        $where = $wpdb->prepare( 'club_id = %d', absint( $club_id ) );
        if ( in_array( 'deleted_at', $columns, true ) ) {
            $where .= " AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
        }
        if ( null !== $season ) {
            $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
            if ( '' === $season_column ) {
                foreach ( array( 'paid_season', 'season', 'saison', 'season_end_year' ) as $candidate ) {
                    if ( in_array( $candidate, $columns, true ) ) { $season_column = $candidate; break; }
                }
            }
            if ( 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', (string) $season, $matches ) ) {
                $where .= $wpdb->prepare( ' AND season_end_year = %d', (int) $matches[1] );
            } elseif ( $season_column ) {
                $where .= $wpdb->prepare( " AND REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', (string) $season ) );
            } else {
                $where .= ' AND 0 = 1';
            }
        }

        $rows = (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE {$where}" );
        $status_counts = array();
        $paid_counts = array();
        $gender_counts = array( 'F' => 0, 'M' => 0, 'unknown' => 0 );
        $practice_counts = array( 'leisure' => 0, 'competition' => 0, 'unknown' => 0, 0 => 0, 1 => 0 );
        $age_counts = array( 'minor' => 0, 'adult' => 0, 'unknown' => 0 );
        $age_ranges = array( 'under_12' => 0, '12_17' => 0, '18_40' => 0, '41_plus' => 0 );
        $birth_year_counts = array();
        $paid_licences = 0;
		$pending_payments = 0;
        $validated_licences = 0;
        $unknown_profiles = 0;
        $today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : date( 'Y-m-d' );
        $reference = new DateTimeImmutable( $today );

        foreach ( $rows as $row ) {
            $business_state = function_exists( 'ufsc_resolve_licence_business_state' ) ? ufsc_resolve_licence_business_state( $row ) : array();
            $raw_status = function_exists( 'ufsc_get_licence_status_raw' ) ? ufsc_get_licence_status_raw( $row ) : ( isset( $row->statut ) ? $row->statut : ( $row->status ?? '' ) );
            $status = $business_state['dossier'] ?? ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $raw_status ) : strtolower( trim( (string) $raw_status ) ) );
            $status = '' !== $status ? $status : 'brouillon';
            $status_counts[ $status ] = ( $status_counts[ $status ] ?? 0 ) + 1;
            $official = ! empty( $business_state['official'] ) || ( empty( $business_state ) && 'valide' === $status );
            if ( $official ) { $validated_licences++; }

            $paid = ! empty( $business_state['payment_received'] ) ? 1 : 0;
			if ( 'requis' === ( $business_state['payment'] ?? '' ) ) { $pending_payments++; }
            $paid_counts[ $paid ] = ( $paid_counts[ $paid ] ?? 0 ) + 1;
            $paid_licences += $paid;

			if ( ! $official ) { continue; }

            $gender = strtoupper( trim( (string) ( $row->sexe ?? '' ) ) );
            $gender = in_array( $gender, array( 'F', 'FEMME' ), true ) ? 'F' : ( in_array( $gender, array( 'M', 'H', 'HOMME' ), true ) ? 'M' : 'unknown' );
            $gender_counts[ $gender ]++;

            $practice = ! isset( $row->competition ) || null === $row->competition || '' === (string) $row->competition ? 'unknown' : ( 1 === (int) $row->competition ? 'competition' : 'leisure' );
            $practice_counts[ $practice ]++;
            if ( 'leisure' === $practice ) { $practice_counts[0]++; }
            if ( 'competition' === $practice ) { $practice_counts[1]++; }

            $birth_raw = trim( (string) ( $row->date_naissance ?? '' ) );
            $birth = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth_raw ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $birth_raw ) : false;
            if ( 'unknown' === $gender || 'unknown' === $practice || ! $birth || $birth->format( 'Y-m-d' ) !== $birth_raw || $birth > $reference ) { $unknown_profiles++; }
            if ( ! $birth || $birth->format( 'Y-m-d' ) !== $birth_raw || $birth > $reference ) { $age_counts['unknown']++; continue; }
            $age = $birth->diff( $reference )->y;
            $age_counts[ $age < 18 ? 'minor' : 'adult' ]++;
            $age_ranges[ $age < 12 ? 'under_12' : ( $age < 18 ? '12_17' : ( $age <= 40 ? '18_40' : '41_plus' ) ) ]++;
            $year = $birth->format( 'Y' );
            $birth_year_counts[ $year ] = ( $birth_year_counts[ $year ] ?? 0 ) + 1;
        }

        $total = count( $rows );
        $drafts = (int) ( $status_counts['brouillon'] ?? 0 );
        return array(
            'total_licences'     => $total,
            'paid_licences'      => $paid_licences,
			'pending_payments'    => $pending_payments,
            'validated_licences' => $validated_licences,
            'draft_licences'     => $drafts,
            'other_statuses'     => max( 0, $total - $validated_licences - $drafts ),
            'quota_remaining'    => max( 0, 50 - $total ),
            'by_status'          => $status_counts,
            'by_paid'            => $paid_counts,
            'by_gender'          => $gender_counts,
            'by_practice'        => $practice_counts,
            'by_age'             => $age_counts,
            'by_age_range'       => $age_ranges,
            'by_birth_year'      => $birth_year_counts,
            'unknown_profiles'   => $unknown_profiles,
        );
    }
}
