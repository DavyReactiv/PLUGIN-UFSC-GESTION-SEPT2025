<?php
$root = dirname( __DIR__ );
$profile = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-fields.php' );
$guard = file_get_contents( $root . '/includes/admin/class-ffst-club-profile-schema-guard.php' );
$layout = file_get_contents( $root . '/includes/admin/class-ffst-club-admin-layout.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$handler = file_get_contents( $root . '/includes/frontend/class-club-form-handler.php' );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$leaders_export = file_get_contents( $root . '/includes/admin/class-ffst-leaders-export-fix.php' );
$matrix_export = file_get_contents( $root . '/includes/admin/class-ffst-matrix-export-admin.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur' ) as $prefix ) {
    foreach ( array( 'pere_nom', 'pere_prenom', 'mere_nom', 'mere_prenom' ) as $suffix ) {
        $field = $prefix . '_' . $suffix;
        $assert( false !== strpos( $profile, "'_" . $suffix . "'" ) || false !== strpos( $profile, "$suffix" ), "profile field support missing: {$field}" );
    }
}

$assert( false !== strpos( $profile, "'_pere_nom_prenom'" ) && false !== strpos( $profile, "'_mere_nom_prenom'" ), 'legacy combined parent columns must be preserved' );
$assert( false !== strpos( $guard, "'_pere_nom'" ) && false !== strpos( $guard, "'_mere_prenom'" ), 'schema guard must add split fields idempotently' );
$assert( false === stripos( $profile, 'DROP COLUMN' ) && false === stripos( $profile, 'RENAME COLUMN' ), 'profile schema change must remain additive' );
$assert( false === stripos( $guard, 'DROP COLUMN' ) && false === stripos( $guard, 'RENAME COLUMN' ), 'schema guard must never remove/rename historical data' );

$assert( false !== strpos( $layout, "prefix+'_pere_nom'" ) && false !== strpos( $layout, "prefix+'_mere_prenom'" ), 'admin layout must show four split parent fields' );
$assert( false !== strpos( $front, "$prefix . '_pere_nom'" ) && false !== strpos( $front, "'entraineur_mere_prenom'" ), 'front profile must show split parent fields for leaders and trainer' );
$assert( false !== strpos( $front, 'data-ufsc-foreign-parent-input' ), 'front must mark conditional foreign-parent fields' );

$assert( false !== strpos( $handler, 'validate_foreign_parent_identity' ), 'front club save must enforce server-side foreign parent validation' );
$assert( false !== strpos( $admin, 'validate_foreign_parent_identity' ), 'admin club save must enforce server-side foreign parent validation' );
$assert( false !== strpos( $profile, 'legacy_father' ) && false !== strpos( $profile, 'legacy_mother' ), 'legacy combined values must be accepted as compatibility fallback' );

$assert( false !== strpos( $leaders_export, 'parent_identity_value' ), 'official leaders export must support split parent identities' );
$assert( false !== strpos( $matrix_export, 'parent_identity_value' ), 'FFST matrix export must support split parent identities' );
$assert( false !== strpos( $leaders_export, "'_nom_prenom'" ), 'leaders export must retain legacy combined fallback' );
$assert( false !== strpos( $matrix_export, "'_nom_prenom'" ), 'matrix export must retain legacy combined fallback' );

$assert( false === strpos( $profile, 'WC()->cart' ), 'parent identity feature must not touch WooCommerce cart' );
$assert( false === strpos( $handler, 'woocommerce_add_cart_item_data' ), 'foreign-parent validation must not alter add-to-cart hooks' );

echo "Foreign parent identity static safeguards OK\n";
