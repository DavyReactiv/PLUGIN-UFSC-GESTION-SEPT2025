<?php
/** Runtime business proof for honorability scope and canonical settings preservation. */
define( 'ABSPATH', __DIR__ );
$GLOBALS['options'] = array();
function sanitize_title($v){ return trim(preg_replace('/-+/','-',strtolower(str_replace(array(' ','_'),'-',$v))),'-'); }
function remove_accents($v){ return strtr($v,array('é'=>'e','è'=>'e','ê'=>'e','É'=>'E','î'=>'i')); }
function sanitize_key($v){ return preg_replace('/[^a-z0-9_-]/','',strtolower($v)); }
function sanitize_text_field($v){ return trim((string)$v); }
function apply_filters($tag,$value){ return $value; }
function __($v){ return $v; }
function absint($v){ return abs((int)$v); }
function get_option($key,$default=false){ return $GLOBALS['options'][$key] ?? $default; }
function update_option($key,$value){ $GLOBALS['options'][$key]=$value; return true; }
function wp_parse_args($args,$defaults=array()){ return array_merge($defaults,(array)$args); }
function current_time(){ return time(); }
function ufsc_get_licence_season_label($row){ return $row->season ?? ''; }
require dirname(__DIR__).'/inc/common/compliance.php';
$rows=array(
 (object)array('id'=>1,'role'=>'Président','season'=>'2026-2027'),
 (object)array('id'=>2,'role'=>'Adhérent','season'=>'2026-2027'),
 (object)array('id'=>3,'role'=>'Coach','season'=>'2025-2026'),
);
$kpis=ufsc_get_honorability_document_kpis($rows,'2026-2027');
if($kpis['required']!==1 || $kpis['missing']!==1) exit("FAIL honorability club/season/role scope\n");
if(ufsc_role_requires_honorability('adhérent') || !ufsc_role_requires_honorability('  RESPONSABLE   TECHNIQUE ')) exit("FAIL normalized honorability roles\n");
echo "Final DEV corrective runtime safeguards OK\n";
