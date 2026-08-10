<?php
/** Runtime proof: rendered incomplete source, fallback step two and a real cart call. */
define('ABSPATH', __DIR__.'/'); define('UFSC_CL_URL','https://example.test/plugin/'); define('UFSC_CL_VERSION','test');
function __($v){return $v;} function esc_html__($v){return $v;} function esc_attr__($v){return $v;} function esc_html_e($v){echo $v;} function esc_attr_e($v){echo $v;}
function absint($v){return abs((int)$v);} function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));} function sanitize_text_field($v){return trim((string)$v);} function wp_unslash($v){return $v;}
function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_url($v){return $v;} function esc_textarea($v){return esc_html($v);}
function add_query_arg($args,$url){return $url.(strpos($url,'?')===false?'?':'&').http_build_query($args);} function admin_url($p=''){return 'https://example.test/wp-admin/'.$p;} function get_permalink(){return 'https://example.test/club/';} function get_page_by_path(){return (object)array('ID'=>1);} function home_url($p=''){return 'https://example.test/'.$p;}
function is_user_logged_in(){return true;} function get_current_user_id(){return 5;} function get_transient(){return array();} function wp_nonce_field(){echo '<input type="hidden" name="_wpnonce" value="runtime">';}
function disabled($yes){if($yes)echo 'disabled="disabled"';} function checked($yes){if($yes)echo 'checked="checked"';} function selected(){} function ufsc_get_licence_product_id(){return 0;}
function ufsc_get_licence_season_label($r){return $r->season;} function ufsc_get_licence_season_context_status(){return array('renewal_allowed'=>true,'renewal_state'=>'renewable','action_label'=>'Renouveler','renewal_reason'=>'');}
function ufsc_normalize_fighter_level($v){return $v;} function ufsc_fighter_level_label($v){return $v?:'Niveau manquant';} function ufsc_get_sport_level_options(){return array('assaut'=>'Assaut');} function ufsc_get_sport_level_help(){return '';}
class UFSC_Season_Service{static function get_current_season(){return '2026-2027';}} class UFSC_Renewal_Service{static function editable_renewal_fields(){return array();}}
class UFSC_Identifier_Resolver{static function read(){return 'UFSC-42';}}
require dirname(__DIR__).'/includes/frontend/class-frontend-shortcodes.php';
$row=(object)array('id'=>42,'club_id'=>9,'season'=>'2025-2026','nom'=>'Janaelle','prenom'=>'Test','fighter_level'=>'','poids'=>'');
$method=new ReflectionMethod('UFSC_Frontend_Shortcodes','render_renewal_assistant'); $method->setAccessible(true);
$_GET=array(); $html=$method->invoke(null,array($row),array('club_id'=>9));
if(!preg_match('/<input[^>]+id="ufsc-renew-42"[^>]*>/', $html,$m))exit("FAIL checkbox absent\n");
if(stripos($m[0],'disabled')!==false)exit("FAIL incomplete checkbox disabled\n");
if(strpos($m[0],'name="ufsc_renew_ids[]"')===false || strpos($m[0],'value="42"')===false || strpos($m[0],'class="ufsc-renew-checkbox ufsc-renewal-checkbox"')===false)exit("FAIL checkbox contract\n");
if(strpos($html,'data-selectable="1"')===false || strpos($html,'data-complete="0"')===false || strpos($html,'data-cart-eligible="0"')===false || strpos($html,'data-blocked="0"')===false)exit("FAIL independent decisions without product\n");
if(!preg_match('/href="([^"]*renew_source_id=42[^"]*target_season=2026-2027[^"]*)"/',html_entity_decode($html)))exit("FAIL fallback URL\n");
$_GET=array('renew_source_id'=>'42','target_season'=>'2026-2027'); $fallback=$method->invoke(null,array($row),array('club_id'=>9));
if(strpos($fallback,'data-initial-step="2"')===false || strpos($fallback,'ufsc-renewal-profile-title-42')===false || !preg_match('/data-ufsc-step-actions="2"/', $fallback))exit("FAIL server step two\n");
if(strpos($fallback,'name="ufsc_renew_ids[]"')===false || strpos($fallback,'name="action" value="ufsc_bulk_renew_licences"')===false)exit("FAIL cart form\n");
foreach(array('renewal_profiles[42][email]','renewal_profiles[42][telephone]','renewal_profiles[42][adresse]','renewal_profiles[42][code_postal]','renewal_profiles[42][ville]','renewal_profiles[42][date_naissance]','renewal_profiles[42][sexe]','renewal_profiles[42][poids]','renewal_profiles[42][fighter_level]','renewal_profiles[42][pratique]','renewal_profiles[42][role]') as $field){ if(strpos($fallback,'name="'.$field.'"')===false)exit("FAIL editable field $field\n"); }
if(strpos($fallback,'Saison d’origine')===false || strpos($fallback,'Saison cible')===false || strpos($fallback,'ufsc_renew_source_season')===false)exit("FAIL source/target season context\n");
if(strpos($fallback,'data-ufsc-completeness')===false || strpos($fallback,'data-ufsc-product-ready')===false || strpos($fallback,'ufsc-cart-readiness')===false)exit("FAIL completeness/cart runtime UI\n");
$rows=array(); for($id=1;$id<=25;$id++){ $rows[]=(object)array('id'=>$id,'club_id'=>9,'season'=>'2025-2026','nom'=>'Personne '.$id,'prenom'=>'Test','fighter_level'=>'','poids'=>''); }
$_GET=array('ufsc_renew_per_page'=>'10','ufsc_renew_page'=>'2'); $page_two=$method->invoke(null,array_slice($rows,10,10),array('club_id'=>9),25,2,10,array());
if(substr_count($page_two,'class="ufsc-renewal-source-row')!==10)exit("FAIL controlled pagination row count\n");
if(strpos($page_two,'value="11"')===false || strpos($page_two,'value="21"')!==false)exit("FAIL controlled pagination window\n");
if(strpos($page_two,'ufsc_renew_page=3')===false || strpos($page_two,'ufsc_renew_per_page=10')===false)exit("FAIL pagination state URL\n");
$_GET=array('renew_source_id'=>'24','target_season'=>'2026-2027','ufsc_renew_per_page'=>'10'); $direct_page=$method->invoke(null,array_slice($rows,20,5),array('club_id'=>9),25,3,10,array());
if(strpos($direct_page,'ufsc-renewal-profile-title-24')===false || strpos($direct_page,'Page 3 sur 3')===false)exit("FAIL fallback source page resolution\n");
echo "OK: rendered selectable incomplete checkbox, fallback steps, cart form and controlled pagination\n";
