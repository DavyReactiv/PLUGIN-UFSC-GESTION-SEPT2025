<?php
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/../'); }
require_once __DIR__ . '/../includes/core/class-sql.php';
require_once __DIR__ . '/../includes/frontend/class-frontend-shortcodes.php';

if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style() {} }
if (!function_exists('esc_html__')) { function esc_html__($t,$d='default'){return $t;} }
if (!function_exists('__')) { function __($t,$d='default'){return $t;} }
if (!function_exists('esc_html')) { function esc_html($t){ return htmlspecialchars($t, ENT_QUOTES); } }
if (!function_exists('esc_attr')) { function esc_attr($t){ return htmlspecialchars($t, ENT_QUOTES); } }
if (!function_exists('esc_url')) { function esc_url($u){ return $u; } }
if (!function_exists('esc_html_e')) { function esc_html_e($t,$d='default'){ echo esc_html__($t,$d); } }
if (!function_exists('is_user_logged_in')) { function is_user_logged_in(){ return true; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!function_exists('ufsc_get_user_club_id')) { function ufsc_get_user_club_id($user_id){ return 1; } }
if (!function_exists('remove_query_arg')) { function remove_query_arg($key){ return '#'; } }
if (!function_exists('get_option')) { function get_option($n,$d=array()){ return array(); } }
if (!function_exists('wp_parse_args')) { function wp_parse_args($a,$d){ return array_merge($d,$a); } }
if (!function_exists('apply_filters')) { function apply_filters($tag,$value){ return $value; } }
if (!function_exists('date_i18n')) { function date_i18n($f,$ts){ return date($f,$ts); } }
if (!defined('UFSC_CL_URL')) { define('UFSC_CL_URL',''); }
if (!defined('UFSC_CL_VERSION')) { define('UFSC_CL_VERSION',''); }

function ufsc_get_licences_table(){ return 'licences'; }

$licence = (object) array(
    'prenom' => 'Jean',
    'nom' => 'Dupont',
    'email' => 'jean@example.com',
    'date_naissance' => '1990-05-01',
    'adresse' => '1 rue Test',
    'tel_mobile' => '0600000000',
    'reduction_postier' => 1,
    'identifiant_laposte' => '123456',
    'reduction_benevole' => 0,
    'licence_delegataire' => 0,
    'numero_licence_delegataire' => '',
    'note' => 'Licence de test',
    'statut' => 'validated',
    'payment_status' => 'paid'
);

$wpdb = new class($licence) {
    private $licence;
    public function __construct($licence){ $this->licence = $licence; }
    public function prepare($q){ return $q; }
    public function get_row($q){ return $this->licence; }
};
$GLOBALS['wpdb'] = $wpdb;

echo UFSC_Frontend_Shortcodes::render_single_licence(1);
