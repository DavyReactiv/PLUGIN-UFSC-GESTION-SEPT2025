<?php
$root = dirname( __DIR__ );
$admin  = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$layout = file_get_contents( $root . '/includes/admin/class-ffst-club-admin-layout.php' );
$profile = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-fields.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$core = file_get_contents( $root . '/includes/core/class-sql.php' );
$guard = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-schema-guard.php' );

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
    false !== strpos( $core, "'tresorier_ville'=>array('Trésorier – Ville','text')" ),
    'La ville du trésorier fait partie du registre canonique UFSC_SQL.'
);
$assert(
    false !== strpos( $guard, "add_action( 'admin_init', array( __CLASS__, 'repair_missing_columns' ), 1 )" ),
    'Le schéma dirigeants est revérifié sur admin_init avant le rendu.'
);
$assert(
    false !== strpos( $guard, 'ufsc_flush_table_columns_cache( $table )' ),
    'La réparation vide le cache de colonnes de la table exacte, transient compris.'
);
$assert(
    false !== stripos( $guard, 'row size too large' ) &&
    false !== strpos( $guard, 'text NULL' ),
    'La migration sait retenter en TEXT uniquement en cas de limite de taille de ligne.'
);
$filter_pos = strpos( $guard, 'public static function filter_unavailable_fields' );
$repair_pos = false !== $filter_pos ? strpos( $guard, 'self::repair_missing_columns();', $filter_pos ) : false;
$known_pos = false !== $filter_pos ? strpos( $guard, '$known  = self::actual_columns( $table );', $filter_pos ) : false;
$assert(
    false !== $repair_pos && false !== $known_pos && $repair_pos < $known_pos,
    'Le filtre des champs tente la réparation SQL avant de masquer un champ indisponible.'
);

$assert(
    false === strpos( $profile, "get_option( self::SCHEMA_OPTION, '' ) || ! class_exists( 'UFSC_SQL' )" ),
    'Une ancienne option de schéma ne doit pas empêcher la réparation des nouvelles colonnes dirigeants.'
);
$assert(
    false !== strpos( $profile, "\$defs[ \$prefix . '_ville' ]" ),
    'Le schéma additif vérifie la colonne ville pour chaque dirigeant.'
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
