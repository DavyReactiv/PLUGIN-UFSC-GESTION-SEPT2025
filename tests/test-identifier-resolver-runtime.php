<?php
/** Runtime-free contract tests for identifier classification. */
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', dirname( __DIR__ ) . '/' ); }
require_once dirname( __DIR__ ) . '/includes/core/class-ufsc-identifier-resolver.php';

$row = array(
    'numero_licence_ufsc'        => 'UFSC-L-000042',
    'numero_licence_asptt'       => '',
    'numero_licence_delegataire' => 'LEGACY-42',
    'source_licence_number'      => 'SOURCE-42',
);
if ( 'UFSC-L-000042' !== UFSC_Identifier_Resolver::read( $row, 'licence_ufsc' ) ) { exit( 1 ); }
if ( '' !== UFSC_Identifier_Resolver::read( $row, 'licence_asptt' ) ) { fwrite( STDERR, "An ambiguous alias was reclassified as ASPTT.\n" ); exit( 1 ); }
$ambiguous = UFSC_Identifier_Resolver::read_ambiguous( $row, 'licence' );
if ( 'LEGACY-42' !== ( $ambiguous['numero_licence_delegataire'] ?? '' ) || 'SOURCE-42' !== ( $ambiguous['source_licence_number'] ?? '' ) ) { exit( 1 ); }
echo "Identifier resolver runtime safeguards passed.\n";
