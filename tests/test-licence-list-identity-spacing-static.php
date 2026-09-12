<?php
$js = file_get_contents( __DIR__ . '/../assets/js/ufsc-production-licence-ux.js' );
$css = file_get_contents( __DIR__ . '/../assets/css/ufsc-production-licence-ux.css' );

$checks = array(
    'metadata is detached before reading identity text' => false !== strpos( $js, "var meta = identity.querySelector('.ufsc-licence-person-meta');" ) && false !== strpos( $js, 'if (meta) meta.remove();' ),
    'name is read only after metadata detachment' => false !== strpos( $js, "if (meta) meta.remove();\n      var raw = (identity.textContent || '').trim();" ),
    'metadata is reattached after the name' => false !== strpos( $js, 'if (meta) identity.appendChild(meta);' ),
    'metadata renders on its own line' => false !== strpos( $css, '.ufsc-licence-person-meta{display:block;' ),
    'metadata has breathing room below the name' => false !== strpos( $css, 'margin-top:7px' ),
);

$failed = array();
foreach ( $checks as $label => $passed ) {
    if ( ! $passed ) {
        $failed[] = $label;
    }
}

if ( $failed ) {
    fwrite( STDERR, 'FAIL: ' . implode( '; ', $failed ) . PHP_EOL );
    exit( 1 );
}

echo "Licence identity spacing safeguards OK\n";
