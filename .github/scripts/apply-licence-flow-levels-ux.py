from pathlib import Path
import re

ROOT = Path('.')

def read(path):
    return (ROOT / path).read_text(encoding='utf-8')

def write(path, text):
    (ROOT / path).write_text(text, encoding='utf-8')

def replace_once(text, old, new, label):
    if old not in text:
        raise SystemExit(f'Missing expected block: {label}')
    return text.replace(old, new, 1)

# 1. Canonical fighter-level rules: official selectable values + safe historical compatibility.
fighter = '''<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical selectable values for the licence sporting level. */
function ufsc_get_fighter_levels() {
\treturn (array) apply_filters( 'ufsc_sport_level_options', array(
\t\t'pro'       => __( 'Pro', 'ufsc-clubs' ),
\t\t'classe_a'  => __( 'Classe A', 'ufsc-clubs' ),
\t\t'classe_b'  => __( 'Classe B', 'ufsc-clubs' ),
\t\t'classe_c'  => __( 'Classe C', 'ufsc-clubs' ),
\t\t'assaut'    => __( 'Assaut', 'ufsc-clubs' ),
\t\t'veteran'   => __( 'Vétéran', 'ufsc-clubs' ),
\t) );
}

/** Public business-name alias used by forms, cart and integrations. */
function ufsc_get_sport_level_options() { return ufsc_get_fighter_levels(); }

function ufsc_get_sport_level_required_message() {
\treturn __( 'Merci de vérifier et de sélectionner le niveau correspondant au boxeur avant de finaliser la demande de licence.', 'ufsc-clubs' );
}

function ufsc_get_sport_level_help() {
\treturn __( 'Merci de vérifier et de sélectionner le niveau correspondant au boxeur avant de finaliser la demande de licence.', 'ufsc-clubs' );
}

function ufsc_fighter_level_label( $level ) {
\t$key = ufsc_normalize_fighter_level( $level );
\t$levels = ufsc_get_fighter_levels();
\tif ( isset( $levels[ $key ] ) ) {
\t\treturn $levels[ $key ];
\t}
\t// Historical compatibility: old rows are displayed, never rewritten automatically.
\tif ( 'debutant' === $key ) {
\t\treturn __( 'Débutant', 'ufsc-clubs' );
\t}
\treturn __( 'Non renseigné', 'ufsc-clubs' );
}

function ufsc_normalize_fighter_level( $level ) {
\t$raw = trim( (string) $level );
\t$raw = function_exists( 'remove_accents' ) ? remove_accents( $raw ) : strtr( $raw, array( 'é' => 'e', 'É' => 'E' ) );
\t$key = sanitize_key( str_replace( array( ' ', '-' ), '_', $raw ) );
\t$aliases = array(
\t\t'debutant' => 'debutant', // legacy only: no longer proposed for new licences.
\t\t'assaut' => 'assaut',
\t\t'classe_c' => 'classe_c',
\t\t'classe_b' => 'classe_b',
\t\t'classe_a' => 'classe_a',
\t\t'pro' => 'pro',
\t\t'professionnel' => 'pro',
\t\t'veteran' => 'veteran',
\t);
\treturn $aliases[ $key ] ?? sanitize_key( (string) $level );
}

/** Veteran starts at 41, consistently with the existing UFSC age-category grid. */
function ufsc_get_veteran_min_age() {
\treturn max( 18, (int) apply_filters( 'ufsc_fighter_level_veteran_min_age', 41 ) );
}

/** Calculate age on the actual day; never trust a browser-computed age. */
function ufsc_age_from_birth_date( $birth_date, $today = '' ) {
\t$birth = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $birth_date );
\t$now   = DateTimeImmutable::createFromFormat( '!Y-m-d', $today ?: gmdate( 'Y-m-d' ) );
\tif ( ! $birth || ! $now || $birth > $now ) {
\t\treturn null;
\t}
\treturn (int) $birth->diff( $now )->y;
}

/** Default only for a new/current-season request; historical rows are never backfilled. */
function ufsc_get_default_fighter_level( $birth_date ) {
\t$age = ufsc_age_from_birth_date( $birth_date );
\tif ( null === $age ) {
\t\treturn '';
\t}
\treturn $age < 18 ? 'assaut' : 'classe_c';
}

function ufsc_is_selectable_fighter_level( $level ) {
\treturn isset( ufsc_get_sport_level_options()[ ufsc_normalize_fighter_level( $level ) ] );
}

/** Server-side business validation. Empty/legacy is accepted only for historical-compatible callers. */
function ufsc_validate_fighter_level( $level, $birth_date, $allow_empty = true ) {
\t$level = ufsc_normalize_fighter_level( $level );
\tif ( '' === $level && $allow_empty ) {
\t\treturn true;
\t}
\tif ( 'debutant' === $level && $allow_empty ) {
\t\treturn true;
\t}
\tif ( ! ufsc_is_selectable_fighter_level( $level ) ) {
\t\treturn new WP_Error( 'ufsc_invalid_fighter_level', ufsc_get_sport_level_required_message() );
\t}
\t$age = ufsc_age_from_birth_date( $birth_date );
\tif ( null === $age ) {
\t\treturn new WP_Error( 'ufsc_invalid_birth_date_for_level', __( 'Une date de naissance valide est requise pour contrôler le niveau sportif.', 'ufsc-clubs' ) );
\t}
\t$allowed = $age < 18 ? array( 'assaut' ) : array( 'assaut', 'classe_c', 'classe_b', 'classe_a', 'pro' );
\tif ( $age >= ufsc_get_veteran_min_age() ) {
\t\t$allowed[] = 'veteran';
\t}
\tif ( ! in_array( $level, $allowed, true ) ) {
\t\treturn new WP_Error( 'ufsc_invalid_fighter_level', $age < 18
\t\t\t? __( 'Pour un mineur, le niveau de licence proposé par défaut est Assaut.', 'ufsc-clubs' )
\t\t\t: sprintf( __( 'Sélectionnez un niveau compatible avec le licencié. Vétéran est disponible à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) );
\t}
\treturn true;
}
'''
write('inc/common/fighter-level.php', fighter)

