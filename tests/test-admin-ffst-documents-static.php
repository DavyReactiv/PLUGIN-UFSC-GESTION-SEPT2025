<?php
$root = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-ffst-documents-admin.php' );
$profile = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) { $failures[] = $message; }
};

$assert( false !== strpos( $profile, "'ufsc-ffst-documents'" ), 'Le sous-menu FFST doit être enregistré.' );
$assert( false !== strpos( $profile, 'UFSC_Permissions::CAP_GESTION_MANAGE' ), 'Le module FFST doit nécessiter la capacité de gestion.' );
$assert( false !== strpos( $module, 'Dossiers FFST' ), 'Le module admin FFST doit exister.' );
$assert( false !== strpos( $module, 'minimum_licences_ok' ) && false !== strpos( $module, '>= 10' ), 'Le contrôle du minimum de 10 licences FFST doit être présent.' );
foreach ( array( 'Président', 'Secrétaire', 'Trésorier', 'Entraîneur / instructeur' ) as $role ) {
    $assert( false !== strpos( $module, $role ), 'Le rôle FFST obligatoire doit être contrôlé : ' . $role );
}
$assert( false !== strpos( $module, 'Aucune donnée ni document FFST n’est exposé dans l’espace du représentant du club.' ), 'Le caractère strictement admin du module doit être explicite.' );
$assert( false === strpos( $module, 'add_shortcode' ), 'Le module FFST ne doit exposer aucun shortcode front.' );

if ( $failures ) {
    foreach ( $failures as $failure ) { fwrite( STDERR, "FAIL: {$failure}\n" ); }
    exit( 1 );
}

echo "Admin FFST documents static checks passed.\n";
