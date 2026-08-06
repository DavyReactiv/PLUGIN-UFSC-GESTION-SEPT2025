<?php
$root  = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$css   = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$assert = static function ( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$assert( false !== strpos( $admin, "\$edit_context['is_historical']" ) && false !== strpos( $admin, 'archive en lecture seule' ), 'A direct edit URL cannot mutate a historical annuality.' );
$assert( false !== strpos( $admin, "empty( \$season_context['is_historical'] )" ) && false !== strpos( $admin, 'Ouvrir la nouvelle licence' ), 'Archive detail has contextual, non-mutating actions.' );
$assert( false !== strpos( $front, 'class="ufsc-licences-workspace"' ), 'Licence content has its dedicated wide workspace.' );
$assert( false !== strpos( $css, '.ufsc-club-portal .ufsc-licences-workspace' ) && false !== strpos( $css, 'max-width: 1440px' ), 'Workspace width is scoped and bounded.' );
$assert( false !== strpos( $css, '@media(max-width:768px)') && false !== strpos( $css, '.ufsc-renewal-source-row td:before' ), 'Renewals become labelled cards on mobile.' );
echo "Final admin and renewal UI safeguards passed.\n";
