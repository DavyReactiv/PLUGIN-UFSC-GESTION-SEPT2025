<?php
/** Execute filter sanitization and pagination output, not source-string assertions. */
define('ABSPATH',__DIR__.'/');
function absint($v){return abs((int)$v);} function sanitize_text_field($v){return trim(strip_tags((string)$v));} function wp_unslash($v){return $v;} function esc_attr($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_attr__($v){return $v;} function esc_html($v){return htmlspecialchars((string)$v,ENT_QUOTES);} function esc_url($v){return $v;} function __($v){return $v;} function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}
require dirname(__DIR__).'/includes/frontend/class-frontend-shortcodes.php';
$_GET=array('ufsc_renew_search'=>'  Dupont ','ufsc_renew_sex'=>'X','ufsc_renew_practice'=>'competition','ufsc_renew_birth_from'=>'2025/01/01','ufsc_renew_birth_to'=>'2026-07-31','ufsc_renew_state'=>'incomplete');
$filter=new ReflectionMethod('UFSC_Frontend_Shortcodes','get_renewal_filters_from_request');$filter->setAccessible(true);$values=$filter->invoke(null);
if($values['search']!=='Dupont'||$values['gender']!==''||$values['practice']!=='competition'||$values['birth_from']!==''||$values['birth_to']!=='2026-07-31'||$values['renewal_state']!=='incomplete')exit("FAIL filters\n");
$pagination=new ReflectionMethod('UFSC_Frontend_Shortcodes','render_renewal_pagination');$pagination->setAccessible(true);$html=$pagination->invoke(null,2,3,array('ufsc_section'=>'licences-renouvellement','ufsc_renew_search'=>'Dupont','ufsc_renew_per_page'=>10),'https://example.test/club/');
foreach(array('Première page','Page précédente','aria-current="page"','rel="prev"','rel="next"','Page suivante','Dernière page','ufsc_renew_search=Dupont','ufsc_renew_per_page=10') as $expected){if(strpos($html,$expected)===false)exit("FAIL pagination {$expected}\n");}
echo "Renewal filter and clickable pagination runtime safeguards passed.\n";
