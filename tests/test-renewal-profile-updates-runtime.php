<?php
/** Autonomous tests for safe, non-destructive renewal profile changes. */
define('ABSPATH', __DIR__ . '/');
function __($v){return $v;} function apply_filters($h,$v){return $v;} function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function sanitize_text_field($v){return trim(strip_tags((string)$v));} function sanitize_email($v){return filter_var((string)$v,FILTER_SANITIZE_EMAIL);}
function is_email($v){return false !== filter_var($v,FILTER_VALIDATE_EMAIL);} function wp_unslash($v){return $v;} function absint($v){return abs((int)$v);}
class WP_Error { function __construct($c,$m){} }
class UFSC_Category_Repository { static function normalize_weight($v){$v=str_replace(',','.',trim((string)$v));return is_numeric($v)?(float)$v:null;} }
class UFSC_Identifier_Resolver { static function read(){return 'UFSC-42';} }
function ufsc_get_sport_level_options(){return array('assaut'=>'Assaut','classe_c'=>'Classe C','classe_b'=>'Classe B','classe_a'=>'Classe A','veteran'=>'Vétéran');}
require dirname(__DIR__) . '/includes/core/class-ufsc-renewal-service.php';
$archive=(object)array('id'=>42,'club_id'=>9,'adresse'=>'5 allée des Frênes','email'=>'old@example.test','tel_mobile'=>'06 00 00 00 00','fighter_level'=>'assaut','poids'=>'32.5','nom'=>'Vieira','prenom'=>'Janaelle','date_naissance'=>'2012-05-06','sexe'=>'F','numero_licence_asptt'=>'ASPTT-OLD','statut'=>'validated');
$before=serialize($archive);
$result=UFSC_Renewal_Service::sanitize_renewal_updates($archive,array('adresse'=>'8 rue des Sports','email'=>'new@example.test','tel_mobile'=>'06x12-34','fighter_level'=>'classe_c','poids'=>'34,5','competition'=>'1'));
if($result['errors']||'8 rue des Sports'!==$result['data']['adresse']||34.5!==$result['data']['poids']||'0612-34'!==$result['data']['tel_mobile']) exit("FAIL valid updates\n");
if($before!==serialize($archive)) exit("FAIL archive mutated\n");
if(!isset($result['changes']['adresse'],$result['changes']['poids'])) exit("FAIL change summary\n");
$bad=UFSC_Renewal_Service::sanitize_renewal_updates($archive,array('email'=>'not-an-email','fighter_level'=>'unknown','poids'=>'-4'));
if(!isset($bad['errors']['email'],$bad['errors']['fighter_level'],$bad['errors']['poids'])) exit("FAIL invalid fields\n");
$sensitive=UFSC_Renewal_Service::sanitize_renewal_updates($archive,array('nom'=>'Nouveau','fighter_level'=>'assaut','poids'=>'32.5'));
if(!isset($sensitive['errors']['confirm_identity_change'])||!$sensitive['sensitive_identity_change']) exit("FAIL sensitive confirmation\n");
$confirmed=UFSC_Renewal_Service::sanitize_renewal_updates($archive,array('nom'=>'Nouveau','fighter_level'=>'assaut','poids'=>'32.5','confirm_identity_change'=>'1','numero_licence_ufsc'=>'FORGED','numero_licence_asptt'=>'FORGED','statut'=>'active'));
if($confirmed['errors']||isset($confirmed['data']['numero_licence_ufsc'],$confirmed['data']['numero_licence_asptt'],$confirmed['data']['statut'])) exit("FAIL immutable injection\n");
echo "Renewal profile update runtime safeguards passed.\n";
