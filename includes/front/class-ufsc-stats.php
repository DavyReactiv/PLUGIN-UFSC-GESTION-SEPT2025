<?php
/**
 * UFSC statistics helper for frontend dashboards.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UFSC_Stats {
    /**
     * WordPress database object.
     *
     * @var wpdb
     */
    private $wpdb;

    /**
     * Table name for licences.
     *
     * @var string
     */
    private $table;

    /**
     * Constructor.
     *
     * @param wpdb|null $wpdb Optional injected wpdb instance.
     */
    public function __construct( $wpdb = null ) {
        if ( $wpdb ) {
            $this->wpdb = $wpdb;
        } else {
            global $wpdb;
            $this->wpdb = $wpdb;
        }
        $this->table = $this->wpdb->prefix . 'ufsc_licences';
    }

    /**
     * Get counts of active licences by gender.
     *
     * Uses prepared statements and benefits from an index on the gender column.
     *
     * @return array[] Array of [ gender => string, total => int ].
     */
    public function get_gender_counts() {
        $cache_key = 'ufsc_gender_counts';
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $sql = "SELECT gender, COUNT(*) AS total FROM {$this->table}
                WHERE status = %s
                GROUP BY gender";

        $prepared = $this->wpdb->prepare( $sql, 'active' );
        $results  = $this->wpdb->get_results( $prepared, ARRAY_A );
        set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

        return $results;
    }

    /**
     * Get counts of active licences by practice.
     *
     * Uses prepared statements and benefits from an index on the practice column.
     *
     * @return array[] Array of [ practice => string, total => int ].
     */
    public function get_practice_counts() {
        $cache_key = 'ufsc_practice_counts';
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $sql = "SELECT practice, COUNT(*) AS total FROM {$this->table}
                WHERE status = %s
                GROUP BY practice";

        $prepared = $this->wpdb->prepare( $sql, 'active' );
        $results  = $this->wpdb->get_results( $prepared, ARRAY_A );
        set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

        return $results;
    }

    /**
     * Get counts of active licences by age groups.
     *
     * Birthdate column is indexed allowing efficient age calculations.
     *
     * @return array[] Array of [ age_group => string, total => int ].
     */
    public function get_age_group_counts() {
        $cache_key = 'ufsc_age_group_counts';
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $sql = "SELECT age_group, COUNT(*) AS total FROM (
                    SELECT CASE
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 5 AND 11 THEN '5-11'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 12 AND 15 THEN '12-15'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 16 AND 17 THEN '16-17'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 18 AND 34 THEN '18-34'
                        WHEN TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) BETWEEN 35 AND 49 THEN '35-49'
                        ELSE '50+'
                    END AS age_group
                    FROM {$this->table}
                    WHERE status = %s
                      AND birthdate IS NOT NULL
                      AND TIMESTAMPDIFF( YEAR, birthdate, CURDATE() ) >= 5
                ) AS derived
                GROUP BY age_group";

        $prepared = $this->wpdb->prepare( $sql, 'active' );
        $results  = $this->wpdb->get_results( $prepared, ARRAY_A );
        set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

        return $results;
    }

    /**
     * Get counts of active licences by region.
     *
     * Relies on an index for the region column when available.
     *
     * @return array[] Array of [ region => string, total => int ].
     */
    public function get_region_counts() {
        $cache_key = 'ufsc_region_counts';
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $sql = "SELECT region, COUNT(*) AS total FROM {$this->table}
                WHERE status = %s
                  AND region IS NOT NULL
                  AND region != ''
                GROUP BY region";

        $prepared = $this->wpdb->prepare( $sql, 'active' );
        $results  = $this->wpdb->get_results( $prepared, ARRAY_A );
        set_transient( $cache_key, $results, 10 * MINUTE_IN_SECONDS );

        return $results;
    }

    /**
     * Get weekly licence creation counts for the last 12 weeks.
     *
     * @return array[] Array of [ week_start => string, total => int ].
     */
    public function get_12_week_licence_evolution() {
        $cache_key = 'ufsc_12_week_licence_evolution';
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $sql = "SELECT DATE_FORMAT(date_creation, '%x%v') AS week, COUNT(*) AS total
                FROM {$this->table}
                WHERE status = %s
                  AND date_creation >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                GROUP BY week
                ORDER BY week ASC";

        $prepared = $this->wpdb->prepare( $sql, 'active' );
        $rows     = $this->wpdb->get_results( $prepared, ARRAY_A );

        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row['week'] ] = (int) $row['total'];
        }

        $evolution = array();
        for ( $i = 11; $i >= 0; $i-- ) {
            $week_key   = gmdate( 'oW', strtotime( "-{$i} week" ) );
            $week_start = gmdate( 'Y-m-d', strtotime( "-{$i} week Monday" ) );
            $evolution[] = array(
                'week_start' => $week_start,
                'total'      => isset( $counts[ $week_key ] ) ? $counts[ $week_key ] : 0,
            );
        }

        set_transient( $cache_key, $evolution, 10 * MINUTE_IN_SECONDS );

        return $evolution;
    }

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

        $where_clauses = array( 'club_id = %d' );
        $where_values  = array( $club_id );
        if ( null !== $season ) {
            // Season column optional; only add if exists.
            $columns = method_exists( $wpdb, 'get_col' ) ? $wpdb->get_col( "DESCRIBE `{$table}`" ) : array();
            if ( in_array( 'season', $columns, true ) ) {
                $where_clauses[] = 'season = %d';
                $where_values[]  = $season;
            }
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // Status distribution
        $status_sql   = "SELECT status, COUNT(*) AS count FROM `{$table}` WHERE {$where_sql} GROUP BY status";
        $status_rows  = $wpdb->get_results( $wpdb->prepare( $status_sql, $where_values ) );
        $status_counts = array();
        $total_licences = 0;
        foreach ( $status_rows as $row ) {
            $status_counts[ $row->status ] = (int) $row->count;
            $total_licences += (int) $row->count;
        }

        // Paid distribution
        $paid_sql    = "SELECT paid, COUNT(*) AS count FROM `{$table}` WHERE {$where_sql} GROUP BY paid";
        $paid_rows   = $wpdb->get_results( $wpdb->prepare( $paid_sql, $where_values ) );
        $paid_counts = array();
        $paid        = 0;
        foreach ( $paid_rows as $row ) {
            $paid_counts[ $row->paid ] = (int) $row->count;
            if ( '1' == $row->paid || 1 === (int) $row->paid ) {
                $paid = (int) $row->count;
            }
        }

        // Gender distribution
        $gender_sql   = "SELECT gender, COUNT(*) AS count FROM `{$table}` WHERE {$where_sql} GROUP BY gender";
        $gender_rows  = $wpdb->get_results( $wpdb->prepare( $gender_sql, $where_values ) );
        $gender_counts = array();
        foreach ( $gender_rows as $row ) {
            $gender_counts[ $row->gender ] = (int) $row->count;
        }

        // Practice distribution
        $practice_sql   = "SELECT practice, COUNT(*) AS count FROM `{$table}` WHERE {$where_sql} GROUP BY practice";
        $practice_rows  = $wpdb->get_results( $wpdb->prepare( $practice_sql, $where_values ) );
        $practice_counts = array();
        foreach ( $practice_rows as $row ) {
            $practice_counts[ $row->practice ] = (int) $row->count;
        }

        // Birth year distribution
        $birth_sql   = "SELECT YEAR(birthdate) AS year, COUNT(*) AS count FROM `{$table}` WHERE {$where_sql} GROUP BY YEAR(birthdate)";
        $birth_rows  = $wpdb->get_results( $wpdb->prepare( $birth_sql, $where_values ) );
        $birth_year_counts = array();
        foreach ( $birth_rows as $row ) {
            $birth_year_counts[ $row->year ] = (int) $row->count;
        }

        // Determine validated licences based on canonical status
        $validated = isset( $status_counts['active'] ) ? (int) $status_counts['active'] : 0;

        // Quota remaining based on included active licences
        $included_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql} AND is_included = %d AND status = %s";
        $included_active = (int) $wpdb->get_var(
            $wpdb->prepare(
                $included_sql,
                array_merge( $where_values, array( 1, 'active' ) )
            )
        );

        $quota_remaining = max( 0, 10 - $included_active );

        return array(
            'total_licences'     => $total_licences,
            'paid_licences'      => $paid,
            'validated_licences' => $validated,
            // Legacy keys preserved for backward compatibility.
            'paid'               => $paid,
            'validated'          => $validated,
            'quota_remaining'    => $quota_remaining,
            'by_status'          => $status_counts,
            'by_paid'            => $paid_counts,
            'by_gender'          => $gender_counts,
            'by_practice'        => $practice_counts,
            'by_birth_year'      => $birth_year_counts,
        );
    }
}
