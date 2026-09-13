<?php
$root        = dirname( __DIR__ );
$module      = file_get_contents( $root . '/includes/admin/class-ffst-official-template-admin.php' );
$birthfields = file_get_contents( $root . '/includes/admin/class-ffst-birthplace-fields.php' );
$licenceform = file_get_contents( $root . '/templates/frontend/licence-form.php' );
$loader      = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );

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
$assert( false !== strpos( $loader, 'class-ffst-birthplace-fields.php' ), 'Les champs de naissance FFST sont chargés.' );
$assert( false !== strpos( $loader, 'UFSC_FFST_Birthplace_Fields::init()' ), 'Les champs de naissance FFST sont initialisés.' );

$assert( false !== strpos( $module, "admin_post_' . self::AFFILIATION_ACTION" ), 'La génération officielle passe par admin-post.' );
$assert( false !== strpos( $module, 'check_admin_referer' ), 'La génération vérifie un nonce WordPress.' );
$assert( false !== strpos( $module, 'UFSC_Permissions::CAP_GESTION_MANAGE' ), 'La génération exige la capacité UFSC de gestion.' );
$assert( false !== strpos( $module, 'ZipArchive' ) && false !== strpos( $module, 'word/document.xml' ), 'Le modèle DOCX officiel est prérempli sans reconstruire le document.' );
$assert( false !== strpos( $module, '01-AFFIL-REAFFIL-FFST-26-27.docx' ), 'Le modèle d’affiliation FFST 2026-2027 est explicitement utilisé.' );
$assert( false !== strpos( $module, 'Aucun document maison n’est généré.' ), 'L’interface interdit explicitement le fallback vers un document maison.' );
$assert( false !== strpos( $module, 'max-width:1280px' ), 'Le pack transmission est limité à une largeur cohérente avec le dossier admin.' );
$assert( false !== strpos( $module, 'get_club' ) && false !== strpos( $module, 'get_licences' ), 'Le préremplissage lit les données UFSC existantes.' );
$assert( false !== strpos( $module, 'president' ) && false !== strpos( $module, 'secretaire' ) && false !== strpos( $module, 'tresorier' ), 'Les trois dirigeants obligatoires sont recherchés.' );
$assert( false !== strpos( $module, "'date_naissance'" ), 'La date de naissance est injectée dans le modèle officiel.' );
$assert( false !== strpos( $module, "'ville_naissance'" ), 'La ville de naissance est injectée dans le modèle officiel.' );
$assert( false !== strpos( $module, "'departement_naissance'" ), 'Le département de naissance est injecté dans le modèle officiel.' );
$assert( false !== strpos( $module, "'pays_naissance'" ), 'Le pays de naissance est disponible comme alternative FFST.' );
$assert( false !== strpos( $module, 'Ville + Dept (ou Pays)' ), 'Le libellé officiel de lieu de naissance est conservé.' );
$assert( false !== strpos( $module, 'À compléter' ), 'Les données absentes restent signalées au lieu d’être inventées.' );

$assert( false !== strpos( $birthfields, 'president_ville_naissance' ) && false !== strpos( $birthfields, 'secretaire_ville_naissance' ) && false !== strpos( $birthfields, 'tresorier_ville_naissance' ), 'Les dirigeants du club disposent des champs de lieu de naissance.' );
$assert( false !== strpos( $birthfields, 'entraineur_date_naissance' ) && false !== strpos( $birthfields, 'entraineur_ville_naissance' ), 'L’entraîneur/instructeur dispose de la date et du lieu de naissance.' );
$assert( false !== strpos( $birthfields, "'ville_naissance'" ) && false !== strpos( $birthfields, "'departement_naissance'" ) && false !== strpos( $birthfields, "'pays_naissance'" ), 'Les licences disposent des champs naissance FFST.' );
$assert( false !== strpos( $licenceform, 'ufsc-ffst-birthplace-field' ), 'Le formulaire licence affiche les champs FFST pour les rôles dirigeants.' );
$assert( false !== strpos( $licenceform, "['president','secretaire','tresorier','entraineur'" ), 'Les champs de lieu de naissance deviennent obligatoires pour les rôles dirigeants.' );

foreach ( array( 'DELETE FROM', 'TRUNCATE ', 'DROP TABLE', 'UPDATE `', 'INSERT INTO', 'wc_create_order', 'empty_cart(' ) as $forbidden ) {
    $assert( false === stripos( $module, $forbidden ), "Opération destructive interdite détectée dans le générateur : {$forbidden}" );
    $assert( false === stripos( $birthfields, $forbidden ), "Opération destructive interdite détectée dans les champs naissance : {$forbidden}" );
}
$assert( false !== stripos( $birthfields, 'ALTER TABLE' ) && false !== stripos( $birthfields, 'ADD COLUMN' ), 'La migration naissance est uniquement additive.' );
$assert( false === stripos( $birthfields, 'DROP COLUMN' ) && false === stripos( $birthfields, 'CHANGE COLUMN' ) && false === stripos( $birthfields, 'RENAME COLUMN' ), 'Aucune migration destructive des colonnes existantes.' );

if ( $failed ) {
    exit( 1 );
}

echo "OK: modèles officiels FFST et champs naissance dirigeants sécurisés.\n";
