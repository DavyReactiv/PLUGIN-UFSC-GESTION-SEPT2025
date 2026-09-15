<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-licences-canonical-export.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failed = false;
$assert = static function ( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failed = true;
    }
};

foreach ( array( 'role', 'ville_naissance', 'departement_naissance', 'pays_naissance', 'numero_licence_ffst', 'numero_licence', 'season', 'age_export', 'moins_12_ans' ) as $field ) {
    $assert( false !== strpos( $module, "'{$field}'" ), "canonical export field {$field} is present" );
}

$assert( false !== strpos( $module, "'age_export'            => 'Âge au jour de l’export'" ), 'export exposes age at export date' );
$assert( false !== strpos( $module, "'moins_12_ans'          => 'Moins de 12 ans'" ), 'export exposes under-12 marker' );
$assert( false !== strpos( $module, "self::age_from_birthdate( \$row['date_naissance'] ?? '' )" ), 'age derives only from stored birth date' );
$assert( false !== strpos( $module, "\$age < 12 ? 'Oui' : 'Non'" ), 'under-12 rule is strictly age below 12' );
$assert( false !== strpos( $module, "current_time( 'Y-m-d' )" ), 'age uses the local WordPress export date' );
$assert( false !== strpos( $module, 'checkdate(' ), 'invalid birth dates are rejected' );
$assert( false !== strpos( $module, 'if ( $birth > $today ) { return null; }' ), 'future birth dates are not classified' );

$assert( false !== strpos( $module, "'infos_fsasptt'" ), 'legacy FSASPTT field is explicitly hidden from the current export UI' );
$assert( false !== strpos( $module, "'infos_asptt'" ), 'legacy ASPTT field is explicitly hidden from the current export UI' );
$assert( false !== strpos( $module, "add_action( 'admin_post_ufsc_export_data'" ), 'canonical export handles the existing export action' );
$assert( false !== strpos( $module, "check_admin_referer( 'ufsc_export_data' )" ), 'canonical export preserves nonce protection' );
$assert( false !== strpos( $module, "UFSC_Scope::get_user_scope_region()" ), 'canonical export preserves regional scope' );
$assert( false !== strpos( $loader, "class-licences-canonical-export.php" ), 'canonical exporter is loaded' );
$assert( false !== strpos( $loader, 'UFSC_Licences_Canonical_Export::init();' ), 'canonical exporter is initialized' );

// Export must remain read-only.
$assert( 0 === preg_match( '/\$wpdb\s*->\s*(insert|update|delete|replace)\s*\(/i', $module ), 'export performs no database row mutation' );
$assert( false === stripos( $module, 'DELETE FROM' ), 'export contains no DELETE statement' );
$assert( false === stripos( $module, 'TRUNCATE' ), 'export contains no TRUNCATE statement' );
$assert( false === stripos( $module, 'ALTER TABLE' ), 'export contains no schema mutation' );

if ( $failed ) { exit( 1 ); }
echo "OK canonical licence export\n";
