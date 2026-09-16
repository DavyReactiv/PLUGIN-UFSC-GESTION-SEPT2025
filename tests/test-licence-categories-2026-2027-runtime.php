<?php
// Runtime guard for the 2026-2027 licence category update.
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
if ( ! function_exists( '__' ) ) { function __( $text ) { return $text; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( 'remove_accents' ) ) { function remove_accents( $value ) { return strtr( $value, array( 'é'=>'e','É'=>'E','è'=>'e','ê'=>'e','à'=>'a','ç'=>'c' ) ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $value ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ); } }
if ( ! class_exists( 'WP_Error' ) ) { class WP_Error { public $code; public $message; public function __construct( $code, $message ) { $this->code=$code; $this->message=$message; } public function get_error_message(){ return $this->message; } } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $value ) { return $value instanceof WP_Error; } }

require dirname( __DIR__ ) . '/inc/common/fighter-level.php';
require dirname( __DIR__ ) . '/includes/core/class-ufsc-category-repository.php';

$failed = false;
$assert = static function ( $condition, $message ) use ( &$failed ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); $failed = true; }
};
$birth = static function ( $age ) { return gmdate( 'Y-m-d', strtotime( '-' . (int) $age . ' years' ) ); };

$options = ufsc_get_sport_level_options();
$assert( isset( $options['assaut'] ), 'Assaut remains selectable' );
$assert( isset( $options['combat'] ), 'Cadet/Junior Combat is selectable' );
$assert( isset( $options['classe_c'], $options['classe_b'], $options['classe_a'] ), 'Senior Combat classes A/B/C remain selectable' );
$assert( isset( $options['pro'], $options['veteran'] ), 'historical Pro/Veteran values remain represented' );
$assert( 'combat' === ufsc_normalize_fighter_level( 'cadet combat' ), 'cadet combat alias normalizes safely' );
$assert( 'classe_c' === ufsc_normalize_fighter_level( 'senior combat classe c' ), 'senior class C alias normalizes safely' );

$assert( is_wp_error( ufsc_validate_fighter_level( 'combat', $birth( 14 ), false ) ), 'combat is blocked before cadet 2nd year' );
$assert( true === ufsc_validate_fighter_level( 'combat', $birth( 15 ), false ), 'combat is allowed from age 15' );
$assert( true === ufsc_validate_fighter_level( 'combat', $birth( 17 ), false ), 'junior combat remains allowed at 17' );
$assert( is_wp_error( ufsc_validate_fighter_level( 'combat', $birth( 18 ), false ) ), 'generic cadet/junior combat stops at senior age' );
$assert( true === ufsc_validate_fighter_level( 'classe_c', $birth( 18 ), false ), 'senior combat class C starts at adult age' );
$assert( 'veteran' === ufsc_get_default_fighter_level( $birth( 41 ) ), 'veteran assaut is the adult default from 41' );
$assert( true === ufsc_validate_fighter_level( 'debutant', $birth( 30 ), true ), 'legacy debutant stays accepted for historical/admin compatibility' );

$pre_2026 = UFSC_Category_Repository::detect_age_category( '2020-05-10', 'M', '2026-2027' );
$assert( is_array( $pre_2026 ) && 'pre_poussins' === $pre_2026['key'], '2026-2027 pre-poussin birth years are recognized' );
$junior_2026 = UFSC_Category_Repository::detect_age_category( '2009-05-10', 'F', '2026/2027' );
$assert( is_array( $junior_2026 ) && 'juniors_filles' === $junior_2026['key'], '2026-2027 junior female is recognized' );
$senior_2026 = UFSC_Category_Repository::detect_age_category( '1986-05-10', 'M', '2026-2027' );
$assert( is_array( $senior_2026 ) && 'seniors_masculins' === $senior_2026['key'], '2026-2027 senior male lower birth-year bound is recognized' );
$veteran_2026 = UFSC_Category_Repository::detect_age_category( '1985-05-10', 'F', '2026-2027' );
$assert( is_array( $veteran_2026 ) && 'veterans_feminines' === $veteran_2026['key'], '2026-2027 veteran female is recognized' );

// Regression guard: historical season remains unchanged.
$historic = UFSC_Category_Repository::detect_age_category( '2018-05-10', 'M', '2025-2026' );
$assert( is_array( $historic ) && 'pre_poussins' === $historic['key'], '2025-2026 historical age grid remains available' );

if ( $failed ) { exit( 1 ); }
echo "OK licence categories 2026-2027 and historical compatibility\n";
