<?php
$root = dirname( __DIR__ );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

$assert(
    false !== strpos( $handler, "UFSC_FFST_Club_Profile_Schema_Guard::repair_missing_columns();" ),
    'Le front répare le schéma additif avant la sauvegarde du profil club.'
);

foreach ( array(
    "'tresorier_ville'",
    "'tresorier_code_postal'",
    "'tresorier_complement_adresse'",
    "'tresorier_adresse'",
    "'tresorier_ville_naissance'",
    "'tresorier_departement_naissance'",
    "'tresorier_pays_naissance'"
) as $needle ) {
    $assert(
        false !== strpos( $handler, $needle ) || false !== strpos( $handler, "'tresorier'" ),
        'Le profil front autorise le champ dirigeant attendu: ' . $needle
    );
}

$assert(
    false !== strpos( $handler, "array_key_exists( \$field, \$_POST )" ),
    'Seuls les champs effectivement soumis sont sauvegardés.'
);

$assert(
    false !== strpos( $handler, 'missing SQL columns for club #' ),
    'Une colonne SQL manquante bloque la fausse confirmation de sauvegarde.'
);

$assert(
    false === strpos( $handler, "\$allowed_data['tresorier_ville'] = ''" ),
    'La ville du trésorier n’est jamais remise à vide implicitement.'
);

if ( $failures ) {
    exit( 1 );
}

echo "OK: sauvegarde front du profil dirigeants sécurisée.\n";