# 2. Pack allocation: exactly 10 included per active season; 11th+ paid. Keep bureau/libre labels informational.
path = 'inc/common/compliance.php'
text = read(path)
pattern = re.compile(r"/\*\* Pure 3-office/7-free allocation rule, also used by runtime tests\. \*/\nfunction ufsc_resolve_pack_credit\( \$role, \$included_roles \) \{.*?\n\}\n", re.S)
replacement = '''/** Canonical affiliation pack limit: ten included licences per club and season. */
function ufsc_get_pack_included_limit() {
\t$settings = function_exists( 'ufsc_get_woocommerce_settings' ) ? (array) ufsc_get_woocommerce_settings() : array();
\t$limit = isset( $settings['included_licenses'] ) ? (int) $settings['included_licenses'] : 10;
\treturn max( 0, (int) apply_filters( 'ufsc_pack_included_limit', $limit ) );
}

/**
 * Pure pack allocation rule. The first ten licences are included regardless of
 * creation order; the 11th and following licences are payable. Bureau/libre is
 * retained only as a presentation bucket and never reduces the ten-place quota.
 */
function ufsc_resolve_pack_credit( $role, $included_roles ) {
\t$role = ufsc_normalize_club_role( $role );
\t$included_roles = array_map( 'ufsc_normalize_club_role', (array) $included_roles );
\t$limit = ufsc_get_pack_included_limit();
\tif ( count( $included_roles ) >= $limit ) {
\t\treturn array( 'included' => false, 'bucket' => 'payante', 'role' => $role );
\t}
\t$office = array( 'president', 'secretaire', 'tresorier' );
\t$bucket = in_array( $role, $office, true ) && ! in_array( $role, $included_roles, true ) ? 'bureau' : 'libre';
\treturn array( 'included' => true, 'bucket' => $bucket, 'role' => $role );
}
'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Could not replace pack resolver')
write(path, text)

# 3. New licence: age-based default on finalisation, but drafts remain permissive.
path = 'includes/core/class-unified-handlers.php'
text = read(path)
old = """\t\tif ( function_exists( 'ufsc_validate_fighter_level' ) && ( ! $is_draft || ! empty( $data['fighter_level'] ) ) ) {\n\t\t\t$level_validation = ufsc_validate_fighter_level( $data['fighter_level'] ?? '', $date_naissance, true );\n\t\t\tif ( is_wp_error( $level_validation ) ) {\n\t\t\t\t$errors[] = $level_validation->get_error_message();\n\t\t\t}\n\t\t}\n"""
new = """\t\tif ( ! $is_draft && empty( $data['fighter_level'] ) && function_exists( 'ufsc_get_default_fighter_level' ) ) {\n\t\t\t$data['fighter_level'] = ufsc_get_default_fighter_level( $date_naissance );\n\t\t}\n\t\tif ( function_exists( 'ufsc_validate_fighter_level' ) && ( ! $is_draft || ! empty( $data['fighter_level'] ) ) ) {\n\t\t\t$level_validation = ufsc_validate_fighter_level( $data['fighter_level'] ?? '', $date_naissance, $is_draft );\n\t\t\tif ( is_wp_error( $level_validation ) ) {\n\t\t\t\t$errors[] = $level_validation->get_error_message();\n\t\t\t\t$structured_errors[] = array( 'field' => 'fighter_level', 'label' => __( 'Niveau du boxeur', 'ufsc-clubs' ), 'step' => 2, 'message' => $level_validation->get_error_message() );\n\t\t\t}\n\t\t}\n"""
text = replace_once(text, old, new, 'new licence fighter level validation')
write(path, text)

# 4. Renewal: preserve a valid current level; missing/legacy target gets age default. Never write source row.
path = 'includes/core/class-ufsc-renewal-service.php'
text = read(path)
old = """        $level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $raw['fighter_level'] ?? $source->fighter_level ?? '' ) : sanitize_key( (string) ( $raw['fighter_level'] ?? $source->fighter_level ?? '' ) );\n        if ( ! isset( ufsc_get_sport_level_options()[$level] ) ) { $errors['fighter_level'] = __( 'Le niveau sportif est obligatoire pour renouveler cette licence.', 'ufsc-clubs' ); }\n        $data['fighter_level'] = $level;\n"""
new = """        $birth_for_level = $data['date_naissance'] ?? ( $source->date_naissance ?? '' );\n        $level_source = array_key_exists( 'fighter_level', $raw ) ? $raw['fighter_level'] : ( $source->fighter_level ?? '' );\n        $level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $level_source ) : sanitize_key( (string) $level_source );\n        if ( function_exists( 'ufsc_is_selectable_fighter_level' ) && ! ufsc_is_selectable_fighter_level( $level ) && function_exists( 'ufsc_get_default_fighter_level' ) ) {\n            $level = ufsc_get_default_fighter_level( $birth_for_level );\n        }\n        if ( function_exists( 'ufsc_validate_fighter_level' ) ) {\n            $level_validation = ufsc_validate_fighter_level( $level, $birth_for_level, false );\n            if ( is_wp_error( $level_validation ) ) { $errors['fighter_level'] = $level_validation->get_error_message(); }\n        } elseif ( ! isset( ufsc_get_sport_level_options()[$level] ) ) {\n            $errors['fighter_level'] = __( 'Le niveau sportif est obligatoire pour renouveler cette licence.', 'ufsc-clubs' );\n        }\n        $data['fighter_level'] = $level;\n"""
text = replace_once(text, old, new, 'renewal fighter level resolution')
write(path, text)

# 5. Front: label it clearly, auto-propose default for old/missing renewal rows, and use canonical help.
path = 'includes/frontend/class-frontend-shortcodes.php'
text = read(path)
old = "$level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $row->fighter_level ?? '' ) : sanitize_key( (string) ( $row->fighter_level ?? '' ) );"
new = "$level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $row->fighter_level ?? '' ) : sanitize_key( (string) ( $row->fighter_level ?? '' ) ); if ( function_exists( 'ufsc_is_selectable_fighter_level' ) && ! ufsc_is_selectable_fighter_level( $level ) && function_exists( 'ufsc_get_default_fighter_level' ) ) { $level = ufsc_get_default_fighter_level( $row->date_naissance ?? '' ); }"
text = replace_once(text, old, new, 'renewal displayed default')
text = text.replace("<?php esc_html_e( 'Niveau sportif', 'ufsc-clubs' ); ?>", "<?php esc_html_e( 'Niveau du boxeur', 'ufsc-clubs' ); ?>")
old_help = "<small data-ufsc-level-help><?php echo esc_html( sprintf( __( 'Mineur : Assaut. Majeur : Classe C, Classe B ou Classe A. Vétéran à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) ); ?></small>"
new_help = "<small data-ufsc-level-help><?php echo esc_html( ufsc_get_sport_level_help() ); ?></small>"
text = replace_once(text, old_help, new_help, 'new licence help text')
write(path, text)

# 6. New licence JS: default ASSAUT for minor / CLASSE C for adult, without overriding a manual choice.
path = 'assets/js/ufsc-license-form.js'
text = read(path)
pattern = re.compile(r"\tfunction initFighterLevel\(\) \{.*?\n\t\}\n\n\tfunction initCompliancePanels", re.S)
replacement = '''\tfunction initFighterLevel() {
\t\tconst birth = $('#date_naissance');
\t\tconst level = $('[data-ufsc-fighter-level]');
\t\tif (!birth.length || !level.length) return;
\t\tlet userSelected = Boolean(level.val());
\t\tlevel.on('change', function() { userSelected = Boolean(level.val()); });
\t\tfunction refreshLevelOptions() {
\t\t\tconst date = birth.val() ? new Date(birth.val() + 'T00:00:00') : null;
\t\t\tconst now = new Date();
\t\t\tlet age = date && !isNaN(date.getTime()) ? now.getFullYear() - date.getFullYear() : null;
\t\t\tif (date && (now.getMonth() < date.getMonth() || (now.getMonth() === date.getMonth() && now.getDate() < date.getDate()))) age--;
\t\t\tlevel.find('option').prop('hidden', false);
\t\t\tif (age === null || age < 0) return;
\t\t\tlevel.find('option[value="pro"], option[value^="classe_"]').prop('hidden', age < 18);
\t\t\tconst veteranMinAge = parseInt(level.attr('data-veteran-min-age'), 10) || 41;
\t\t\tlevel.find('option[value="veteran"]').prop('hidden', age < veteranMinAge);
\t\t\tif (level.find('option:selected').prop('hidden')) { level.val(''); userSelected = false; }
\t\t\tif (!userSelected && !level.val()) {
\t\t\t\tlevel.val(age < 18 ? 'assaut' : 'classe_c').trigger('change.select2');
\t\t\t}
\t\t}
\t\tbirth.on('change input', function() { if (!level.data('ufsc-manual-level')) userSelected = false; refreshLevelOptions(); });
\t\tlevel.on('change', function() { if (document.activeElement === level[0]) level.data('ufsc-manual-level', true); });
\t\trefreshLevelOptions();
\t}

