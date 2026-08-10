<?php
$root = dirname(__DIR__);
$main = file_get_contents($root . '/ufsc-clubs-licences-sql.php');
$front = file_get_contents($root . '/includes/frontend/class-frontend-shortcodes.php');
$admin = file_get_contents($root . '/includes/admin/class-admin-menu.php');
$assert = static function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}};
$archive_placeholder = "'" . '$Format:%h$' . "'"; $assert( false !== strpos( $main, $archive_placeholder ) || (bool) preg_match( "/UFSC_CL_BUILD_ARCHIVE', '[0-9a-f]{7,40}'/", $main ), 'archive placeholder or expanded commit SHA exists' );
$assert(substr_count($front, 'data-ufsc-build=') >= 3, 'portal build attributes exist');
$assert(false !== strpos($admin, "ufsc_asset_version( 'assets/admin/js/admin.js' )"), 'admin bundle uses file mtime helper');
echo "Release build and asset version safeguards OK\n";
