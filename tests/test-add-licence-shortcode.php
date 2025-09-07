<?php
define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/frontend/class-frontend-shortcodes.php';

if (!function_exists('__')) { function __($t,$d='default'){ return $t; } }
if (!function_exists('esc_html__')) { function esc_html__($t,$d='default'){ return $t; } }
if (!function_exists('esc_html')) { function esc_html($t){ return $t; } }
if (!function_exists('esc_html_e')) { function esc_html_e($t,$d='default'){ return $t; } }
if (!function_exists('esc_attr')) { function esc_attr($t){ return $t; } }
if (!function_exists('esc_url')) { function esc_url($t){ return $t; } }
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs,$atts){ return array_merge($pairs,$atts); } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(){ return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!function_exists('get_transient')) { function get_transient($k){ return false; } }
if (!function_exists('delete_transient')) { function delete_transient($k){} }
if (!function_exists('current_user_can')) { function current_user_can($cap){ return true; } }
if (!function_exists('get_option')) { function get_option($name){ return $GLOBALS['ufsc_option_value']; } }
if (!function_exists('get_permalink')) { function get_permalink($id){ return 'https://example.com/product'; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field($a){ return '<input type="hidden" name="_wpnonce" value="test" />'; } }
if (!function_exists('selected')) { function selected($value,$current,$echo=true){$sel=$value==$current?' selected="selected"':''; if($echo) echo $sel; return $sel;} }
if (!function_exists('checked')) { function checked($value,$current=1,$echo=true){$chk=$value==$current?' checked="checked"':''; if($echo) echo $chk; return $chk;} }
if (!function_exists('esc_textarea')) { function esc_textarea($t){ return $t; } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($t){ return $t; } }
if (!function_exists('wp_kses')) { function wp_kses($t,$allowed=array()){ return $t; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($t){ return $t; } }

class UFSC_SQL { public static function get_settings(){ return array('table_clubs'=>'clubs','table_licences'=>'licences'); } }
class WPDB_Stub { public function get_var($q){ if (strpos($q,'quota_licences')!==false) return 10; return 0; } public function prepare($q,...$a){ return $q; } }
$wpdb = new WPDB_Stub();

$GLOBALS['ufsc_option_value'] = 0;
$out1 = UFSC_Frontend_Shortcodes::render_add_licence( array('club_id'=>1) );

$GLOBALS['ufsc_option_value'] = 123;
$out2 = UFSC_Frontend_Shortcodes::render_add_licence( array('club_id'=>1) );

if (strpos($out1, 'Produit licence introuvable') !== false && strpos($out2, 'ufsc-add-licence-section') !== false) {
    echo "Add licence shortcode OK\n";
} else {
    echo "Add licence shortcode FAIL\n";
}