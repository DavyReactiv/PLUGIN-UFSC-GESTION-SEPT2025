<?php
$root = dirname( __DIR__ );
$fix  = file_get_contents( $root . '/inc/common/account-profile-injection-fix.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $flags, "'/account-profile-injection-fix.php'" ), 'feature composition must load the Compte Club placement repair' );
$assert( false !== strpos( $fix, "remove_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output', 20 )" ), 'historical first-form enrichment filter must be removed' );
$assert( false !== strpos( $fix, "'class=\"ufsc-club-form ufsc-club-profile\"'" ), 'insertion must anchor to the canonical club profile form' );
$assert( false !== strpos( $fix, "strrpos( $before_marker, '<form' )" ), 'insertion must resolve the opening form immediately preceding the profile form marker' );
$assert( false === strpos( $fix, "ufsc-logo-editor__upload" ), 'repair must not target or modify the nested logo upload form' );
$assert( false === strpos( $fix, 'UFSC_Unified_Handlers::' ), 'repair must not call licence business handlers' );
$assert( false === strpos( $fix, 'WC()->cart' ), 'repair must not touch WooCommerce cart logic' );
$assert( false === strpos( $fix, 'ALTER TABLE' ), 'repair must not alter the database schema' );
$assert( false === strpos( $fix, '$wpdb->' ), 'repair must not perform database writes or reads' );

echo "Compte Club action box placement safeguards OK\n";
