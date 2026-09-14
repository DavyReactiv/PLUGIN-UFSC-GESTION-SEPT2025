<?php
$root = dirname( __DIR__ );
$module = file_get_contents( $root . '/inc/common/admin-ffst-leaders-reporting.php' );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-identifier-resolver.php' );

$assert = static function ( $ok, $message ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $bootstrap, "inc/common/admin-ffst-leaders-reporting.php" ), 'bootstrap loads FFST leaders reporting module' );
$assert( false !== strpos( $resolver, "'licence_ffst' => array( 'numero_licence_ffst' )" ), 'identifier resolver exposes canonical FFST licence number' );
$assert( false !== strpos( $module, "'affiliations_active'" ), 'active status reuses canonical annual KPI filter' );
$assert( false !== strpos( $module, 'Actif (affiliation de la saison)' ), 'clubs status selector exposes annual active state' );
$assert( false !== strpos( $module, 'N° licence FFST' ) && false !== strpos( $module, 'numero_licence_ffst' ), 'admin licence presentation uses FFST number' );
$assert( false !== strpos( $module, 'admin_post_ufsc_export_active_leaders_csv' ), 'active-club leaders CSV endpoint exists' );
$assert( false !== strpos( $module, "array( 'president', 'secretaire', 'tresorier' )" ), 'mandatory office roles are explicit' );
$assert( false !== strpos( $module, "'entraineur'" ) && false !== strpos( $module, "'encadrant'" ), 'coaching roles are included in export' );
$assert( false !== strpos( $module, 'ufsc_get_pack_included_limit' ) && false !== strpos( $module, 'ufsc_get_pack_usage' ), 'quota display reuses canonical pack helpers' );
$assert( false !== strpos( $module, '3 places identifiées pour le bureau' ), 'quota explicitly presents three office places' );
$assert( false === stripos( $module, 'DELETE FROM' ), 'reporting layer never deletes data' );
$assert( false === stripos( $module, 'TRUNCATE' ), 'reporting layer never truncates data' );
$assert( false === preg_match( '/\$wpdb->(?:update|insert|delete|replace)\s*\(/i', $module ), 'reporting layer performs no row mutation' );

fwrite( STDOUT, "Admin FFST leaders reporting safeguards OK\n" );
