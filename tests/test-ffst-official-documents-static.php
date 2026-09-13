<?php
$root   = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-ffst-official-template-admin.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

$failed = false;
$assert = static function( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failed = true;
    }
};

$assert( false !== strpos( $loader, 'class-ffst-official-template-admin.php' ), 'Le module du modèle officiel FFST est chargé côté admin.' );
$assert( false !== strpos( $loader, 'UFSC_FFST_Official_Template_Admin::init()' ), 'Le générateur officiel FFST est initialisé.' );
$assert( false === strpos( $loader, 'UFSC_FFST_Official_Documents_Admin::init()' ), 'L’ancien générateur Word maison ne doit plus être initialisé.' );
$assert( false !== strpos( $module, "admin_post_' . self::AFFILIATION_ACTION" ), 'La génération officielle passe par admin-post.' );
$assert( false !== strpos( $module, 'check_admin_referer' ), 'La génération vérifie un nonce WordPress.' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_GESTION_MANAGE' ), 'La génération exige la capacité UFSC de gestion.' );
$assert( false !== strpos( $module, 'ZipArchive' ) && false !== strpos( $module, 'word/document.xml' ), 'Le modèle DOCX officiel est prérempli sans reconstruire le document.' );
$assert( false !== strpos( $module, '01-AFFIL-REAFFIL-FFST-26-27.docx' ), 'Le modèle d’affiliation FFST 2026-2027 est explicitement utilisé.' );
$assert( false !== strpos( $module, 'Aucun document maison n’est généré.' ), 'L’interface interdit explicitement le fallback vers un document maison.' );
$assert( false !== strpos( $module, 'max-width:1280px' ), 'Le pack transmission est limité à une largeur cohérente avec le dossier admin.' );
$assert( false !== strpos( $module, 'get_club' ) && false !== strpos( $module, 'get_licences' ), 'Le préremplissage lit les données UFSC existantes.' );
$assert( false !== strpos( $module, 'president' ) && false !== strpos( $module, 'secretaire' ) && false !== strpos( $module, 'tresorier' ), 'Les trois dirigeants obligatoires sont recherchés.' );
$assert( false !== strpos( $module, 'À compléter' ), 'Les données absentes restent signalées au lieu d’être inventées.' );

foreach ( array( 'DELETE FROM', 'TRUNCATE ', 'DROP TABLE', 'ALTER TABLE', 'UPDATE `', 'INSERT INTO', 'wc_create_order', 'empty_cart(' ) as $forbidden ) {
    $assert( false === stripos( $module, $forbidden ), "Opération destructive interdite détectée : {$forbidden}" );
}

if ( $failed ) {
    exit( 1 );
}

echo "OK: modèles officiels FFST préremplis sans régression métier.\n";
