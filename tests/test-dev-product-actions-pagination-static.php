<?php
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$settings = file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$js = file_get_contents( $root . '/assets/js/frontend-dashboard.js' );
$assert = static function ( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: $message\n" ); exit( 1 ); } };
$assert( false !== strpos( $front, "'selectable' => ! empty( \$context['renewal_allowed'] )" ) && false !== strpos( $front, "'complete' => (bool) \$complete" ) && false !== strpos( $front, "'blocked' => empty( \$context['renewal_allowed'] )" ), 'independent state flags remain' );
$assert( false !== strpos( $front, "disabled( ! \$decision['selectable'] )" ) && false === strpos( $front, 'disabled( ! $decision[\'cart_eligible\'] )' ), 'missing data/product cannot disable selection' );
$assert( false !== strpos( $front, 'data-ufsc-build=') && false !== strpos( $bootstrap, "ufsc_asset_version( 'assets/frontend/css/frontend.css' )") && false !== strpos( $bootstrap, "ufsc_asset_version( 'assets/frontend/js/frontend.js' )"), 'HTML build and cache versions' );
$assert( false !== strpos( $settings, "apply_filters( 'ufsc_licence_product_id'") && false !== strpos( $settings, 'ufsc_get_licence_product_resolution') && false === strpos( $settings, 'wc_get_products( array( \'limit\' => 1'), 'central explicit resolver never guesses first product' );
$assert( false !== strpos( $front, 'ufsc_get_licence_product_message( $product_resolution )') && false !== strpos( $front, 'data-ufsc-cart-ready=') && false !== strpos( $front, 'disabled( ! $product_ready )'), 'cart action always rendered with unavailable explanation' );
$assert( substr_count( $front, 'Enregistrer en brouillon' ) >= 3 && false !== strpos( $js, "prop('checked', true).trigger('change')") && false !== strpos( $js, 'showRenewalStep(2'), 'draft, 0-to-1 selection and renewal verification navigation' );
foreach ( array( 'ufsc_section', 'ufsc_renew_per_page', 'ufsc_renew_search', 'ufsc_renew_sex', 'ufsc_renew_practice', 'ufsc_renew_birth_from', 'ufsc_renew_birth_to', 'ufsc_renew_state', 'ufsc_renew_page', 'aria-current="page"', 'aria-disabled="true"' ) as $needle ) { $assert( false !== strpos( $front, $needle ), "pagination preserves $needle" ); }
$assert( false !== strpos( $css, 'min-height:44px!important') && false !== strpos( $css, 'opacity:1!important') && false !== strpos( $css, 'visibility:visible!important') && false !== strpos( $css, ':focus-visible'), 'scoped visible accessible actions' );
$renewal = substr( $front, strpos( $front, 'private static function render_renewal_assistant' ), strpos( $front, 'private static function split_licences_by_active_season' ) - strpos( $front, 'private static function render_renewal_assistant' ) );
preg_match_all( '/\bid="([^"]+)"/', $renewal, $matches ); $static = array_filter( $matches[1], static function( $id ) { return false === strpos( $id, '<?php' ); } );
$assert( count( $static ) === count( array_unique( $static ) ), 'no duplicate literal DOM IDs in renewal renderer' );
echo "OK: DEV product, actions, pagination, assets and DOM safeguards\n";
