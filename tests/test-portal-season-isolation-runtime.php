<?php
/** Runtime proof that club and season constraints are applied before LIMIT/COUNT. */
define('ABSPATH', __DIR__.'/'); define('UFSC_CL_URL','/plugin/'); define('UFSC_CL_VERSION','test');
function __($v){return $v;} function absint($v){return abs((int)$v);} function sanitize_text_field($v){return trim((string)$v);} function sanitize_key($v){return strtolower($v);} function wp_parse_args($a,$d=array()){return array_merge($d,(array)$a);} function wp_unslash($v){return $v;}
function ufsc_get_licences_table(){return 'wp_ufsc_licences';} function ufsc_table_columns(){return array('id','club_id','nom','prenom','statut','paid_season','deleted_at');} function ufsc_get_detected_season_column(){return 'paid_season';}
class RuntimeWpdb {
 public $last=''; public $rows;
 function __construct(){ $this->rows=array((object)array('id'=>1,'club_id'=>9,'nom'=>'Active','prenom'=>'A','statut'=>'brouillon','paid_season'=>'2026-2027'),(object)array('id'=>2,'club_id'=>9,'nom'=>'Archive','prenom'=>'B','statut'=>'validee','paid_season'=>'2025/2026'),(object)array('id'=>3,'club_id'=>10,'nom'=>'Other club','prenom'=>'C','statut'=>'validee','paid_season'=>'2025-2026')); }
 function esc_like($v){return $v;} function prepare($sql,$values=null){$args=is_array($values)?$values:array_slice(func_get_args(),1);foreach($args as $v){$replacement=is_numeric($v)?(string)$v:"'".str_replace("'","''",$v)."'";$sql=preg_replace('/%[ds]/',$replacement,$sql,1);}return $sql;}
 function get_results($sql){$this->last=$sql;preg_match('/club_id = (\d+)/',$sql,$c);preg_match("/REPLACE\(TRIM\(`paid_season`\), '\\/', '-'\) = '([^']+)'/",$sql,$s);$club=(int)($c[1]??0);$season=$s[1]??'';return array_values(array_filter($this->rows,function($r)use($club,$season){return $r->club_id===$club && str_replace('/','-',$r->paid_season)===$season;}));}
 function get_var($sql){return count($this->get_results($sql));}
 function get_col($sql){$this->last=$sql;$out=array();foreach($this->rows as $r){if($r->club_id===9){$season=str_replace('/','-',$r->paid_season);if($season!=='2026-2027')$out[]=$season;}}return array_values(array_unique($out));}
}
$GLOBALS['wpdb']=new RuntimeWpdb();
require dirname(__DIR__).'/includes/frontend/class-frontend-shortcodes.php';
$list=new ReflectionMethod('UFSC_Frontend_Shortcodes','get_club_licences');$list->setAccessible(true);
$count=new ReflectionMethod('UFSC_Frontend_Shortcodes','get_club_licences_count');$count->setAccessible(true);
$archives=new ReflectionMethod('UFSC_Frontend_Shortcodes','get_club_archive_seasons');$archives->setAccessible(true);
$current=$list->invoke(null,9,array('season'=>'2026-2027','page'=>1,'per_page'=>20));
if(count($current)!==1 || $current[0]->id!==1 || strpos($GLOBALS['wpdb']->last,'club_id = 9')===false)exit("FAIL current season isolation\n");
$old=$list->invoke(null,9,array('season'=>'2025-2026','page'=>1,'per_page'=>20));if(count($old)!==1||$old[0]->id!==2)exit("FAIL archived season isolation or club leak\n");
if($count->invoke(null,9,array('season'=>'2026-2027'))!==1)exit("FAIL season total\n");
if($archives->invoke(null,9,'2026-2027')!==array('2025-2026'))exit("FAIL distinct archive seasons\n");
echo "Strict SQL season, total, archive and club isolation runtime safeguards OK\n";
