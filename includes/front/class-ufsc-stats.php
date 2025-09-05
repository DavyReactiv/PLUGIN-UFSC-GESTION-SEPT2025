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
        $sql = "SELECT gender, COUNT(*) AS total FROM {$this->table}
                WHERE ( status = %s OR paid = %d )
                GROUP BY gender";

        $prepared = $this->wpdb->prepare( $sql, 'active', 1 );
        return $this->wpdb->get_results( $prepared, ARRAY_A );
    }

    /**
     * Get counts of active licences by practice.
     *
     * Uses prepared statements and benefits from an index on the practice column.
     *
     * @return array[] Array of [ practice => string, total => int ].
     */
    public function get_practice_counts() {
        $sql = "SELECT practice, COUNT(*) AS total FROM {$this->table}
                WHERE ( status = %s OR paid = %d )
                GROUP BY practice";

        $prepared = $this->wpdb->prepare( $sql, 'active', 1 );
        return $this->wpdb->get_results( $prepared, ARRAY_A );
    }

    /**
     * Get counts of active licences by age groups.
     *
     * Birthdate column is indexed allowing efficient age calculations.
     *
     * @return array[] Array of [ age_group => string, total => int ].
     */
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
                    WHERE ( status = %s OR paid = %d )
                      AND birthdate IS NOT NULL
                ) AS derived
                GROUP BY age_group";

        $prepared = $this->wpdb->prepare( $sql, 'active', 1 );
        return $this->wpdb->get_results( $prepared, ARRAY_A );
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
