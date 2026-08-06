<?php
/** Runtime contract: saving a draft can never imply a cart mutation. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); }
require dirname( __DIR__ ) . '/includes/core/class-unified-handlers.php';
$cases = array( 'save' => false, 'draft' => false, '' => false, 'ADD_TO_CART' => true, 'add_to_cart' => true, 'add_to_cart<script>' => false );
foreach ( $cases as $intent => $expected ) {
    $actual = UFSC_Unified_Handlers::should_add_licence_to_cart( $intent );
    if ( $actual !== $expected ) { fwrite( STDERR, "FAIL intent {$intent}\n" ); exit( 1 ); }
}
echo "Draft/cart intent runtime safeguards passed.\n";
