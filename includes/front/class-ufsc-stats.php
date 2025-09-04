<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Stats {
    /**
     * Get aggregated statistics for a club's licences.
     * Utilises indexed columns and GROUP BY queries for performance.
     *
     * @param int      $club_id Club identifier.
     * @param null|int $season  Optional season filter.
     * @return array  Aggregated statistics.
     */
    public static function get_club_stats( $club_id, $season = null ) {
        global $wpdb;

        if ( function_exists( 'ufsc_get_licences_table' ) ) {
            $table = ufsc_get_licences_table();
        } else {
            $table = $wpdb->prefix . 'ufsc_licences';
        }

        $where = $wpdb->prepare( 'club_id = %d', $club_id );
        if ( null !== $season ) {
            // Season column optional; only add if exists.
            $columns = $wpdb->get_col( "DESCRIBE `{$table}`" );
            if ( in_array( 'season', $columns, true ) ) {
                $where .= $wpdb->prepare( ' AND season = %d', $season );
            }
        }

        // Status distribution
        $status_rows = $wpdb->get_results( "SELECT status, COUNT(*) AS count FROM `{$table}` WHERE {$where} GROUP BY status" );
        $status_counts = array();
        $total_licences = 0;
        foreach ( $status_rows as $row ) {
            $status_counts[ $row->status ] = (int) $row->count;
            $total_licences += (int) $row->count;
        }

        // Paid distribution
        $paid_rows = $wpdb->get_results( "SELECT paid, COUNT(*) AS count FROM `{$table}` WHERE {$where} GROUP BY paid" );
        $paid_counts = array();
        $paid_licences = 0;
        foreach ( $paid_rows as $row ) {
            $paid_counts[ $row->paid ] = (int) $row->count;
            if ( '1' == $row->paid || 1 === (int) $row->paid ) {
                $paid_licences = (int) $row->count;
            }
        }

        // Gender distribution
        $gender_rows = $wpdb->get_results( "SELECT gender, COUNT(*) AS count FROM `{$table}` WHERE {$where} GROUP BY gender" );
        $gender_counts = array();
        foreach ( $gender_rows as $row ) {
            $gender_counts[ $row->gender ] = (int) $row->count;
        }

        // Practice distribution
        $practice_rows = $wpdb->get_results( "SELECT practice, COUNT(*) AS count FROM `{$table}` WHERE {$where} GROUP BY practice" );
        $practice_counts = array();
        foreach ( $practice_rows as $row ) {
            $practice_counts[ $row->practice ] = (int) $row->count;
        }

        // Birth year distribution
        $birth_rows = $wpdb->get_results( "SELECT YEAR(birthdate) AS year, COUNT(*) AS count FROM `{$table}` WHERE {$where} GROUP BY YEAR(birthdate)" );
        $birth_year_counts = array();
        foreach ( $birth_rows as $row ) {
            $birth_year_counts[ $row->year ] = (int) $row->count;
        }

        // Determine validated licences based on status
        $validated_statuses = array( 'validated', 'valide', 'validée', 'validé', 'approved' );
        $validated_licences = 0;
        foreach ( $validated_statuses as $status ) {
            if ( isset( $status_counts[ $status ] ) ) {
                $validated_licences += $status_counts[ $status ];
            }
        }

        return array(
            'total_licences'     => $total_licences,
            'paid_licences'      => $paid_licences,
            'validated_licences' => $validated_licences,
            'quota_remaining'    => max( 0, 50 - $total_licences ),
            'by_status'          => $status_counts,
            'by_paid'            => $paid_counts,
            'by_gender'          => $gender_counts,
            'by_practice'        => $practice_counts,
            'by_birth_year'      => $birth_year_counts,
        );
    }
}
