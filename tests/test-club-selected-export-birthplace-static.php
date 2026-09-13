<?php
$root = dirname( __DIR__ );
$export = file_get_contents( $root . '/includes/admin/class-clubs-selected-export.php' );
$birth = file_get_contents( $root . '/includes/admin/class-ffst-birthplace-fields.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failed = false;
$assert = static function( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); $failed = true; }
};

$assert( false !== strpos( $loader, 'class-clubs-selected-export.php' ), 'Le module export sélection est chargé.' );
$assert( false !== strpos( $loader, 'UFSC_Clubs_Selected_Export::init()' ), 'Le module export sélection est initialisé.' );
$assert( false !== strpos( $export, 'input[name="club_ids[]"]:checked' ), 'Les cases cochées pilotent l’export.' );
$assert( false !== strpos( $export, 'Sélectionnez au moins un club' ), 'Un export vide est bloqué au lieu d’exporter tous les clubs.' );
$assert( false !== strpos( $export, "wp_verify_nonce" ), 'L’export sélectionné vérifie un nonce.' );
$assert( false !== strpos( $export, "id IN" ), 'La requête export est limitée aux identifiants sélectionnés.' );
$assert( false !== strpos( $birth, "array( 'new', 'edit', 'view' )" ), 'Les champs FFST sont disponibles à la création, à la modification et à la consultation admin.' );
$assert( false !== strpos( $birth, 'wp_footer' ), 'Les champs FFST sont aussi disponibles dans le compte club en front.' );
foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur' ) as $prefix ) {
    $assert( false !== strpos( $birth, $prefix ), "Le rôle {$prefix} est pris en charge." );
}
foreach ( array( 'date_naissance', 'ville_naissance', 'departement_naissance', 'pays_naissance' ) as $field ) {
    $assert( false !== strpos( $birth, $field ), "Le champ {$field} est pris en charge." );
}
$assert( false === stripos( $birth, 'DROP TABLE' ) && false === stripos( $birth, 'TRUNCATE ' ), 'Aucune opération destructive sur le schéma.' );

if ( $failed ) { exit( 1 ); }
echo "OK: export des clubs cochés et champs naissance FFST sécurisés.\n";
