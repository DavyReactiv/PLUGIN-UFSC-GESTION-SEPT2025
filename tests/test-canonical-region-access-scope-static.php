<?php
$root = dirname( __DIR__ );
$permissions = file_get_contents( $root . '/includes/permissions/class-ufsc-permissions.php' );
$scope       = file_get_contents( $root . '/includes/security/class-ufsc-scope.php' );
$clubs       = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $permissions, "'DROM-COM UFSC'" ), 'canonical permission regions must include DROM-COM UFSC' );
$assert( false !== strpos( $permissions, "'guadeloupe'                => 'DROM-COM UFSC'" ), 'Guadeloupe legacy access must map to DROM-COM UFSC' );
$assert( false !== strpos( $permissions, "'martinique'                => 'DROM-COM UFSC'" ), 'Martinique legacy access must map to DROM-COM UFSC' );
$assert( false !== strpos( $permissions, "'guyane'                    => 'DROM-COM UFSC'" ), 'Guyane legacy access must map to DROM-COM UFSC' );
$assert( false !== strpos( $permissions, "'la-reunion'                => 'DROM-COM UFSC'" ), 'La Reunion legacy access must map to DROM-COM UFSC' );
$assert( false !== strpos( $permissions, "'mayotte'                   => 'DROM-COM UFSC'" ), 'Mayotte legacy access must map to DROM-COM UFSC' );
$assert( false !== strpos( $permissions, 'ufsc_expand_region_access_values' ), 'region query compatibility helper must exist' );
$assert( false !== strpos( $scope, 'ufsc_expand_region_access_values( $regions )' ), 'scope queries must expand legacy region aliases' );
$assert( false !== strpos( $clubs, 'ufsc_expand_region_access_values( $allowed_regions )' ), 'club list regional filter must use compatible region aliases' );

$assert( false === strpos( $permissions, 'UPDATE ' ), 'permission-region compatibility must not run SQL data migrations' );
$assert( false === strpos( $scope, 'UPDATE ' ), 'scope compatibility must not mutate club/licence tables' );
$assert( false === strpos( $permissions, 'WC()->cart' ), 'region scope fix must not touch WooCommerce cart' );
$assert( false === strpos( $scope, 'WC()->cart' ), 'region scope fix must not touch WooCommerce cart' );

echo "Canonical regional access scope safeguards OK\n";