\tfunction initCompliancePanels'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Could not patch fighter level JS')
write(path, text)

# 7. Admin export presentation: include Pro and legacy Débutant labels.
path = 'includes/admin/class-sql-admin.php'
text = read(path)
old = "'fighter_level'              => \"CASE l.fighter_level WHEN 'assaut' THEN 'Assaut' WHEN 'classe_c' THEN 'Classe C' WHEN 'classe_b' THEN 'Classe B' WHEN 'classe_a' THEN 'Classe A' WHEN 'veteran' THEN 'Vétéran' ELSE 'Non renseigné' END AS fighter_level\","
new = "'fighter_level'              => \"CASE l.fighter_level WHEN 'pro' THEN 'Pro' WHEN 'classe_a' THEN 'Classe A' WHEN 'classe_b' THEN 'Classe B' WHEN 'classe_c' THEN 'Classe C' WHEN 'assaut' THEN 'Assaut' WHEN 'veteran' THEN 'Vétéran' WHEN 'debutant' THEN 'Débutant' ELSE 'Non renseigné' END AS fighter_level\","
text = replace_once(text, old, new, 'admin fighter level export case')
write(path, text)

# 8. Final navigation contract: append last, no !important, equal tabs and stable breakpoints.
path = 'assets/css/ufsc-front.css'
text = read(path)
marker = '/* Club account navigation final contract — equal tabs, stable responsive grid. */'
if marker not in text:
    text = text.rstrip() + '''\n\n/* Club account navigation final contract — equal tabs, stable responsive grid. */
.ufsc-club-portal .ufsc-club-account__nav.ufsc-club-portal__nav {
 display: grid;
 grid-template-columns: repeat(6, minmax(0, 1fr));
 gap: 8px;
 align-items: stretch;
 inline-size: 100%;
 max-inline-size: 100%;
 overflow: visible;
}
.ufsc-club-portal .ufsc-club-account__nav.ufsc-club-portal__nav > a {
 display: flex;
 align-items: center;
 justify-content: center;
 inline-size: 100%;
 min-inline-size: 0;
 min-block-size: 48px;
 block-size: 100%;
 padding: 10px 12px;
 text-align: center;
 line-height: 1.2;
 white-space: normal;
 overflow-wrap: anywhere;
}
@media (max-width: 899px) {
 .ufsc-club-portal .ufsc-club-account__nav.ufsc-club-portal__nav { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 599px) {
 .ufsc-club-portal .ufsc-club-account__nav.ufsc-club-portal__nav { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 420px) {
 .ufsc-club-portal .ufsc-club-account__nav.ufsc-club-portal__nav { grid-template-columns: minmax(0, 1fr); }
}\n'''
write(path, text)

