<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-ffst-official-documents-admin.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failed = false;
$assert = static function( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failed = true;
    }
};

$assert( false !== strpos( $loader, 'class-ffst-official-documents-admin.php' ), 'Le pack FFST officiel est chargé uniquement dans le contexte admin.' );
$assert( false !== strpos( $loader, 'UFSC_FFST_Official_Documents_Admin::init()' ), 'Le pack FFST est initialisé.' );
$assert( false !== strpos( $module, "admin_post_' . self::AFFILIATION_ACTION" ), 'La génération Word passe par admin-post.' );
$assert( false !== strpos( $module, 'check_admin_referer' ), 'La génération vérifie un nonce WordPress.' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_GESTION_MANAGE' ), 'La génération exige la capacité UFSC de gestion.' );
$assert( false !== strpos( $module, 'application/msword' ), 'L’affiliation est téléchargée en document Word.' );
$assert( false !== strpos( $module, "self::LICENCES_ACTION" ), 'Le pack réutilise le générateur dirigeants existant au lieu de dupliquer son métier.' );
$assert( false !== strpos( $module, 'UFSC_Storage_Resolver::get_clubs_table()' ), 'La lecture du club utilise le résolveur de stockage canonique.' );
$assert( false !== strpos( $module, 'SELECT *' ), 'Le pack lit la fiche club existante sans migration.' );

foreach ( array( 'DELETE FROM', 'TRUNCATE ', 'DROP TABLE', 'ALTER TABLE', 'UPDATE `', 'INSERT INTO', 'wc_create_order', 'empty_cart(' ) as $forbidden ) {
    $assert( false === stripos( $module, $forbidden ), "Opération destructive interdite détectée : {$forbidden}" );
}

$assert( false !== strpos( $module, 'Aucune fiche club, licence, commande ou saison n’est modifiée.' ), 'L’interface annonce explicitement le caractère non destructif.' );
$assert( false !== strpos( $module, 'president' ) && false !== strpos( $module, 'secretaire' ) && false !== strpos( $module, 'tresorier' ), 'Les trois dirigeants obligatoires sont inclus.' );
$assert( false !== strpos( $module, 'À compléter' ), 'Les données manquantes restent visibles au lieu d’être inventées.' );

if ( $failed ) {
    exit( 1 );
}

echo "OK: pack FFST officiel sécurisé et non destructif.\n";
