<?php
/** Execute the production routing contract and count canonical licence tables. */
define( 'ABSPATH', __DIR__ );
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $value ) ); }
require dirname( __DIR__ ) . '/includes/admin/class-sql-admin.php';
$render_screen = static function ( $action ) {
	ob_start();
	if ( ! UFSC_SQL_Admin::licence_screen_renders_table( $action ) ) {
		echo '<form class="ufsc-licence-form"></form>';
	} else {
		echo '<table class="ufsc-admin-licences-table"></table>';
	}
	return ob_get_clean();
};
$expected = array( '' => 1, 'new' => 0, 'edit' => 0, 'view' => 0 );
foreach ( $expected as $action => $count ) {
	$html = $render_screen( $action );
	$actual = substr_count( $html, 'ufsc-admin-licences-table' );
	if ( $actual !== $count ) { fwrite( STDERR, "FAIL {$action}: {$actual} table(s)\n" ); exit( 1 ); }
	echo "PASS {$action}: {$actual} canonical licence table(s)\n";
}
echo "Licence render runtime contract OK\n";
