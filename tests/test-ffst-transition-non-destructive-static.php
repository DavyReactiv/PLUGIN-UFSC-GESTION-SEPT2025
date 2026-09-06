<?php
$root = dirname( __DIR__ );
$migration = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$sql = file_get_contents( $root . '/includes/core/class-sql.php' );
$sanitizer = file_get_contents( $root . '/inc/form-license-sanitizer.php' );
$renewal = file_get_contents( $root . '/includes/core/class-ufsc-renewal-service.php' );
$template = file_get_contents( $root . '/templates/frontend/licence-form.php' );

$assert = static function ( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $migration, 'ensure_ffst_schema' ), 'FFST schema has an idempotent additive migration.' );
$assert( false !== strpos( $migration, 'ufsc_ffst_schema_ready' ), 'FFST schema readiness is tracked independently.' );
$assert( false === strpos( $migration, 'DROP COLUMN' ) && false === strpos( $migration, 'RENAME COLUMN' ), 'No destructive column migration exists.' );
$assert( false === strpos( $migration, 'UPDATE `{$settings' ), 'Migration does not bulk rewrite existing licence/club data.' );

$assert( false !== strpos( $sql, "'numero_affiliation_ffst'=>array('N° affiliation FFST','text')" ), 'Current club model exposes FFST affiliation identifier.' );
$assert( false !== strpos( $sql, "'numero_licence_ffst'=>array('N° licence FFST','text')" ), 'Current licence model exposes FFST licence identifier.' );
$assert( false !== strpos( $sql, "'infos_ffst'=>array('Infos FFST','bool')" ), 'Current licence model supports FFST consent.' );
$assert( false !== strpos( $sql, "unset( \$fields[\$legacy_field] )" ), 'Historical partner fields are removed from the active write/UI whitelist.' );

$assert( false === strpos( $sanitizer, "'infos_asptt'" ) && false === strpos( $sanitizer, "'infos_fsasptt'" ), 'Current sanitizer does not synthesize old partner consent values.' );
$assert( false === strpos( $sanitizer, "'reduction_postier'" ) && false === strpos( $sanitizer, "'identifiant_laposte'" ), 'Current sanitizer does not overwrite Postier/La Poste history.' );
$assert( false !== strpos( $sanitizer, "'infos_ffst'" ), 'Current sanitizer accepts FFST consent.' );

$assert( false !== strpos( $renewal, 'legacy_partner_fields' ), 'Renewal has an explicit historical partner blocklist.' );
$assert( false !== strpos( $renewal, "'infos_ffst'" ), 'Renewal uses FFST consent for the target season.' );
$assert( false === strpos( $renewal, "'numero_licence_ffst' ) { \$payload" ), 'Renewal does not auto-copy a FFST licence number into the next season.' );

$assert( false !== strpos( $template, 'Recevoir les informations FFST' ), 'Licence form uses FFST wording.' );
$assert( false === stripos( $template, 'ASPTT' ) && false === stripos( $template, 'FSASPTT' ), 'Licence form no longer exposes ASPTT/FSASPTT.' );
$assert( false === stripos( $template, 'Réduction postier' ) && false === stripos( $template, 'Identifiant La Poste' ), 'Licence form no longer exposes historical Postier/La Poste controls.' );
