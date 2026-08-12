<?php
/** Cross-layer safeguards for the P0 officer, cart and responsive delivery. */
$root = dirname( __DIR__ );
$handler = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$cart = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );
$stats = file_get_contents( $root . '/includes/front/class-ufsc-stats.php' );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$css = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$js = file_get_contents( $root . '/assets/js/ufsc-license-form.js' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
foreach ( array( 'prenom', 'nom', 'sexe', 'date_naissance', 'email', 'adresse', 'ville', 'code_postal', 'pays', 'telephone', 'role' ) as $field ) {
    $assert( false !== strpos( $handler, "'{$field}'" ), "server-required officer field {$field}" );
}
foreach ( array( 'president', 'secretaire', 'tresorier' ) as $role ) {
    $assert( false !== strpos( $front, "render_officer_licence_card( '{$role}'" ), "canonical officer card {$role}" );
}
$assert( false !== strpos( $handler, 'sync_current_officers_to_club_legacy' ) && false !== strpos( $handler, 'bureau_role_duplicate' ), 'one-way officer projection and duplicate rejection' );
$assert( false !== strpos( $front, 'Je confirme avoir transmis ou complété l’attestation d’honorabilité requise pour cette fonction.' ), 'exact accessible honorability confirmation' );
$assert( false !== strpos( $handler, "ufsc_role_requires_honorability( \$role )" ) && false !== strpos( $handler, 'honorability_confirmed_by' ), 'server-side role gate and author audit' );
$assert( false !== strpos( $front, 'name="ufsc_submit_action" value="add_to_cart"' ), 'submit button directly carries cart intent' );
foreach ( array( 'ufsc_ensure_woocommerce_cart', 'ufsc_persist_woocommerce_cart', 'set_session', 'set_customer_session_cookie', 'save_data', 'ufsc_licence_id', 'ufsc_club_id', 'ufsc_operation_type' ) as $needle ) {
    $assert( false !== strpos( $cart . $handler, $needle ), "native cart chain {$needle}" );
}
$assert( false !== strpos( $cart, 'Pack credits never create a paid cart line' ) && false !== strpos( $cart, '$already_in_cart' ), 'included-credit and double-click safeguards' );
$assert( false !== strpos( $stats, '$row->statut' ) && false !== strpos( $stats, 'unknown_profiles' ), 'canonical status and demographic source' );
$assert( false !== strpos( $front, 'append_demographic_clauses' ) && false !== strpos( $front, "UPPER(TRIM(`sexe`)) IN ('F','FEMME')" ) && false !== strpos( $front, 'STR_TO_DATE(`date_naissance`' ), 'KPI links and list queries share legacy-aware demographic normalization' );
$assert( false !== strpos( $admin, 'array( 1, 2, 3, 4, $page - 1, $page, $page + 1, (int) $total_pages )' ), 'admin pagination exposes pages 1-4 and last' );
$assert( false !== strpos( $css, 'width: min(1180px, calc(100% - 32px))' ) && false !== strpos( $css, 'min-block-size: 44px' ), '1180px container and touch target contract' );
$assert( false !== strpos( $js, 'ufscWizardInitialized' ) && false !== strpos( $js, 'ufscSubmitting' ) && false !== strpos( $js, 'event.originalEvent.submitter' ) && false !== strpos( $js, 'ufsc:resetSubmitting' ) && false !== strpos( $js, ".off('.ufscSingleSubmit')" ), 'idempotent JS preserves the clicked submitter and exposes one scoped recovery event' );
echo "P0 finalization cross-layer static safeguards OK\n";
