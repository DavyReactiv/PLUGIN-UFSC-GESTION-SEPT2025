<?php
$path = dirname( __DIR__ ) . '/includes/admin/class-ffst-matrix-export-admin.php';
$code = file_get_contents( $path );
if ( false === $code ) { fwrite( STDERR, "matrix module missing\n" ); exit( 1 ); }
$forbidden = array( 'WC()->cart', 'add_to_cart(', 'consume_pack_credit', 'quota', 'renewal', '$wpdb->update(', '$wpdb->insert(', '$wpdb->delete(' );
foreach ( $forbidden as $needle ) {
    if ( false !== strpos( $code, $needle ) ) { fwrite( STDERR, "forbidden write/commerce reference: {$needle}\n" ); exit( 1 ); }
}
foreach ( array( 'affiliations', 'dirigeants', 'pratiquants', 'club_ids[]', 'Sélectionner les clubs affichés' ) as $required ) {
    if ( false === strpos( $code, $required ) ) { fwrite( STDERR, "required matrix feature missing: {$required}\n" ); exit( 1 ); }
}
echo "FFST matrix export read-only guard OK\n";
