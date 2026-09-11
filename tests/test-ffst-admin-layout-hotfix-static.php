<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/inc/common/ffst-admin-layout-hotfix.php' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $flags, "'/ffst-admin-layout-hotfix.php'" ), 'FFST layout hotfix is loaded' );
$assert( false !== strpos( $file, "'ufsc-ffst-documents' !== \$page" ), 'CSS is scoped to the FFST admin page only' );
$assert( false !== strpos( $file, 'max-width: 1320px' ), 'FFST admin content is capped on large monitors' );
$assert( false !== strpos( $file, 'max-width: 1280px' ), 'FFST tables and panels use a readable desktop width' );
$assert( false !== strpos( $file, 'table-layout: fixed' ), 'desktop club table uses stable column widths' );
$assert( false !== strpos( $file, '@media (max-width: 782px)' ), 'mobile layout has a dedicated responsive fallback' );
$assert( false !== strpos( $file, 'overflow-x: auto' ), 'wide tables remain usable on narrow screens' );
$assert( false !== strpos( $file, 'max-width: 820px' ), 'large form controls are capped to a readable width' );
$assert( false === stripos( $file, 'UPDATE ' ), 'presentation hotfix performs no database updates' );
$assert( false === stripos( $file, 'DELETE ' ), 'presentation hotfix performs no database deletes' );
$assert( false === stripos( $file, 'ALTER TABLE' ), 'presentation hotfix performs no schema mutation' );
