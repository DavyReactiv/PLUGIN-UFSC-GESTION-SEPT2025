<?php
$root = dirname( __DIR__ );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );
$export = file_get_contents( $root . '/includes/admin/class-ffst-export-admin.php' );
$compliance = file_get_contents( $root . '/includes/admin/class-ffst-compliance-admin.php' );
$insurance = file_get_contents( $root . '/includes/admin/class-ffst-insurance-admin.php' );
$screen = file_get_contents( $root . '/includes/admin/class-ffst-documents-admin.php' );

$failures = array();
$assert = static function ( $ok, $message ) use ( &$failures ) {
    if ( ! $ok ) {
        $failures[] = $message;
        echo "FAIL: {$message}\n";
        return;
    }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $loader, 'class-ffst-export-admin.php' ), 'Le générateur FFST consolidé est chargé.' );
$assert( false !== strpos( $loader, 'class-ffst-compliance-admin.php' ), 'Le suivi FFST consolidé est chargé.' );
$assert( false !== strpos( $loader, 'class-ffst-insurance-admin.php' ), 'La préparation assurance FFST est chargée.' );
$assert( false !== strpos( $loader, 'class-ufsc-bank-transfer-admin.php' ), 'Le rapprochement bancaire déjà en production reste chargé.' );
$assert( false !== strpos( $screen, 'UFSC_FFST_Export_Admin::render_actions' ), 'L’écran Dossiers FFST utilise le vrai générateur.' );
$assert( false === strpos( $screen, 'Générer le dossier FFST (à brancher)' ), 'Le bouton factice FFST a disparu.' );

$assert( false !== strpos( $export, "array( 'season', 'saison', 'paid_season', 'season_end_year' )" ), 'Le bordereau dirigeants est isolé par saison.' );
$assert( false === strpos( $export, "\$values['numero_ffst'] ?: \$values['numero_ufsc']" ), 'Une colonne générique FFST ne retombe jamais sur un numéro UFSC.' );
$assert( false !== strpos( $export, "\$mapping['numero_generic'], \$row, \$values['numero_ffst']" ), 'Une colonne générique reçoit uniquement le numéro FFST.' );
$assert( false === stripos( $export, 'asptt' ) && false === stripos( $export, 'fsasptt' ), 'Aucune donnée ASPTT/FSASPTT n’est requalifiée dans les exports.' );
$assert( false === strpos( $export, '$wpdb->update' ) && false === strpos( $export, '$wpdb->insert' ) && false === strpos( $export, '$wpdb->delete' ), 'Les exports ne modifient aucune ligne club/licence.' );

$assert( false !== strpos( $compliance, 'CAP_GESTION_MANAGE' ) && false !== strpos( $compliance, 'check_admin_referer' ), 'Le suivi FFST est protégé par capacité et nonce.' );
$assert( false !== strpos( $compliance, 'ufsc_ffst_compliance_' ), 'Le suivi est isolé par option club + saison.' );
$assert( false !== strpos( $compliance, 'MAX_UPLOAD_BYTES' ) && false !== strpos( $compliance, 'wp_handle_upload' ), 'Les pièces signées sont limitées et passent par l’API upload WordPress.' );
$assert( false === strpos( $compliance, '$wpdb->update' ) && false === strpos( $compliance, '$wpdb->insert' ) && false === strpos( $compliance, '$wpdb->delete' ), 'Le suivi n’altère pas les tables clubs/licences.' );

$assert( false !== strpos( $insurance, "UFSC_Identifier_Resolver::read( \$licence, 'licence_ffst' )" ), 'La préparation assurance lit explicitement le numéro FFST.' );
$assert( false !== strpos( $insurance, "array( 'numero_licence_ffst' )" ), 'Le seul fallback d’identifiant assurance reste le champ FFST.' );
$assert( false === strpos( $insurance, 'numero_asptt' ) && false === strpos( $insurance, 'asptt_number' ), 'Aucun ancien numéro ASPTT n’est utilisé pour l’assurance.' );
$assert( false !== strpos( $insurance, "array( 'season', 'saison', 'paid_season', 'season_end_year' )" ), 'La préparation assurance est isolée par saison.' );
$assert( false !== strpos( $insurance, 'sans colonne saison identifiable' ), 'Sans saison fiable, l’assurance refuse de mélanger les historiques.' );
$assert( false === strpos( $insurance, '$wpdb->update' ) && false === strpos( $insurance, '$wpdb->insert' ) && false === strpos( $insurance, '$wpdb->delete' ), 'La préparation assurance est en lecture seule.' );

if ( $failures ) {
    exit( 1 );
}
echo "FFST consolidated production safeguards OK\n";