<?php
/** Reconcile every frontend KPI partition against the canonical season rows. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
function current_time( $type ) { return 'Y-m-d' === $type ? '2026-08-12' : '2026-08-12 12:00:00'; }
function ufsc_get_licences_table() { return 'wp_licences'; }
function ufsc_table_columns() { return array( 'id', 'club_id', 'season', 'statut', 'status', 'deleted_at', 'sexe', 'competition', 'date_naissance', 'paid' ); }
function ufsc_get_licence_status_norm( $status ) {
    $status = sanitize_key( $status );
    return in_array( $status, array( 'draft', 'a_completer' ), true ) ? 'brouillon' : ( 'validated' === $status ? 'valide' : $status );
}
class Canonical_KPI_WPDB {
    public $prefix = 'wp_';
    public $query = '';
    public function prepare( $query, ...$values ) {
        foreach ( $values as $value ) { $query = preg_replace( '/%[ds]/', is_int( $value ) ? (string) $value : "'" . $value . "'", $query, 1 ); }
        return $query;
    }
    public function get_results( $query ) {
        $this->query = $query;
        return array(
            (object) array( 'id' => 1, 'club_id' => 7, 'season' => '2026-2027', 'statut' => 'brouillon', 'status' => 'valide', 'sexe' => 'F', 'competition' => 0, 'date_naissance' => '2012-01-15', 'paid' => 0 ),
            (object) array( 'id' => 2, 'club_id' => 7, 'season' => '2026-2027', 'statut' => 'brouillon', 'sexe' => 'M', 'competition' => 1, 'date_naissance' => '1980-06-20', 'paid' => 0 ),
            (object) array( 'id' => 3, 'club_id' => 7, 'season' => '2026-2027', 'statut' => 'brouillon', 'sexe' => '', 'competition' => null, 'date_naissance' => '', 'paid' => 0 ),
            (object) array( 'id' => 4, 'club_id' => 7, 'season' => '2026-2027', 'statut' => 'valide', 'sexe' => 'F', 'competition' => 1, 'date_naissance' => '2000-03-11', 'paid' => 1 ),
        );
    }
}
$wpdb = new Canonical_KPI_WPDB();
$GLOBALS['wpdb'] = $wpdb;
require dirname( __DIR__ ) . '/includes/front/class-ufsc-stats.php';
$stats = UFSC_Stats::get_club_stats( 7, '2026-2027' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$total = $stats['total_licences'];
$assert( 4 === $total && 3 === $stats['draft_licences'], 'three canonical statut drafts must reconcile with the four-row list' );
$assert( $stats['validated_licences'] + $stats['draft_licences'] + $stats['other_statuses'] === $total, 'exclusive status partitions reconcile' );
$assert( array_sum( $stats['by_gender'] ) === $total, 'women + men + unknown reconcile' );
$assert( array_sum( $stats['by_age'] ) === $total, 'minor + adult + unknown reconcile' );
$assert( $stats['by_practice']['leisure'] + $stats['by_practice']['competition'] + $stats['by_practice']['unknown'] === $total, 'leisure + competition + unknown reconcile' );
$assert( 1 === $stats['unknown_profiles'], 'unknown profile counts people, not missing-field occurrences' );
$assert( false !== strpos( $wpdb->query, 'club_id = 7' ) && false !== strpos( $wpdb->query, "2026-2027" ), 'club and season are bound into the canonical row query' );
echo "Canonical KPI/list reconciliation runtime safeguards OK\n";
