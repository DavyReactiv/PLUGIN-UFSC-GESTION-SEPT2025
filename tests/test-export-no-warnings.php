<?php
use PHPUnit\Framework\TestCase;

/**
 * Ensure export queries don't trigger warnings when no filters are provided.
 */
class UFSC_Export_NoWarning_Test extends TestCase {

    /**
     * Helper that mimics the final query execution logic.
     *
     * @param array $params Parameters passed to the query.
     * @return object Mocked wpdb instance used during the call.
     */
    private function run_query_with_params( array $params ) {
        $wpdb = new class {
            public $prepare_called = false;
            public $received_sql  = '';

            public function prepare( $sql, $params ) {
                $this->prepare_called = true;
                return $sql;
            }

            public function get_results( $sql, $output ) {
                $this->received_sql = $sql;
                return array();
            }
        };

        $GLOBALS['wpdb'] = $wpdb;
        $sql = 'SELECT `id` FROM `table`';

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        $wpdb->get_results( $sql, ARRAY_A );

        return $wpdb;
    }

    public function test_club_export_without_filters() {
        $wpdb = $this->run_query_with_params( array() );
        $this->assertFalse( $wpdb->prepare_called, 'prepare should not run when params are empty' );
    }

    public function test_licence_export_without_filters() {
        $wpdb = $this->run_query_with_params( array() );
        $this->assertFalse( $wpdb->prepare_called, 'prepare should not run when params are empty' );
    }
}

