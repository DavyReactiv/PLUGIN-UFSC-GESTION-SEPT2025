<?php
$root   = dirname( __DIR__ );
$labels = file_get_contents( $root . '/inc/common/affiliation-status-labels-fr.php' );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, "FAIL: {$message}\n" );
		exit( 1 );
	}
};

$assert( false !== strpos( $flags, 'affiliation-status-labels-fr.php' ), 'French affiliation labels must be loaded' );
$assert( false !== strpos( $labels, "'pending_payment': 'Règlement à vérifier'" ), 'admin pending_payment must be displayed in French' );
$assert( false !== strpos( $labels, "'pending_validation': 'Règlement validé — affiliation à valider'" ), 'admin pending_validation must be displayed in French' );
$assert( false !== strpos( $labels, "'Paiement en attente'       => 'Règlement transmis — vérification UFSC en cours'" ), 'front pending payment wording must be explicit' );
$assert( false !== strpos( $labels, "'En attente de validation'  => 'Règlement validé — affiliation à valider'" ), 'front validation wording must be explicit' );
$assert( false === strpos( $labels, 'UPDATE ' ), 'presentation labels must not mutate database state' );
$assert( false === strpos( $labels, 'DELETE ' ), 'presentation labels must not delete data' );

echo "French affiliation status labels safeguards OK\n";
