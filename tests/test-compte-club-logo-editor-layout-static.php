<?php
$root   = dirname( __DIR__ );
$css    = file_get_contents( $root . '/assets/css/ufsc-account-logo-editor-fix.css' );
$layout = file_get_contents( $root . '/inc/common/account-overview-layout.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $layout, "'ufsc-account-logo-editor-fix'" ), 'logo editor repair stylesheet must be enqueued' );
$assert( false !== strpos( $layout, "array( 'ufsc-account-overview-fix' )" ), 'logo editor repair must load after the validated overview stylesheet' );
$assert( false !== strpos( $css, '.ufsc-logo-editor {' ), 'logo editor repair must remain narrowly scoped' );
$assert( false !== strpos( $css, 'grid-template-columns: minmax(0, 1fr) !important;' ), 'logo editor must override the legacy internal two-column grid' );
$assert( false !== strpos( $css, '.ufsc-logo-editor__upload' ), 'upload form must be covered by the repair' );
$assert( false !== strpos( $css, 'grid-column: 1 !important;' ), 'logo editor subcomponents must stay on the single internal column' );
$assert( false !== strpos( $css, 'word-break: normal !important;' ), 'logo helper text must not collapse into narrow character wrapping' );
$assert( false === strpos( $css, 'WC()->cart' ), 'presentation CSS must not contain cart behaviour' );
$assert( false === strpos( $layout, 'ALTER TABLE' ), 'layout loader must not touch the database schema' );
$assert( false === strpos( $layout, 'UFSC_Unified_Handlers::' ), 'layout loader must not invoke licence business handlers' );

echo "Compte Club logo editor layout safeguards OK\n";
