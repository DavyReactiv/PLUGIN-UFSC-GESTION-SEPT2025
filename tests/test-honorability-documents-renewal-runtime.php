<?php
/** Autonomous persistence, validation and bulk-renewal execution test. */
define( 'ABSPATH', __DIR__ . '/' ); define( 'MB_IN_BYTES', 1048576 );
$GLOBALS['options'] = array(); $GLOBALS['posts'] = array( 101 => (object) array( 'ID' => 101 ), 102 => (object) array( 'ID' => 102 ) );
function __( $v ) { return $v; } function _n( $a, $b, $n ) { return 1 === $n ? $a : $b; }
function absint( $v ) { return abs( (int) $v ); } function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
function sanitize_text_field( $v ) { return trim( (string) $v ); } function sanitize_textarea_field( $v ) { return trim( (string) $v ); }
function wp_unslash($v){return $v;} function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);} function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;}
function sanitize_title( $v ) { return strtolower( str_replace( array( ' ', '_' ), '-', remove_accents( trim( $v ) ) ) ); }
function remove_accents( $v ) { return strtr( $v, array( 'é'=>'e', 'É'=>'E', 'è'=>'e', 'î'=>'i' ) ); }
function apply_filters( $hook, $value ) { return $value; } function get_current_user_id() { return 77; }
function wp_generate_uuid4() { static $i = 0; return 'test-cart-' . ++$i; }
function current_time( $type ) { return '2026-08-04 12:00:00'; } function get_post( $id ) { return $GLOBALS['posts'][$id] ?? null; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value ) { $GLOBALS['options'][$key] = $value; return true; }
class WP_Error { private $c; private $m; function __construct($c,$m){$this->c=$c;$this->m=$m;} function get_error_message(){return $this->m;} }
class UFSC_Category_Repository { const DEFAULT_DISCIPLINE='kickboxing'; public static function normalize_weight($v){ return is_numeric(str_replace(',','.',$v)) ? (float) str_replace(',','.',$v) : null; } public static function detect_for_athlete(){return array('age_category_label'=>'Senior','weight_category_label'=>'-65 kg');} }
class UFSC_Renewal_Service { public static function person_key($row,$club){ return 'test:' . $club . ':' . $row->id; } public static function sanitize_renewal_updates($source,$raw){return array('data'=>array('fighter_level'=>$source->fighter_level,'poids'=>(float)$source->poids),'changes'=>array(),'errors'=>array(),'sensitive_identity_change'=>false);} public static function create_target_draft($source,$club,$season,$updates=array()){return array('licence_id'=>100+(int)$source->id,'created'=>true);} }
class UFSC_Identifier_Resolver { public static function read(){return 'UFSC-TEST';} }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class FakeWpdb { public $rows=array(); function prepare($q,...$a){return false!==strpos($q,'GET_LOCK')||false!==strpos($q,'RELEASE_LOCK')?$q:end($a);} function get_row($id){return $this->rows[(int)$id]??null;} function get_col($q=''){return false!==strpos($q,'DESC')?array('id','club_id','season','role','is_included'):array('president','secretaire','tresorier','adherent','adherent','adherent','adherent','adherent','adherent','adherent');} function get_var($q){return 1;} function query($q){return 0;} }
$wpdb = new FakeWpdb(); $GLOBALS['wpdb'] = $wpdb;
function ufsc_get_licences_table(){return 'licences';} function ufsc_get_licence_season($id){return is_object($id)?'2025-2026':'2026-2027';}
require dirname(__DIR__) . '/inc/common/compliance.php';

$wpdb->rows[1]=(object)array('id'=>1,'club_id'=>9,'role'=>'coach','nom'=>'Ada','prenom'=>'L','date_naissance'=>'1990-01-01','fighter_level'=>'classe_c','poids'=>'62.5');
$wpdb->rows[2]=(object)array('id'=>2,'club_id'=>9,'role'=>'pratiquant','nom'=>'Lin','prenom'=>'T','date_naissance'=>'2000-01-01','fighter_level'=>'assaut','poids'=>'71');
$doc=ufsc_save_honorability_document(1,9,'2026-2027','coach',101,77);
if(is_wp_error($doc)||101!==$doc['attachment_id']||'pending'!==$doc['status']) exit("FAIL upload\n");
$doc=ufsc_save_honorability_document(1,9,'2026-2027','coach',102,77);
if(1!==count($doc['history'])||101!==$doc['history'][0]['attachment_id']) exit("FAIL replacement history\n");
if(!is_wp_error(ufsc_decide_honorability_document(1,'2026-2027','rejected','',77))) exit("FAIL refusal reason\n");
ufsc_decide_honorability_document(1,'2026-2027','correction_required','Document illisible',77);
$reasons=array(); if(ufsc_can_validate_licence(1,$reasons)||false===strpos(implode(' ',$reasons),'Validation impossible')) exit("FAIL final block\n");
ufsc_decide_honorability_document(1,'2026-2027','validated','',77);
$reasons=array(); if(!ufsc_can_validate_licence(1,$reasons)) exit("FAIL validated allow\n");
$reasons=array(); if(!ufsc_can_validate_licence(2,$reasons)) exit("FAIL practitioner exemption\n");
$kpi=ufsc_get_honorability_document_kpis(array($wpdb->rows[1],$wpdb->rows[2]),'2026-2027'); if(1!==$kpi['required']||1!==$kpi['complete']) exit("FAIL KPI\n");

function add_action(){} function add_filter(){} function is_user_logged_in(){return true;} function ufsc_is_club_affiliated_for_season(){return true;}
function ufsc_club_can_manage_licences_for_season(){return array('allowed'=>true,'message'=>'');}
function ufsc_get_renewed_licence_marker($id){return 3===$id?99:0;} function ufsc_wc_log(){} function ufsc_get_user_club_id(){return 9;}
class FakeCart { public $items=array(); function add_to_cart($p,$q,$v,$a,$d){$this->items[]=array('product_id'=>$p,'quantity'=>$q)+$d;return 'k'.count($this->items);} function get_cart(){return $this->items;} function calculate_totals(){} function set_session(){} }
class FakeSession { function set_customer_session_cookie($set){} function save_data(){} }
class FakeProduct { function is_purchasable(){return true;} } function wc_get_product(){return new FakeProduct();}
class FakeWC { public $cart,$session; function __construct(){ $this->cart=new FakeCart();$this->session=new FakeSession(); } } $GLOBALS['wc']=new FakeWC(); function WC(){return $GLOBALS['wc'];}
$wpdb->rows[3]=(object)array('id'=>3,'club_id'=>9,'role'=>'coach','nom'=>'Old','prenom'=>'Done','date_naissance'=>'1980-01-01','fighter_level'=>'veteran','poids'=>'82');
require dirname(__DIR__) . '/inc/woocommerce/cart-integration.php';
require dirname(__DIR__) . '/inc/common/fighter-level.php';
$result=ufsc_add_renewal_sources_to_cart(55,9,array(1,2,3),'2026-2027');
if(array(1,2)!==$result['added']||!isset($result['skipped'][3])||2!==count(WC()->cart->items)) exit("FAIL bulk result\n");
foreach(WC()->cart->items as $item){if(1!==$item['quantity']||empty($item['ufsc_renew_from_licence_id']))exit("FAIL distinct quantity-one lines\n");}
if('missing'!==ufsc_get_honorability_document(1,'2027-2028')['status']) exit("FAIL annual document isolation\n");
echo "OK: persistent honorability documents, validation gate, KPI and bulk renewal\n";
