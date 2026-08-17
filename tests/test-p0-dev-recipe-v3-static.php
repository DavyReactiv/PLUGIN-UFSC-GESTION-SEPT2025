<?php
$root = dirname( __DIR__ );
$php = file_get_contents( $root . '/inc/common/p0-dev-recipe-v3.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-p0-dev-recipe-v3.css' );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );

$checks = array(
    'v3 loaded' => false !== strpos( $flags, 'p0-dev-recipe-v3.php' ),
    'very late shortcode authority' => false !== strpos( $php, "'do_shortcode_tag', 'ufsc_p0v3_shortcode_output', 999" ),
    'included decision required' => false !== strpos( $php, 'empty( $decision[\'included\'] )' ),
    'legacy cart form removed' => false !== strpos( $php, 'Ajouter\\s+au\\s+panier' ),
    'payment wording corrected' => false !== strpos( $php, 'Vérification obligatoire avant envoi' ),
    'included visual state' => false !== strpos( $css, '.ufsc-p0-licence-decision--included' ),
    'logo enlarged' => false !== strpos( $css, 'width: 180px' ) && false !== strpos( $css, 'height: 180px' ),
    'logo remove secondary' => false !== strpos( $css, '.ufsc-remove-photo' ) && false !== strpos( $css, '#b91c1c' ),
    'quota readability' => false !== strpos( $css, '.ufsc-pack-office' ) && false !== strpos( $css, 'color: #fff' ),
    'kpi underline removed' => false !== strpos( $css, 'text-decoration: none' ),
);

foreach ( $checks as $label => $ok ) {
    if ( ! $ok ) {
        fwrite( STDERR, "FAIL: {$label}\n" );
        exit( 1 );
    }
}

echo "P0 DEV recipe v3 static guards OK\n";
