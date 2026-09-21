<?php
$root = dirname( __DIR__ );
$admin  = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$layout = file_get_contents( $root . '/includes/admin/class-ffst-club-admin-layout.php' );
$profile = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-fields.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );

$failures = 0;
$assert = static function( $condition, $message ) use ( &$failures ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        $failures++;
    }
};

foreach ( array( 'president', 'secretaire', 'tresorier' ) as $prefix ) {
    foreach ( array( 'adresse', 'complement_adresse', 'code_postal', 'ville' ) as $suffix ) {
        $field = $prefix . '_' . $suffix;
        $assert(
            false !== strpos( $admin, "'" . $field . "'" ),
            'La section admin Dirigeants expose ' . $field . '.'
        );
    }
}

$assert(
    false !== strpos( $admin, "'tresorier_ville'" ),
    'La ville du trésorier est explicitement rendue dans la fiche club admin.'
);

$assert(
    false !== strpos( $admin, 'if (array_key_exists($k, $_POST))' ),
    'Le handler admin ne sauvegarde que les champs réellement soumis.'
);
$assert(
    false !== strpos( $profile, 'array_key_exists( $field, $_POST )' ),
    'Le complément FFST ne remplace jamais une valeur pour un champ absent du POST.'
);
$assert(
    false !== strpos( $profile, "'tresorier_ville'" ),
    'Le stockage canonique FFST prévoit la ville du trésorier.'
);

$assert(
    false !== strpos( $layout, "grid-template-columns:repeat(3,minmax(0,1fr))" ),
    'Les cartes dirigeants utilisent une grille desktop compacte.'
);
$assert(
    false !== strpos( $layout, "Identité & contact" )
    && false !== strpos( $layout, "Naissance" )
    && false !== strpos( $layout, "Adresse" ),
    'Les cartes dirigeants sont regroupées en sections lisibles.'
);
$assert(
    false !== strpos( $layout, '@media(max-width:1100px)' )
    && false !== strpos( $layout, '@media(max-width:782px)' ),
    'La présentation dirigeants reste responsive.'
);

$assert(
    false !== strpos( $front, "\$prefix . '_complement_adresse'" )
    && false !== strpos( $front, "\$prefix . '_code_postal'" )
    && false !== strpos( $front, "\$prefix . '_ville'" ),
    'Le Compte Club front conserve l’adresse détaillée des dirigeants, y compris la ville.'
);

$assert(
    false === stripos( $profile, 'DROP COLUMN' )
    && false === stripos( $profile, 'RENAME COLUMN' ),
    'Le correctif ne contient aucune migration destructive des champs dirigeants.'
);

if ( $failures ) {
    exit( 1 );
}

echo "OK: intégrité et présentation des dirigeants sécurisées.\n";
