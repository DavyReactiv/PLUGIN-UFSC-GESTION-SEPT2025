<?php
$root = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/admin/list-tables/class-ufsc-licences-list-table.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$css   = file_get_contents( $root . '/assets/css/ufsc-front.css' );
$js    = file_get_contents( $root . '/assets/js/frontend-dashboard.js' );
$assert = static function ( $ok, $message ) { if ( ! $ok ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$assert( false !== strpos( $admin, "array( 25, 50 )" ) && false !== strpos( $admin, 'LIMIT {$pagination[\'per_page\']} OFFSET {$offset}' ), 'Admin pagination stays server-side and bounded.' );
$assert( false !== strpos( $admin, 'ufsc_get_licence_season_context_status' ) && false !== strpos( $admin, "['is_historical']" ), 'Admin actions use row season context.' );
$historical = substr( $admin, strpos( $admin, 'if ( ! empty( $season_context[\'is_historical\'] ) )' ), 1600 );
$assert( false !== strpos( $historical, "['action_url']" ) && false !== strpos( $historical, '} elseif ( ufsc_user_can' ), 'Historical action branch is separated from current-season edit.' );
$assert( false !== strpos( $front, 'id="<?php echo esc_attr( $checkbox_id ); ?>"' ) && false !== strpos( $front, 'aria-describedby=' ), 'Checkbox has unique identity and description.' );
$assert( false !== strpos( $front, "'renew_source_id'") && false !== strpos( $front, 'data-initial-step=' ), 'Individual renewal has server fallback.' );
$assert( false !== strpos( $js, 'event.preventDefault()' ) && false !== strpos( $js, "data-initial-step" ), 'Enhanced link enters requested step.' );
$assert( false !== strpos( $css, '.ufsc-club-portal [hidden]') && false !== strpos( $css, '.ufsc-document-button') && false !== strpos( $css, 'min-height: 44px'), 'Hidden state and accessible dedicated buttons are protected.' );
echo "P0 production renewal safeguards passed.\n";
