<?php
/** Business proof: exactly ten affiliation licences are included; the 11th is payable. */
define('ABSPATH', __DIR__.'/');
function sanitize_title($v){$v=strtolower(strtr($v,array('é'=>'e','è'=>'e','ê'=>'e','à'=>'a','î'=>'i')));return trim(preg_replace('/[^a-z0-9]+/','-',$v),'-');}
function remove_accents($v){return $v;} function apply_filters($n,$v){return $v;} function __($v){return $v;}
require dirname(__DIR__).'/inc/common/compliance.php';
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";};
$roles=array();
$sequence=array('adherent','pratiquant','president','adherent','secretaire','adherent','tresorier','adherent','coach','adherent');
foreach($sequence as $index=>$role){
    $a=ufsc_resolve_pack_credit($role,$roles);
    $assert(!empty($a['included']), 'licence '.($index+1).' incluse dans le pack');
    $roles[]=ufsc_normalize_club_role($role);
}
$assert(count($roles)===10,'exactement dix crédits inclus consommés');
$a=ufsc_resolve_pack_credit('adherent',$roles);
$assert(empty($a['included'])&&$a['bucket']==='payante','11e licence payante');
$a=ufsc_resolve_pack_credit('president',$roles);
$assert(empty($a['included']),'11e renouvellement ou nouvelle licence payante quel que soit le rôle');
$assert(!ufsc_role_requires_honorability('adherent')&&ufsc_role_requires_honorability('coach'),'honorabilité suit le rôle canonique');
echo "Affiliation pack 10-included/11th-paid runtime safeguards OK\n";
