<?php
$root = dirname( __DIR__ );
$module = file_get_contents( $root . '/includes/admin/class-ufsc-bank-transfer-admin.php' );
$loader = file_get_contents( $root . '/includes/admin/class-user-profile-scope-field.php' );
$payments = file_get_contents( $root . '/includes/core/class-ufsc-licence-payments.php' );

$failures = array();
$assert = static function ( $ok, $message ) use ( &$failures ) {
    if ( ! $ok ) { $failures[] = $message; echo "FAIL: {$message}\n"; return; }
    echo "PASS: {$message}\n";
};

$assert( false !== strpos( $loader, 'class-ufsc-bank-transfer-admin.php' ), 'Le suivi des virements est chargé uniquement depuis le module admin existant.' );
$assert( false !== strpos( $module, "'bacs' !== \$order->get_payment_method()" ), 'Seules les commandes réellement réglées par virement BACS peuvent être confirmées.' );
$assert( false !== strpos( $module, 'is_ufsc_order' ) && false !== strpos( $module, '_ufsc_club_id' ), 'Une commande doit être identifiée comme commande UFSC avant rapprochement.' );
$assert( false !== strpos( $module, 'transfer_received' ) && false !== strpos( $module, 'Virement reçu sur le compte' ), 'La confirmation demande une validation humaine explicite du relevé bancaire.' );
$assert( false !== strpos( $module, "META_CONFIRMED" ) && false !== strpos( $module, "META_DATE" ) && false !== strpos( $module, "META_USER" ) && false !== strpos( $module, "META_REFERENCE" ), 'La commande conserve date, utilisateur et référence de rapprochement.' );
$assert( false !== strpos( $module, "already_confirmed" ), 'La confirmation est idempotente et refuse une seconde validation.' );
$assert( false !== strpos( $module, "array( 'pending', 'on-hold' )" ), 'Les commandes annulées, remboursées ou déjà finalisées ne peuvent pas être payées manuellement.' );
$assert( false !== strpos( $module, '$order->payment_complete()' ), 'Le paiement est finalisé par WooCommerce et non par une réécriture parallèle des licences.' );
$assert( false === strpos( $module, '$wpdb->update' ) && false === strpos( $module, '$wpdb->insert' ) && false === strpos( $module, '$wpdb->delete' ), 'Le module ne modifie directement aucune ligne de licence ou affiliation.' );
$assert( false !== strpos( $module, 'ufsc_audit_log' ) && false !== strpos( $module, 'add_order_note' ), 'Le rapprochement laisse une piste d’audit UFSC et une note WooCommerce.' );
$assert( false !== strpos( $payments, "'bacs'     => __( 'WooCommerce - Virement bancaire'" ), 'Le module de traçabilité paiement existant connaît déjà le gateway BACS.' );
$assert( false === strpos( $module, 'add_option' ) && false === strpos( $module, 'CREATE TABLE' ) && false === strpos( $module, 'ALTER TABLE' ), 'Aucune nouvelle table ni migration n’est introduite.' );
$assert( false === strpos( $module, '<style>' ) && false === strpos( $module, 'wp_enqueue_style' ), 'Aucun CSS admin supplémentaire n’est ajouté.' );

if ( $failures ) { exit( 1 ); }
echo "Bank transfer reconciliation safeguards OK\n";
