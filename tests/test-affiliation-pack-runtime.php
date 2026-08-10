<?php
/** Business proof for the reserved 3-office + 7-free affiliation pack. */
define('ABSPATH', __DIR__.'/');
function sanitize_title($v){$v=strtolower(strtr($v,array('é'=>'e','è'=>'e','ê'=>'e','à'=>'a','î'=>'i')));return trim(preg_replace('/[^a-z0-9]+/','-',$v),'-');}
function remove_accents($v){return $v;} function apply_filters($n,$v){return $v;} function __($v){return $v;}
require dirname(__DIR__).'/inc/common/compliance.php';
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}};
$roles=array();
foreach(array('adherent','pratiquant','adherent','adherent','adherent','adherent','adherent') as $role){$a=ufsc_resolve_pack_credit($role,$roles);$assert($a['included']&&$a['bucket']==='libre','seven free credits');$roles[]=ufsc_normalize_club_role($role);}
$a=ufsc_resolve_pack_credit('adherent',$roles);$assert(!$a['included']&&$a['bucket']==='payante','eighth ordinary licence is payable');
foreach(array('président'=>'president','secretaire'=>'secretaire','trésorière'=>'tresorier') as $raw=>$canonical){$a=ufsc_resolve_pack_credit($raw,$roles);$assert($a['included']&&$a['bucket']==='bureau'&&$a['role']===$canonical,"reserved $canonical credit");$roles[]=$canonical;}
$assert(count($roles)===10,'pack total is ten');
$a=ufsc_resolve_pack_credit('président',$roles);$assert(!$a['included'],'duplicate office role cannot consume an office credit or an exhausted free credit');
$assert(!ufsc_role_requires_honorability('adherent')&&ufsc_role_requires_honorability('coach'),'honorability follows canonical role');
echo "Affiliation pack 3-office/7-free runtime safeguards OK\n";
