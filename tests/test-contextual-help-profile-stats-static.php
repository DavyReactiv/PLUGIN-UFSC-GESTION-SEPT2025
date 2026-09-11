<?php
/** Static safeguards for contextual help and profile-stat presentation. */
$root   = dirname( __DIR__ );
$flags  = file_get_contents( $root . '/inc/common/feature-flags.php' );
$module = file_get_contents( $root . '/inc/common/contextual-help.php' );
$failures = array();
$assert = static function( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$assert( false !== strpos( $flags, 'contextual-help.php' ), 'Contextual help module must be loaded.' );
$assert( false !== strpos( $module, 'Coordonnées du club modifiables ici.' ), 'Compte Club must keep a concise coordinates notice.' );
$assert( false !== strpos( $module, 'Les poids des licenciés se mettent à jour depuis l’onglet Mes licences UFSC.' ), 'Legacy full phrase must be targeted for replacement.' );
$assert( false !== strpos( $module, '.ufsc-demographic-summary{display:none!important}' ), 'Legacy demographic panel may be hidden only when replacement statistics are present.' );
$assert( false !== strpos( $module, "false !== strpos( \$output, 'ufsc-stats-v2' )" ), 'Legacy profile hiding must remain conditional on replacement statistics.' );
$assert( false !== strpos( $module, "'Mes licences UFSC'" ) && false !== strpos( $module, 'Pour modifier un poids' ), 'Weight guidance must live in licence help.' );
$assert( false !== strpos( $module, "'Ajouter une licence'" ) && false !== strpos( $module, 'brouillon sans consommer de place' ), 'Licence request flow must explain draft/quota behavior.' );
$assert( false !== strpos( $module, "'Affiliation'" ) && false !== strpos( $module, 'paiement et la validation UFSC' ), 'Affiliation help must distinguish payment and validation.' );
$assert( false !== strpos( $module, 'uniquement les licences officiellement validées' ), 'Statistics help must state that drafts and pending dossiers are excluded.' );
$assert( false !== strpos( $module, "'honor'" ) && false !== strpos( $module, "'ffst'" ) && false !== strpos( $module, "'licence'" ), 'Admin guidance must cover key UFSC tools.' );
$assert( false === strpos( $module, 'UPDATE ' ) && false === strpos( $module, 'DELETE FROM' ) && false === strpos( $module, 'ALTER TABLE' ), 'Contextual guidance must stay read-only.' );

if ( $failures ) {
    fwrite( STDERR, "Contextual help/profile safeguards failed:\n- " . implode( "\n- ", $failures ) . "\n" );
    exit( 1 );
}
echo "Contextual help/profile safeguards: OK\n";
