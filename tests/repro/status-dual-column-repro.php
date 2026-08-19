<?php
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' ); }

function __( $text ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) ); }
function wp_parse_args( $args, $defaults ) { return array_merge( $defaults, $args ); }
function ufsc_table_columns( $table ) { return array( 'id', 'statut', 'status' ); }

final class Repro_Status_WPDB {
    public $row = array( 'id' => 900, 'statut' => 'brouillon', 'status' => 'draft' );

    public function update( $table, $data, $where, $format, $where_format ) {
        // Simulate a legacy `status` constraint which accepts legacy values such as
        // draft/pending, but rejects the canonical French value `en_attente`.
        if ( isset( $data['status'] ) && 'en_attente' === $data['status'] ) {
            return false;
        }
        foreach ( $data as $key => $value ) {
            $this->row[ $key ] = $value;
        }
        return 1;
    }
}

$wpdb = new Repro_Status_WPDB();
require_once dirname( __DIR__, 2 ) . '/inc/common/licence-status.php';

$result = UFSC_Licence_Status::update_status_columns(
    'wp_ufsc_licences',
    array( 'id' => 900 ),
    'en_attente',
    array( '%d' )
);

if ( 'en_attente' !== $wpdb->row['statut'] ) {
    fwrite( STDERR, "REPRODUCED: canonical statut stayed brouillon because legacy status rejected en_attente\n" );
    exit( 2 );
}
if ( false === $result ) {
    fwrite( STDERR, "FAIL: canonical status transition should not be lost because of legacy compatibility\n" );
    exit( 1 );
}

echo "OK: canonical statut persisted independently from legacy status compatibility\n";
