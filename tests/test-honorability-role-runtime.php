<?php
/** Runtime truth-table for every role currently shown by the licence form. */
define( 'ABSPATH', __DIR__ );
function sanitize_title( $value ) { return strtolower( str_replace( array( ' ', '_' ), '-', trim( $value ) ) ); }
function remove_accents( $value ) { return strtr( $value, array( 'é' => 'e', 'É' => 'E', 'î' => 'i' ) ); }
function apply_filters( $hook, $value ) { return $value; }
function __( $value ) { return $value; }
require dirname( __DIR__ ) . '/inc/common/compliance.php';

$roles = array( 'Président', 'Secrétaire', 'Trésorier', 'Dirigeant', 'Éducateur', 'Entraîneur', 'Coach', 'Encadrant', 'Responsable technique' );
foreach ( $roles as $role ) {
	if ( ! ufsc_role_requires_honorability( $role ) ) { fwrite( STDERR, "FAIL: {$role} should require honorability\n" ); exit( 1 ); }
}
foreach ( array( 'Pratiquant', 'Adhérent', 'Membre', 'Compétiteur', '', 'Arbitre' ) as $role ) { if ( ufsc_role_requires_honorability( $role ) ) { fwrite( STDERR, "FAIL: {$role} must be exempt\n" ); exit( 1 ); } }
echo "OK: centralized honorability role truth-table\n";
