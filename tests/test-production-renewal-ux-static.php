<?php
$root = dirname( __DIR__ );
$renewal = file_get_contents( $root . '/includes/core/class-ufsc-renewal-service.php' );
$flags   = file_get_contents( $root . '/inc/common/feature-flags.php' );
$ux      = file_get_contents( $root . '/inc/common/production-licence-ux.php' );
$js      = file_get_contents( $root . '/assets/js/ufsc-production-licence-ux.js' );
$css     = file_get_contents( $root . '/assets/css/ufsc-production-licence-ux.css' );

$assert = static function( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $renewal, "'brouillon', 'draft', 'en_attente', 'pending'" ), 'draft and pending historical statuses must be renewable sources' );
$assert( false === strpos( $renewal, 'Seule une licence validée de la saison précédente peut être renouvelée.' ), 'validated-only historical renewal rule must be removed' );
$assert( false !== strpos( $renewal, 'source_season_mismatch' ), 'previous-season boundary must remain enforced' );
$assert( false !== strpos( $renewal, 'duplicate_renewal' ), 'target-season duplicate protection must remain enforced' );
$assert( false !== strpos( $flags, 'production-licence-ux.php' ), 'production licence UX must be loaded' );
$assert( false !== strpos( $ux, "'ufsc_section' => 'club-licences'" ), 'Mes licences shortcuts must use the canonical club-licences route' );
$assert( false !== strpos( $js, 'Raccourcis licences UFSC' ), 'licence pages must expose shared shortcuts' );
$assert( false !== strpos( $js, 'redirectLegacyLicenceAnchor' ), 'legacy hash-only Mes licences links must be redirected to the canonical table' );
$assert( false !== strpos( $js, 'applyTableLabels' ), 'responsive tables must receive accessible data labels' );
$assert( false !== strpos( $js, 'insertQueryNotice' ), 'query action confirmations must be surfaced consistently' );
$assert( false !== strpos( $css, '.ufsc-global-notice.is-success' ), 'success notice state must be visually distinct' );
$assert( false !== strpos( $css, '.ufsc-global-notice.is-pending' ), 'pending notice state must be visually distinct' );
$assert( false !== strpos( $css, '.ufsc-global-notice.is-error' ), 'error notice state must be visually distinct' );
$assert( false !== strpos( $css, '@media (max-width: 760px)' ), 'licence tables must have a mobile card breakpoint' );

echo "OK: production renewal eligibility, shortcuts, notices and responsive tables\n";
