<?php
/** Runtime proof: rendered incomplete source, fallback step two and a real cart call. */
define('ABSPATH', __DIR__.'/'); define('UFSC_CL_URL','https://example.test/plugin/'); define('UFSC_CL_VERSION','test');
function __($v){return $v;} function esc_html__($v){return $v;} function esc_attr__($v){return $v;} function esc_html_e($v){echo $v;} function esc_attr_e($v){echo $v;}
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));} function sanitize_text_field($v){return trim((string)$v);} function wp_unslash($v){return $v;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_url($v){return $v;} function esc_textarea($v){return esc_html($v);}
function add_query_arg($args,$url){return $url.(strpos($url,'?')===false?'?':'&').http_build_query($args);} function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;} function get_permalink(){return 'https://example.test/club/';} function get_page_by_path(){return (object)array('ID'=>1);} function home_url($p=''){return 'https://example.test/'.$p;}
function is_user_logged_in(){return true;} function get_current_user_id(){return 5;} function get_transient(){return array();} function wp_nonce_field(){echo '<input type="hidden" name="_wpnonce" value="runtime">';}
function disabled($yes){if($yes)echo 'disabled="disabled"';} function checked($yes){if($yes)echo 'checked="checked"';} function selected(){} function ufsc_get_licence_product_id(){return 55;}
function ufsc_get_licence_season_label($r){return $r->season;} function ufsc_get_licence_season_context_status(){return array('renewal_allowed'=>true,'renewal_state'=>'renewable','action_label'=>'Renouveler','renewal_reason'=>'');}
function ufsc_normalize_fighter_level($v){return $v;} function ufsc_fighter_level_label($v){return $v?:'Niveau manquant';} function ufsc_get_sport_level_options(){return array('assaut'=>'Assaut');} function ufsc_get_sport_level_help(){return '';}
class UFSC_Season_Service{static function get_current_season(){return '2026-2027';}} class UFSC_Renewal_Service{static function editable_renewal_fields(){return array();}}
class UFSC_Identifier_Resolver{static function read(){return 'UFSC-42';}}
require dirname(__DIR__).'/includes/frontend/class-frontend-shortcodes.php';
$row=(object)array('id'=>42,'club_id'=>9,'season'=>'2025-2026','nom'=>'Janaelle','prenom'=>'Test','fighter_level'=>'','poids'=>'');
$method=new ReflectionMethod('UFSC_Frontend_Shortcodes','render_renewal_assistant'); $method->setAccessible(true);
$_GET=array(); $html=$method->invoke(null,array($row),array('club_id'=>9));
if(!preg_match('/<input[^>]+id="ufsc-renewal-source-42"[^>]*>/', $html,$m))exit("FAIL checkbox absent\n");
if(stripos($m[0],'disabled')!==false)exit("FAIL incomplete checkbox disabled\n");
if(!preg_match('/href="([^"]*renew_source_id=42[^"]*target_season=2026-2027[^"]*)"/',html_entity_decode($html)))exit("FAIL fallback URL\n");
$_GET=array('renew_source_id'=>'42','target_season'=>'2026-2027'); $fallback=$method->invoke(null,array($row),array('club_id'=>9));
if(strpos($fallback,'data-initial-step="2"')===false || strpos($fallback,'ufsc-renewal-profile-title-42')===false || !preg_match('/data-ufsc-step-actions="2"/', $fallback))exit("FAIL server step two\n");
if(strpos($fallback,'name="source_ids[]"')===false || strpos($fallback,'name="action" value="ufsc_bulk_renew_licences"')===false)exit("FAIL cart form\n");
echo "OK: rendered selectable incomplete checkbox, fallback step 2 and cart form\n";
