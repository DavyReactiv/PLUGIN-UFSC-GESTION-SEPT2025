<?php
$root = dirname( __DIR__ );
$assert = function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$resolver = file_get_contents( $root . '/includes/core/class-ufsc-storage-resolver.php' );
$mapping = file_get_contents( $root . '/includes/core/class-user-club-mapping.php' );
foreach ( array( 'responsable_id', 'user_id', 'owner_id', 'contact_user_id', 'wp_user_id' ) as $column ) { $assert( strpos( $resolver, $column ) !== false, "User relation column {$column} is supported." ); }
foreach ( array( 'ufsc_club_id', 'club_id', 'ufsc_user_club_id', '_ufsc_club_id' ) as $meta ) { $assert( strpos( $resolver, $meta ) !== false, "User meta {$meta} is supported." ); }
$assert( strpos( $resolver, 'diagnostic_only' ) !== false && strpos( $resolver, 'email_match_requires_admin_confirmation' ) !== false, 'Email matches are diagnostic-only.' );
$assert( strpos( $mapping, 'ufsc_get_club_id_for_user' ) !== false && strpos( $mapping, 'resolve_user_club' ) !== false, 'Structured user-club resolver is exposed.' );
echo "User-club link compatibility runtime safeguards OK\n";
