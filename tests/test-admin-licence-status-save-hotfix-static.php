<?php
$root   = dirname( __DIR__ );
$hotfix = file_get_contents( $root . '/inc/common/admin-licence-status-save-hotfix.php' );
$loader = file_get_contents( $root . '/inc/common/front-club-registration-scope-hotfix.php' );

$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $loader, 'admin-licence-status-save-hotfix.php' ), 'Le hotfix statut licence doit être chargé.' );
$assert( false !== strpos( $hotfix, "'ufsc_sql_save_licence'" ), 'Le correctif doit rester limité au save licence admin canonique.' );
$assert( false !== strpos( $hotfix, "remove_action(\n            'ufsc_licence_updated'" ), 'Les observateurs front doivent être détachés uniquement du hook post-save.' );
$assert( false !== strpos( $hotfix, 'UFSC_Licence_Finalization_Runtime' ), 'La finalisation runtime front doit être neutralisée pendant le save admin.' );
$assert( false !== strpos( $hotfix, 'ufsc_structural_finalize_updated_request' ), 'Le finaliseur structurel front doit être neutralisé pendant le save admin.' );
$assert( false !== strpos( $hotfix, 'ufsc_renewal_draft_cart_on_updated' ), 'Le handoff panier renouvellement ne doit pas tourner sur un changement de statut admin.' );
$assert( false !== strpos( $hotfix, "unset( \$_POST[ \$key ], \$_REQUEST[ \$key ] )" ), 'Les intents front transitoires doivent être retirés de la requête admin.' );
$assert( false === strpos( $hotfix, '$wpdb->update' ) && false === strpos( $hotfix, '$wpdb->delete' ) && false === strpos( $hotfix, '$wpdb->insert' ), 'Le hotfix ne doit effectuer aucune écriture SQL parallèle.' );
$assert( false !== strpos( $hotfix, 'register_shutdown_function' ), 'Un diagnostic fatal sans données personnelles doit rester disponible.' );

if ( $failures ) {
    foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
    exit( 1 );
}

echo "OK: admin licence status save hotfix static contract\n";
