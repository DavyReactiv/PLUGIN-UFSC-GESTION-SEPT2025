<?php
$root = dirname( __DIR__ );

$module = file_get_contents( $root . '/inc/common/address-assist.php' );
$js = file_get_contents( $root . '/assets/js/ufsc-address-assist.js' );
$club = file_get_contents( $root . '/includes/frontend/class-club-form.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$bootstrap = file_get_contents( $root . '/ufsc-clubs-licences-sql.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false !== strpos( $bootstrap, "inc/common/address-assist.php" ), 'address assistance module must be loaded' );
$assert( false !== strpos( $module, "'wp_ajax_ufsc_address_lookup_postal_code'" ), 'authenticated postcode lookup must exist' );
$assert( false !== strpos( $module, "'wp_ajax_nopriv_ufsc_address_lookup_postal_code'" ), 'public postcode lookup must exist for public forms' );
$assert( false !== strpos( $module, "check_ajax_referer( 'ufsc_address_assist', 'nonce' )" ), 'postcode lookup must be nonce protected' );
$assert( false !== strpos( $module, "preg_match( '/^\\d{5}$/'" ), 'French postcode lookup must require five digits' );
$assert( false !== strpos( $module, "'https://geo.api.gouv.fr/communes'" ), 'official commune API must be the lookup source' );
$assert( false !== strpos( $module, "30 * DAY_IN_SECONDS" ), 'successful postcode lookups must be cached' );
$assert( false !== strpos( $module, "15 * MINUTE_IN_SECONDS" ), 'remote failures must be briefly cached' );
$assert( false !== strpos( $module, "'timeout'     => 2.5" ), 'remote lookup must use a short timeout' );
$assert( false !== strpos( $module, "class_exists( 'WC_Countries' )" ), 'country choices must be local and reuse WooCommerce country data when available' );

$assert( false !== strpos( $js, "setTimeout(function ()" ) && false !== strpos( $js, "lookupDelay || 350" ), 'postcode lookup must be debounced' );
$assert( false !== strpos( $js, "if (!/^\\d{5}$/.test(postal))" ), 'no lookup may occur before a valid French postcode exists' );
$assert( false !== strpos( $js, "if (countryInput && !isFrance(countryInput.value))" ), 'foreign addresses must never call the French commune API' );
$assert( false !== strpos( $js, "cityInput.value = city" ), 'city suggestion must require explicit user choice' );
$assert( false !== strpos( $js, "renderLookupError(cityInput)" ), 'manual fallback must remain available on lookup failure' );
$assert( false !== strpos( $js, "current && values.indexOf(current) === -1" ), 'historical country values must be preserved even when absent from the list' );
$assert( false !== strpos( $js, "if (!current) current = cfg.defaultCountry || 'France'" ), 'France must default only when the country is empty' );

$assert( false !== strpos( $club, 'name="pays"' ), 'club address form must expose the existing country field' );
$assert( false !== strpos( $club, "! empty( \$club_data['pays'] ) ? \$club_data['pays'] : 'France'" ), 'existing club country must be preserved and France used only for empty/new records' );
$assert( false !== strpos( $front, "ufsc_address_assist_enqueue" ), 'front licence/profile forms must enqueue the helper explicitly' );

$assert( false === stripos( $module, 'DELETE FROM' ), 'address helper must not delete data' );
$assert( false === stripos( $module, 'UPDATE ' ), 'address helper must not update business tables' );
$assert( false === strpos( $module, 'WC()->cart' ), 'address helper must not touch WooCommerce cart' );
$assert( false === strpos( $module, 'woocommerce_add_to_cart' ), 'address helper must not hook add-to-cart' );
$assert( false === strpos( $module, 'woocommerce_checkout' ), 'address helper must not hook checkout' );

echo "Address assistance zero-regression safeguards OK\n";
