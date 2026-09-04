<?php
$root = dirname( __DIR__ );
$file = file_get_contents( $root . '/includes/admin/class-ffst-documents-admin.php' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $file, "admin_post_ufsc_ffst_upload_template" ), 'upload template action must be admin-only' );
$assert( false !== strpos( $file, "admin_post_ufsc_ffst_generate_licences" ), 'generation action must be admin-only' );
$assert( false !== strpos( $file, "UFSC_Permissions::CAP_GESTION_MANAGE" ), 'FFST generation must require management capability' );
$assert( false !== strpos( $file, "PhpOffice\\\\PhpSpreadsheet\\\\IOFactory" ), 'PhpSpreadsheet must be used for official Excel template generation' );
$assert( false !== strpos( $file, "TEMPLATE_OPTION" ), 'official template must be stored as an admin configuration' );
$assert( false !== strpos( $file, "Générer le bordereau dirigeants FFST" ), 'admin generation button must be present' );
$assert( false === strpos( $file, "add_shortcode" ), 'FFST documents must never be exposed through a front-end shortcode' );
$assert( false !== strpos( $file, "ffst_document_generated" ), 'generation must be audit logged' );

echo "OK\n";
