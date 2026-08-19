<?php
$root = dirname( __DIR__ );
$flags = file_get_contents( $root . '/inc/common/feature-flags.php' );
$hardening = file_get_contents( $root . '/inc/common/included-submit-hardening.php' );
$fail = static function ( $message ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); };

strpos( $flags, 'included-submit-hardening.php' ) !== false || $fail( 'included submit hardening is not loaded' );
strpos( $hardening, "array( 'statut' => 'en_attente' )" ) !== false || $fail( 'canonical pending status write missing' );
strpos( $hardening, "array( 'status' => 'pending' )" ) !== false || $fail( 'legacy status mirror is not isolated' );
strpos( $hardening, "'is_included' => 1" ) !== false || $fail( 'NULL-safe included quota persistence missing' );
strpos( $hardening, "'payment_status' => 'included'" ) !== false || $fail( 'included payment state missing' );
strpos( $hardening, 'ufsc_get_licence_status_from_record' ) !== false || $fail( 'fresh status verification missing' );
strpos( $hardening, "'en_attente' === \$normalized" ) !== false || $fail( 'pending verification guard missing' );
strpos( $hardening, "remove_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_journey_finalize_licence' )" ) !== false || $fail( 'legacy detail finalizer is not replaced' );
strpos( $hardening, 'Licence envoyée à l’UFSC pour validation.' ) !== false || $fail( 'success confirmation missing' );
strpos( $hardening, 'Envoi non effectué.' ) !== false || $fail( 'explicit failure feedback missing' );
strpos( $hardening, 'ufsc_journey_finalize_licence();' ) !== false || $fail( 'paid route delegation missing' );

// Regression guard: the hardened included path must not call the dual-column
// helper that can make a legacy mirror constraint cancel the canonical write.
strpos( $hardening, 'UFSC_Licence_Status::update_status_columns' ) === false || $fail( 'dual-column status helper reintroduced in included path' );

echo "Included submit hardening guards OK\n";
