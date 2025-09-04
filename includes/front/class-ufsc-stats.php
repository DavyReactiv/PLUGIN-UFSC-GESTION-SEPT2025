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
}