# 9. Update focused runtime tests.
pack_test = '''<?php
/** Business proof: exactly ten affiliation licences are included; the 11th is payable. */
define('ABSPATH', __DIR__.'/');
function sanitize_title($v){$v=strtolower(strtr($v,array('é'=>'e','è'=>'e','ê'=>'e','à'=>'a','î'=>'i')));return trim(preg_replace('/[^a-z0-9]+/','-',$v),'-');}
function remove_accents($v){return $v;} function apply_filters($n,$v){return $v;} function __($v){return $v;}
require dirname(__DIR__).'/inc/common/compliance.php';
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\\n");exit(1);}echo "PASS: $m\\n";};
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
echo "Affiliation pack 10-included/11th-paid runtime safeguards OK\\n";
'''
write('tests/test-affiliation-pack-runtime.php', pack_test)

fighter_test = '''<?php
define( 'ABSPATH', __DIR__ );
function __( $text ) { return $text; }
function sanitize_key( $value ) { return strtolower( preg_replace( '/[^a-z0-9_\\-]/', '', (string) $value ) ); }
function apply_filters( $hook, $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error { private $message; public function __construct($code,$message){$this->message=$message;} public function get_error_message(){return $this->message;} }
require dirname( __DIR__ ) . '/inc/common/fighter-level.php';
$birth = static function ( $age ) { return gmdate( 'Y-m-d', strtotime( '-' . $age . ' years' ) ); };
$assert = static function ( $condition, $message ) { if(!$condition){fwrite(STDERR,"FAIL: {$message}\\n");exit(1);} echo "PASS: {$message}\\n"; };
$assert( array_keys( ufsc_get_sport_level_options() ) === array( 'pro','classe_a','classe_b','classe_c','assaut','veteran' ), 'liste officielle PRO A B C ASSAUT VETERAN' );
$assert( 'assaut' === ufsc_get_default_fighter_level( $birth(17) ), 'mineur propose Assaut par défaut' );
$assert( 'classe_c' === ufsc_get_default_fighter_level( $birth(18) ), 'majeur propose Classe C par défaut' );
$assert( true === ufsc_validate_fighter_level( 'assaut', $birth(17), false ), 'mineur avec Assaut' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'classe_a', $birth(17), false ) ), 'mineur refusé avec Classe A' );
foreach(array('assaut','classe_c','classe_b','classe_a','pro') as $level){$assert(true===ufsc_validate_fighter_level($level,$birth(25),false),"majeur accepté en {$level}");}
$assert( is_wp_error( ufsc_validate_fighter_level( 'veteran', $birth(40), false ) ), 'Vétéran refusé avant 41 ans' );
$assert( true === ufsc_validate_fighter_level( 'veteran', $birth(41), false ), 'Vétéran accepté à 41 ans' );
$assert( true === ufsc_validate_fighter_level( '', $birth(60), true ), 'ancienne licence vide acceptée en lecture historique' );
$assert( true === ufsc_validate_fighter_level( 'debutant', $birth(30), true ), 'ancienne valeur Débutant reste compatible historiquement' );
$assert( 'Débutant' === ufsc_fighter_level_label( 'debutant' ), 'ancienne valeur Débutant reste lisible' );
$assert( 'Non renseigné' === ufsc_fighter_level_label( null ), 'ancienne licence sans niveau reste Non renseigné' );
echo "Fighter-level defaults and history safeguards OK\\n";
'''
write('tests/test-fighter-level-runtime.php', fighter_test)

