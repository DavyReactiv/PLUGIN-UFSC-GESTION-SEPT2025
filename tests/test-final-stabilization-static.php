<?php
/** Targeted regression safeguards for the final season/front stabilization. */
$root = dirname( __DIR__ );
$files = array(
    'season'   => file_get_contents( $root . '/includes/core/class-ufsc-season-service.php' ),
    'helpers'  => file_get_contents( $root . '/inc/common/season.php' ),
    'admin'    => file_get_contents( $root . '/includes/admin/class-sql-admin.php' ),
    'front'    => file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' ),
    'woo'      => file_get_contents( $root . '/inc/woocommerce/settings-woocommerce.php' ),
    'layout'   => file_get_contents( $root . '/assets/css/ufsc-licence-form.css' ),
    'js'       => file_get_contents( $root . '/assets/js/ufsc-license-form.js' ),
);
$failures = array();
$assert = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$assert( strpos( $files['helpers'], 'UFSC_Season_Service::get_current_season()' ) !== false, 'Legacy helpers must delegate to the season service.' );
$assert( strpos( $files['season'], "shift_season( \$current, 1 )" ) === false, 'Available seasons must not manufacture the next season.' );
$assert( strpos( $files['admin'], 'Saison actuelle : %s' ) !== false && strpos( $files['admin'], 'Saison précédente : %s' ) !== false, 'Licence filter labels must remain explicit.' );
$assert( strpos( $files['woo'], "'product_affiliation_id'  => 4823" ) !== false && strpos( $files['woo'], 'return $configured_id > 0 ? $configured_id : 4823;' ) !== false, 'Affiliation product ID 4823 must be the fallback while explicit configuration is respected.' );
foreach ( array( 'missing_product_id', 'product_not_found', 'product_not_published', 'product_without_price', 'product_not_purchasable' ) as $reason ) {
    $assert( strpos( $files['woo'], $reason ) !== false, 'Missing Woo diagnostic: ' . $reason );
}
$assert( substr_count( $files['front'], 'id="honorabilite"' ) === 0, 'Legacy honorability checkbox must not duplicate compliance.' );
$assert( substr_count( $files['front'], 'name="health_questionnaire_confirmed"' ) === 2, 'Exactly one adult and one mutually exclusive minor health control are expected.' );
$assert( strpos( $files['js'], "honorabilityRoles.indexOf(role.val()) !== -1" ) !== false && strpos( $files['js'], "'adherent'" ) === false, 'Honorability must use an explicit regulated-role allow-list and exempt ordinary members.' );
$assert( strpos( $files['front'], "assets/css/ufsc-licence-form.css" ) !== false, 'Shared layout stylesheet must load in both render contexts.' );
$assert( strpos( $files['layout'], 'max-width: 1200px !important' ) !== false && strpos( $files['layout'], 'repeat(2, minmax(0, 1fr))' ) !== false, 'Desktop layout must be wide and balanced.' );
$assert( strpos( $files['layout'], '@media (max-width: 768px)' ) !== false, 'Responsive single-column layout is required.' );
$assert( strpos( $files['front'], 'ufsc-final-buttons' ) !== false, 'Final buttons need a stable wrapper.' );
$assert( strpos( $files['admin'], 'correction_required' ) !== false && stripos( $files['admin'], 'un motif est obligatoire' ) !== false, 'Document correction/rejection must require a reason.' );

if ( $failures ) { fwrite( STDERR, implode( "\n", $failures ) . "\n" ); exit( 1 ); }
echo "Final stabilization static safeguards passed.\n";
