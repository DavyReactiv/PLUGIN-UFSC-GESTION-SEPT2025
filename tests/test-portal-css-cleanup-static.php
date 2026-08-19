<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$module = file_get_contents( $root . '/inc/common/portal-ui-cleanup.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-portal-clean.css' );
$js = file_get_contents( $root . '/assets/js/ufsc-portal-clean.js' );

$assert = static function ( $ok, $message ) {
    if ( ! $ok ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); }
};

$assert( false !== strpos( $flags, 'portal-ui-cleanup.php' ), 'cleanup module is loaded' );
$assert( false !== strpos( $module, "wp_dequeue_style( \$handle )" ), 'competing portal styles are dequeued' );
$assert( false !== strpos( $module, "'ufsc-club-journey'" ), 'journey overlay is removed from cascade' );
$assert( false !== strpos( $module, "'ufsc-structural-portal'" ), 'structural overlay is removed from cascade' );
$assert( false !== strpos( $module, "'ufsc-portal-clean'" ), 'single final portal stylesheet is enqueued' );

$assert( false !== strpos( $css, '.ufsc-club-hero' ) && false !== strpos( $css, 'display: block' ), 'desktop profile collapse is neutralized' );
$assert( false !== strpos( $css, 'grid-template-columns: repeat(6, minmax(0, 1fr))' ), 'desktop Club navigation has six aligned columns' );
$assert( false !== strpos( $css, '.ufsc-pack-summary' ) && false !== strpos( $css, 'repeat(3, minmax(0, 1fr))' ), 'quota panel uses a balanced three-card grid' );
$assert( false !== strpos( $css, '.ufsc-renewal-profile-grid' ) && false !== strpos( $css, 'repeat(2, minmax(0, 1fr))' ), 'renewal profile uses at most two columns' );
$assert( false !== strpos( $css, 'scroll-margin-top: 120px' ), 'fixed-header anchor offset exists' );
$assert( false !== strpos( $css, '180px' ), 'club logo has a useful desktop size' );
$assert( false === strpos( $css, '!important' ), 'cleanup stylesheet does not add important declarations' );

$assert( false !== strpos( $js, 'normalizeKpis' ), 'KPI reconciliation UI exists' );
$assert( false !== strpos( $js, 'Licences actives ' ), 'active licence label is explicit' );
$assert( false !== strpos( $js, 'normalizeActionTargets' ), 'CTA anchors are normalized' );
$assert( false !== strpos( $js, 'Dossiers prêts pour validation' ), 'included renewal review removes cart vocabulary' );
$assert( false !== strpos( $js, 'Envoyer pour validation — inclus dans votre affiliation' ), 'included renewal CTA is restored' );
$assert( false !== strpos( $js, 'simplifyLogo' ), 'logo editor exposes one primary action' );

echo "Club portal consolidated CSS/UX safeguards OK\n";
