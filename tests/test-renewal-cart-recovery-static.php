<?php
/** Static safeguards for renewal cart recovery. */
$root     = dirname( __DIR__ );
$flags    = file_get_contents( $root . '/inc/common/feature-flags.php' );
$recovery = file_get_contents( $root . '/inc/common/renewal-cart-recovery.php' );
$errors   = array();

$assert = static function( $condition, $message ) use ( &$errors ) {
    if ( ! $condition ) { $errors[] = $message; }
};

$assert( false !== strpos( $flags, 'renewal-cart-recovery.php' ), 'Renewal recovery must be loaded by feature flags.' );
$assert( false !== strpos( $recovery, "remove_action(\n            'admin_post_ufsc_bulk_renew_licences'" ), 'Duplicated production final renewal handler must be removed.' );
$assert( false !== strpos( $recovery, 'ufsc_renewal_recovery_existing_target' ), 'A failed first attempt must reuse its current-season target.' );
$assert( false !== strpos( $recovery, 'ufsc_wc_find_equivalent_renewed_licence_id' ), 'Retry must resolve the existing annual row rather than create a duplicate.' );
$assert( false !== strpos( $recovery, 'UFSC_Licence_Finalization_Service::finalize' ), 'Included/payable decision must use the canonical finalization service.' );
$assert( false !== strpos( $recovery, 'ufsc_add_licence_ids_to_cart_idempotent' ), 'Paid renewal retry must use the canonical idempotent cart helper.' );
$assert( false !== strpos( $recovery, 'ufsc_renewal_recovery_cart_contains_target' ), 'Success must be verified against the live native cart.' );
$assert( false !== strpos( $recovery, 'catch ( Throwable $error )' ), 'Runtime renewal failures must be converted into recoverable errors.' );
$assert( false !== strpos( $recovery, 'ufsc_renewal_recovery_keep_editable' ), 'Failed unpaid renewal must remain editable.' );
$assert( false !== strpos( $recovery, 'Complétez avant le panier' ), 'Missing renewal fields must be explained to the club.' );
$assert( false !== strpos( $recovery, "'Renouvellement de licence'" ), 'Cart must identify licence renewals correctly.' );
$assert( false !== strpos( $recovery, "'Nouvelle licence'" ), 'Cart must identify normal licence requests correctly.' );
$assert( false === strpos( $recovery, 'ufsc_allocate_pack_credit' ), 'Recovery layer must not allocate quota directly.' );
$assert( false === strpos( $recovery, 'DELETE FROM' ) && false === strpos( $recovery, 'ALTER TABLE' ), 'Recovery must not delete data or alter schema.' );

if ( $errors ) {
    fwrite( STDERR, "Renewal cart recovery safeguards failed:\n- " . implode( "\n- ", $errors ) . "\n" );
    exit( 1 );
}

echo "Renewal cart recovery safeguards: OK\n";
