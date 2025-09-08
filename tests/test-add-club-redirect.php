<?php
define('ABSPATH', __DIR__);
if (!function_exists('add_filter')) {
    function add_filter($tag, $func) {
        $GLOBALS['ufsc_filters'][$tag] = $func;
    }
}
if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . $path;
    }
}
require_once __DIR__ . '/../includes/frontend/class-auth-shortcodes.php';

$redirect = ufsc_handle_registration_form('https://example.com');
if ($redirect === 'https://example.com/creation-du-club/') {
    echo "Add club redirect OK\n";
} else {
    echo "Add club redirect FAIL: $redirect\n";
}
