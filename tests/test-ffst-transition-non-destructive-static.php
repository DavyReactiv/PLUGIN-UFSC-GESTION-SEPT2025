<?php
$root = dirname( __DIR__ );
$migration = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$sql = file_get_contents( $root . '/includes/core/class-sql.php' );
$sanitizer = file_get_contents( $root . '/inc/form-license-sanitizer.php' );
$renewal = file_get_contents( $root . '/includes/core/class-ufsc-renewal-service.php' );
$template = file_get_contents( $root . '/templates/frontend/licence-form.php' );
$identifiers = file_get_contents( $root . '/includes/core/class-ufsc-identifier-service.php' );

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
$assert( false !== strpos( $sql, 'unset( $fields[ $legacy_field ] );' ), 'Historical partner fields are removed from the active write/UI whitelist.' );

$assert( false === strpos( $sanitizer, "'infos_asptt'" ) && false === strpos( $sanitizer, "'infos_fsasptt'" ), 'Current sanitizer does not synthesize old partner consent values.' );
$assert( false === strpos( $sanitizer, "'reduction_postier'" ) && false === strpos( $sanitizer, "'identifiant_laposte'" ), 'Current sanitizer does not overwrite Postier/La Poste history.' );
$assert( false !== strpos( $sanitizer, "'infos_ffst'" ), 'Current sanitizer accepts FFST consent.' );

$assert( false !== strpos( $renewal, 'legacy_partner_fields' ), 'Renewal has an explicit historical partner blocklist.' );
$assert( false !== strpos( $renewal, "'infos_ffst'" ), 'Renewal uses FFST consent for the target season.' );
$payload_start = strpos( $renewal, 'public static function renewal_payload' );
$payload_end = strpos( $renewal, 'public static function create_target_draft', $payload_start );
$payload = false !== $payload_start && false !== $payload_end ? substr( $renewal, $payload_start, $payload_end - $payload_start ) : '';
$assert( '' !== $payload && false === strpos( $payload, "'numero_licence_ffst'" ), 'Renewal does not auto-copy a FFST licence number into the next season.' );
$assert( false !== strpos( $renewal, "'numero_licence_asptt'" ) && false !== strpos( $renewal, 'if ( in_array( $field, $legacy_fields, true ) ) { continue; }' ), 'Historical partner identifiers are explicitly blocked from renewal copy.' );

$assert( false !== strpos( $identifiers, 'save_ffst' ), 'FFST identifiers have a dedicated save service.' );
$assert( false !== strpos( $identifiers, "admin_post_ufsc_save_ffst_identifier" ) && false !== strpos( $identifiers, 'handle_ffst_request' ), 'FFST administrator save endpoint is registered and connected to the guarded handler.' );
$assert( false !== strpos( $identifiers, 'check_admin_referer' ) && false !== strpos( $identifiers, "'POST' !== strtoupper" ), 'FFST identifier administration remains protected by POST, capability and nonce checks.' );

$assert( false !== strpos( $template, 'Recevoir les informations FFST' ), 'Licence form uses FFST wording.' );
$assert( false === stripos( $template, 'ASPTT' ) && false === stripos( $template, 'FSASPTT' ), 'Licence form no longer exposes ASPTT/FSASPTT.' );
$assert( false === stripos( $template, 'Réduction postier' ) && false === stripos( $template, 'Identifiant La Poste' ), 'Licence form no longer exposes historical Postier/La Poste controls.' );