journey_test = '''<?php
/** Static contract for the unified new/renewal licence journey. */
$root = dirname(__DIR__);
$compliance = file_get_contents($root.'/inc/common/compliance.php');
$cart = file_get_contents($root.'/inc/woocommerce/cart-integration.php');
$handlers = file_get_contents($root.'/includes/core/class-unified-handlers.php');
$renewal = file_get_contents($root.'/includes/core/class-ufsc-renewal-service.php');
$front = file_get_contents($root.'/includes/frontend/class-frontend-shortcodes.php');
$css = file_get_contents($root.'/assets/css/ufsc-front.css');
$assert=static function($ok,$m){if(!$ok){fwrite(STDERR,"FAIL: $m\\n");exit(1);}echo "PASS: $m\\n";};
$assert(strpos($compliance,'count( $included_roles ) >= $limit')!==false,'pack allocation uses total included count');
$assert(strpos($cart,'ufsc_allocate_pack_credit( $target_id, $club_id, $season, $target_role )')!==false,'renewal uses canonical pack allocation');
$assert(strpos($handlers,'ufsc_allocate_pack_credit')!==false,'new licence uses canonical pack allocation');
$assert(strpos($cart,'WC()->cart->add_to_cart')!==false,'paid renewal has WooCommerce add_to_cart path');
$assert(strpos($handlers,'ufsc_add_licence_ids_to_cart_idempotent')!==false,'paid new licence has idempotent cart path');
$assert(strpos($renewal,'never applied to the source row')!==false,'historical renewal source remains immutable');
$assert(strpos($front,'Niveau du boxeur')!==false,'front displays boxer level');
$assert(strpos($front,'ufsc_get_sport_level_help()')!==false,'front displays explicit level guidance');
$assert(strpos($css,'Club account navigation final contract')!==false,'club navigation final alignment contract exists');
$assert(substr_count($css,'!important')<=37,'navigation correction adds no important declarations');
echo "Unified licence journey safeguards OK\\n";
'''
write('tests/test-licence-flow-levels-ux-static.php', journey_test)

print('Patch applied successfully')
