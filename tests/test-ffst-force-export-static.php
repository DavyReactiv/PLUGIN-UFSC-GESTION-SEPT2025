<?php
$root = dirname( __DIR__ );
$export = file_get_contents( $root . '/includes/admin/class-ffst-export-admin.php' );
$screen = file_get_contents( $root . '/includes/admin/class-ffst-documents-admin.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failures = array();
$assert = static function ( $ok, $message ) use ( &$failures ) {
    if ( ! $ok ) { $failures[] = $message; echo "FAIL: {$message}\n"; return; }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $loader, "class-ffst-export-admin.php" ), 'Le générateur FFST est chargé uniquement depuis le module admin existant.' );
$assert( false !== strpos( $screen, 'UFSC_FFST_Export_Admin::render_actions' ), 'L’écran FFST délègue à un unique générateur.' );
$assert( false === strpos( $screen, 'Générer le dossier FFST (à brancher)' ), 'Le bouton factice bloquant a disparu.' );
$assert( false === strpos( $screen, "\$disabled = \$readiness['percent'] < 100" ), 'La complétude ne désactive plus la génération administrateur.' );
$assert( false !== strpos( $export, 'admin_post_ufsc_ffst_generate_affiliation' ) && false !== strpos( $export, 'admin_post_ufsc_ffst_generate_licences' ), 'Deux actions séparées existent pour affiliation et licences.' );
$assert( false !== strpos( $export, 'Génération forcée autorisée' ), 'Le dossier incomplet est signalé sans bloquer.' );
$assert( false !== strpos( $export, "UFSC_Permissions::CAP_GESTION_MANAGE" ) && substr_count( $export, 'check_admin_referer' ) >= 1, 'Les actions restent réservées aux administrateurs autorisés et protégées par nonce.' );
$assert( false !== strpos( $export, "array( 'season', 'saison', 'paid_season', 'season_end_year' )" ), 'L’export isole explicitement la saison demandée.' );
$assert( false !== strpos( $export, 'numero_affiliation_ffst' ) && false !== strpos( $export, "licence_ffst" ), 'Les champs FFST sont utilisés lorsqu’ils existent.' );
$assert( false === stripos( $export, 'asptt' ) && false === stripos( $export, 'fsasptt' ), 'Aucune donnée ASPTT/FSASPTT n’est requalifiée dans les exports FFST.' );
$assert( false === strpos( $export, '$wpdb->update' ) && false === strpos( $export, '$wpdb->insert' ) && false === strpos( $export, '$wpdb->delete' ), 'La génération ne modifie aucune ligne club ou licence.' );
$assert( false !== strpos( $export, 'ufsc_audit_log' ) && false !== strpos( $export, 'HISTORY_OPTION_PREFIX' ), 'Chaque génération laisse une trace interne sans migration destructive.' );
$assert( false !== strpos( $export, 'Contrôle UFSC' ) && false !== strpos( $export, 'À compléter' ), 'Le fichier licences contient un contrôle explicite des données manquantes.' );
$assert( false !== strpos( $export, 'LEADERS_TEMPLATE_URL' ) && false !== strpos( $export, 'local_upload_path_from_url' ), 'Le modèle officiel licences est utilisé localement lorsqu’il est disponible.' );

if ( $failures ) { exit( 1 ); }
echo "FFST forced export safeguards OK\n";