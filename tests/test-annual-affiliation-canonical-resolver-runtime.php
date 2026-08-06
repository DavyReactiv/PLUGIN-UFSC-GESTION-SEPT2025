<?php
/** Canonical annual affiliation resolver and central gate regression tests. */
define('ABSPATH', __DIR__ . '/');
function __($v){return $v;} function absint($v){return abs((int)$v);} function sanitize_text_field($v){return trim((string)$v);} function sanitize_key($v){return str_replace('-','_',sanitize_title($v));}
function sanitize_title($v){return trim(preg_replace('/[^a-z0-9]+/','-',strtolower(remove_accents((string)$v))),'-');} function remove_accents($v){return strtr($v,array('é'=>'e','É'=>'E','è'=>'e','É'=>'E','à'=>'a','ô'=>'o'));}
function wp_json_encode($v){return json_encode($v);} function get_current_user_id(){return 77;} function apply_filters($h,$v){return $v;} function get_option($k,$d=false){return $d;}
function add_action(){} function add_filter(){}
class UFSC_DB_Migrations { static function get_affiliation_seasons_table_name(){return 'wp_ufsc_affiliations_seasons';} }
class UFSC_Season_Service { static function get_current_season(){return '2026-2027';} }
class FakeAffiliationWpdb {
 public $prefix='wp_'; public $columns=array(); public $rows=array(); public $last_args=array();
 function prepare($query,...$args){$this->last_args=(1===count($args)&&is_array($args[0]))?$args[0]:$args;return $query;}
 function get_col(){return $this->columns;}
 function get_results($query){preg_match('/AND CAST\(`([^`]+)` AS CHAR\)/',$query,$m);$season_col=$m[1]??'season';$club=(int)($this->last_args[0]??0);$values=array_map('strval',array_slice($this->last_args,1));return array_values(array_filter($this->rows,function($r)use($club,$values,$season_col){return (int)($r->club_id??$r->id_club??0)===$club&&in_array((string)($r->{$season_col}??''),$values,true);}));}
}
$GLOBALS['wpdb']=$wpdb=new FakeAffiliationWpdb();
function ufsc_table_columns(){global $wpdb;return $wpdb->columns;}
require dirname(__DIR__) . '/includes/core/class-ufsc-season-archive-manager.php';
require dirname(__DIR__) . '/inc/common/season.php';
function assert_gate($condition,$message){if(!$condition){fwrite(STDERR,"FAIL: $message\n");exit(1);}}
$base=array('id','club_id','status','payment_status','wc_order_id');
$wpdb->columns=array_merge($base,array('season'));
$wpdb->rows=array((object)array('id'=>1,'club_id'=>9,'season'=>'2026-2027','status'=>'validated','payment_status'=>'paid','wc_order_id'=>12));
$gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate($gate['allowed']&&'validated'===$gate['annual_status']&&'season'===$gate['source_column'],'validated season');
$wpdb->columns=array_merge($base,array('saison')); $wpdb->rows=array((object)array('id'=>2,'club_id'=>9,'saison'=>'2026-2027','status'=>'active')); assert_gate(ufsc_club_can_manage_licences_for_season(9,'2026-2027')['allowed'],'active saison alias');
$wpdb->columns=array_merge($base,array('season','saison')); $wpdb->rows=array((object)array('id'=>22,'club_id'=>9,'season'=>'','saison'=>'2026-2027','status'=>'active')); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate($gate['allowed']&&'saison'===$gate['source_column'],'fallback to populated season alias');
$wpdb->columns=array_merge($base,array('season_end_year')); $wpdb->rows=array((object)array('id'=>3,'club_id'=>9,'season_end_year'=>2027,'status'=>'Validée')); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate($gate['allowed']&&'validated'===$gate['annual_status'],'end year and legacy status');
$wpdb->columns=array_merge($base,array('season')); $wpdb->rows=array((object)array('id'=>4,'club_id'=>9,'season'=>'2026-2027','status'=>'pending_payment','payment_status'=>'unpaid')); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate(!$gate['allowed']&&'affiliation_pending_payment'===$gate['code']&&false!==strpos($gate['message'],'Finalisez'),'pending payment');
$wpdb->rows=array((object)array('id'=>5,'club_id'=>9,'season'=>'2026-2027','status'=>'on-hold')); assert_gate(!ufsc_club_can_manage_licences_for_season(9,'2026-2027')['allowed'],'on-hold denied');
$wpdb->rows=array((object)array('id'=>6,'club_id'=>9,'season'=>'2026-2027','status'=>'pending_validation'),(object)array('id'=>7,'club_id'=>9,'season'=>'2026-2027','status'=>'active')); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate($gate['allowed']&&7===$gate['affiliation_id']&&1===$gate['evidence']['duplicate_count'],'active duplicate priority');
$wpdb->rows=array((object)array('id'=>8,'club_id'=>10,'season'=>'2026-2027','status'=>'active')); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate(!$gate['allowed']&&'affiliation_missing'===$gate['code'],'wrong club refused');
$wpdb->columns=array('id','club_id','status'); $wpdb->rows=array(); $gate=ufsc_club_can_manage_licences_for_season(9,'2026-2027'); assert_gate(!$gate['allowed']&&'affiliation_resolution_error'===$gate['code']&&false!==strpos($gate['message'],'n’a pas pu'),'technical fail closed');
$wpdb->columns=array_merge($base,array('season')); $wpdb->rows=array((object)array('id'=>9,'club_id'=>9,'season'=>'2026-2027','status'=>'active')); assert_gate(ufsc_club_can_manage_licences_for_season(9,2026)['allowed']&&ufsc_club_can_manage_licences_for_season(9,2027)['allowed'],'start/end year normalize to current season');
echo "Canonical annual affiliation resolver runtime safeguards passed.\n";
