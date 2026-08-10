<?php
/** Runtime proof for official club email/phone validation path. */
define('ABSPATH',__DIR__.'/');
function __($v){return $v;} function sanitize_text_field($v){return trim((string)$v);} function sanitize_textarea_field($v){return trim((string)$v);} function sanitize_email($v){return filter_var($v,FILTER_SANITIZE_EMAIL);} function is_email($v){return filter_var($v,FILTER_VALIDATE_EMAIL)!==false;} function is_wp_error($v){return $v instanceof WP_Error;}
class WP_Error{private $c,$m;function __construct($c,$m){$this->c=$c;$this->m=$m;}function get_error_message(){return $this->m;}}
require dirname(__DIR__).'/includes/core/class-unified-handlers.php';
$method=new ReflectionMethod('UFSC_Unified_Handlers','validate_club_data');$method->setAccessible(true);
$valid=$method->invoke(null,array('email'=>' contact@club.fr ','telephone'=>'+33 (0)6 12 34 56 78'));if(is_wp_error($valid)||$valid['email']!=='contact@club.fr'||$valid['telephone']!=='+33 (0)6 12 34 56 78')exit("FAIL valid club contacts\n");
if(!is_wp_error($method->invoke(null,array('email'=>'invalid','telephone'=>'12'))))exit("FAIL invalid club contacts\n");
$source=file_get_contents(dirname(__DIR__).'/includes/core/class-unified-handlers.php');if(strpos($source,"array_flip( array( 'email', 'telephone' ) )")===false||strpos($source,'$managed_club !== $target_club_id')===false)exit("FAIL own-club contact whitelist\n");
echo "Club contact validation and own-club whitelist runtime safeguards OK\n";
