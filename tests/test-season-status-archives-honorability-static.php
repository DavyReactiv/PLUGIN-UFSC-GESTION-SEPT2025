<?php
/** Executable regression checks for annual status, lazy archives and compliance. */
$root = dirname( __DIR__ );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$admin = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-clubs-list-table.php' );
$handler = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$js = file_get_contents( $root . '/assets/js/ufsc-license-form.js' );
$woo = file_get_contents( $root . '/inc/woocommerce/hooks.php' ) . file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$failures = array();
$check = static function ( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };

$check( false !== strpos( $front, 'ufsc_get_annual_affiliation_status( $annual_affiliation )' ), 'Dashboard status must use the annual row.' );
$check( false !== strpos( $admin, 'EXISTS (SELECT 1' ) && false !== strpos( $admin, "'club_view' => 'renewals'" ), 'Season clubs and renewal clubs need separate views.' );
$check( false !== strpos( $admin, 'Clubs à renouveler / anciens clubs' ) && false !== strpos( $admin, 'name="season"' ), 'Renewal action and season filter must remain visible.' );
$check( false !== strpos( $front, "'ufsc_show_archives', '1'" ) && false !== strpos( $front, 'if ( $show_archives )' ), 'Archives must be lazy and user-triggered.' );
$check( false !== strpos( $front, 'Renouveler cette licence' ) && false !== strpos( $front, 'ufsc_renew_from_licence_id' ), 'Individual archive renewal must remain available.' );
$check( false !== strpos( $front, 'Vous devez renouveler et faire valider' ), 'Inactive affiliation must explain the renewal block.' );
$check( 2 === substr_count( $front, 'ufsc-health-document-link' ), 'Both health documents must be permanently consultable.' );
$check( 2 === substr_count( $front, 'name="health_questionnaire_confirmed"' ), 'Exactly two mutually-exclusive confirmation controls are expected.' );
$check( false !== strpos( $js, "role.val() !== 'pratiquant'" ), 'Only Pratiquant is exempt in the browser.' );
$check( false !== strpos( $handler, 'ufsc_role_requires_honorability( $role )' ), 'Server-side honorability must use the central rule.' );
$check( false !== strpos( $front, 'Attestation d’honorabilité manquante' ) && false !== strpos( $front, 'ne bloque ni le brouillon, ni le panier, ni le paiement' ), 'Missing attestation must be explicit and non-blocking at checkout.' );
$check( false === strpos( $handler, 'medical_answer' ) && false === strpos( $handler, 'questionnaire_response' ), 'Medical answers must never be stored.' );
$check( false !== strpos( $woo, "'previous_licence_id'" ), 'Renewals must retain lineage.' );
$check( false === strpos( $woo, "'numero_asptt','" ) && false === strpos( $woo, "'numero_asptt'," ), 'ASPTT number must not be part of the renewal copy whitelist.' );
$check( false !== strpos( $woo, 'ufsc_add_renewal_sources_to_cart' ) && false !== strpos( $front, 'renew_licence_ids[]' ), 'Bulk renewal must create selectable, individually contextualized lines.' );
$check( false !== strpos( $handler, 'handle_upload_honorability_attestation' ) && false !== strpos( $handler, 'handle_decide_honorability_attestation' ), 'Persistent upload and decision handlers must be registered.' );

if ( $failures ) { fwrite( STDERR, "FAIL\n- " . implode( "\n- ", $failures ) . "\n" ); exit( 1 ); }
echo "OK: season status, archive and honorability static regressions\n";
