<?php
$root = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-fields.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert( false !== strpos( $module, 'final class UFSC_FFST_Club_Profile_Fields' ), 'Le module profil club FFST existe.' );
$assert( false !== strpos( $module, 'ALTER TABLE' ) && false !== strpos( $module, 'ADD COLUMN' ), 'La migration FFST est additive.' );
$assert( false === stripos( $module, 'DROP COLUMN' ), 'Aucune colonne existante n’est supprimée.' );
$assert( false === stripos( $module, 'RENAME COLUMN' ), 'Aucune colonne existante n’est renommée.' );
$assert( false !== strpos( $module, "'complement_adresse_salle'" ), 'Le complément adresse de la salle est prévu.' );
$assert( false !== strpos( $module, "'president_code_postal'" ), 'Le code postal du président est prévu.' );
$assert( false !== strpos( $module, "'secretaire_code_postal'" ), 'Le code postal du secrétaire est prévu.' );
$assert( false !== strpos( $module, "'tresorier_code_postal'" ), 'Le code postal du trésorier est prévu.' );
$assert( false !== strpos( $module, "'disciplines_ffst'" ), 'Les disciplines FFST sont prévues.' );
$assert( false !== strpos( $module, "'correspondant_email'" ), 'Le correspondant FFST est prévu.' );
$assert( false !== strpos( $module, 'ne bloquent pas l’utilisation du compte' ), 'L’interface indique explicitement le caractère non bloquant.' );
$assert( false !== strpos( $module, "'ufsc_save_club' !== \$action" ), 'La sauvegarde FFST est limitée à l’action canonique du club.' );
$assert( false !== strpos( $module, "wp_verify_nonce" ) && false !== strpos( $module, "'ufsc_save_club'" ), 'La sauvegarde FFST exige le nonce du formulaire club.' );
$assert( false === strpos( $module, "add_action( 'wp_footer', array( __CLASS__, 'render_front'" ), 'L’ancien injecteur FFST n’est plus chargé globalement sur le front.' );
$assert( false !== strpos( $module, 'input[name="action"][value="ufsc_save_club"]' ), 'Le fallback admin reste limité au vrai formulaire club.' );
$assert( false === strpos( $module, "setAttribute('required'" ), 'Les nouveaux champs ne deviennent pas requis côté navigateur.' );
$assert( false !== strpos( $loader, "class-ffst-club-profile-fields.php" ), 'Le module est chargé par le plugin.' );
$assert( false !== strpos( $loader, 'UFSC_FFST_Club_Profile_Fields::init();' ), 'Le module est initialisé.' );

if ( $failures ) {
    exit( 1 );
}

echo "OK: profil club / affiliation FFST additif et non bloquant.\n";
