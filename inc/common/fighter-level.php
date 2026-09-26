<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical selectable values for the licence sporting level. */
function ufsc_get_fighter_levels() {
	return (array) apply_filters( 'ufsc_sport_level_options', array(
		'pro'       => __( 'Pro', 'ufsc-clubs' ),
		'classe_a'  => __( 'Senior Combat — Classe A', 'ufsc-clubs' ),
		'classe_b'  => __( 'Senior Combat — Classe B', 'ufsc-clubs' ),
		'combat'    => __( 'Cadet / Junior Combat', 'ufsc-clubs' ),
		'assaut'    => __( 'Assaut', 'ufsc-clubs' ),
		'veteran'   => __( 'Vétéran Assaut', 'ufsc-clubs' ),
	) );
}

/** Public business-name alias used by forms, cart and integrations. */
function ufsc_get_sport_level_options() { return ufsc_get_fighter_levels(); }

function ufsc_get_sport_level_required_message() {
	return __( 'Merci de vérifier et de sélectionner la catégorie de pratique correspondant au licencié avant de finaliser la demande de licence.', 'ufsc-clubs' );
}

function ufsc_get_sport_level_help() {
	return __( 'Pré-poussin à Cadet 1re année : Assaut ; Cadet 2e année et Junior : Assaut ou Combat ; Senior 18–40 ans : Assaut, Classe B, Classe A ou Pro ; Vétéran : Assaut.', 'ufsc-clubs' );
}

function ufsc_fighter_level_label( $level ) {
	$key = ufsc_normalize_fighter_level( $level );
	$levels = ufsc_get_fighter_levels();
	if ( isset( $levels[ $key ] ) ) {
		return $levels[ $key ];
	}
	// Historical compatibility: old rows are displayed, never rewritten automatically.
	if ( 'debutant' === $key ) {
		return __( 'Débutant', 'ufsc-clubs' );
	}
	return __( 'Non renseigné', 'ufsc-clubs' );
}

function ufsc_normalize_fighter_level( $level ) {
	$raw = trim( (string) $level );
	$raw = function_exists( 'remove_accents' ) ? remove_accents( $raw ) : strtr( $raw, array( 'é' => 'e', 'É' => 'E' ) );
	$key = sanitize_key( str_replace( array( ' ', '-' ), '_', $raw ) );
	$aliases = array(
		'debutant' => 'debutant', // legacy only: never rewritten automatically.
		'assaut' => 'assaut',
		'combat' => 'combat',
		'cadet_combat' => 'combat',
		'cadette_combat' => 'combat',
		'junior_combat' => 'combat',
		'classe_c' => 'classe_b', // 2026-2027: Classe C supprimée, compatibilité des anciennes valeurs.
		'senior_combat_classe_c' => 'classe_b',
		'classe_b' => 'classe_b',
		'senior_combat_classe_b' => 'classe_b',
		'classe_a' => 'classe_a',
		'senior_combat_classe_a' => 'classe_a',
		'pro' => 'pro',
		'professionnel' => 'pro',
		'veteran' => 'veteran',
		'veteran_assaut' => 'veteran',
	);
	return $aliases[ $key ] ?? sanitize_key( (string) $level );
}

/** Veteran starts at 41, consistently with the 2026-2027 age grid. */
function ufsc_get_veteran_min_age() {
	return max( 18, (int) apply_filters( 'ufsc_fighter_level_veteran_min_age', 41 ) );
}

/** Cadet combat starts at 15 (2nd cadet year in the 2026-2027 ring grid). */
function ufsc_get_combat_min_age() {
	return max( 14, (int) apply_filters( 'ufsc_fighter_level_combat_min_age', 15 ) );
}

/** Calculate age on the actual day; never trust a browser-computed age. */
function ufsc_get_season_start_year_for_levels( $season = '' ) {
	$season = trim( str_replace( '/', '-', (string) $season ) );
	if ( preg_match( '/^(\d{4})-\d{4}$/', $season, $matches ) ) {
		return (int) $matches[1];
	}
	if ( class_exists( 'UFSC_Season_Service' ) ) {
		$current = trim( str_replace( '/', '-', (string) UFSC_Season_Service::get_current_season() ) );
		if ( preg_match( '/^(\d{4})-\d{4}$/', $current, $matches ) ) {
			return (int) $matches[1];
		}
	}
	return (int) gmdate( 'Y' );
}

/** Competition age: start year of season minus birth year, per 2026-2027 referential. */
function ufsc_competition_age_from_birth_date( $birth_date, $season = '' ) {
	if ( ! preg_match( '/^(\d{4})-\d{2}-\d{2}$/', (string) $birth_date, $matches ) ) {
		return null;
	}
	$birth_year = (int) $matches[1];
	$season_year = ufsc_get_season_start_year_for_levels( $season );
	if ( $birth_year <= 0 || $birth_year > $season_year ) {
		return null;
	}
	return $season_year - $birth_year;
}

function ufsc_age_from_birth_date( $birth_date, $today = '' ) {
	$birth = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $birth_date );
	$now   = DateTimeImmutable::createFromFormat( '!Y-m-d', $today ?: gmdate( 'Y-m-d' ) );
	if ( ! $birth || ! $now || $birth > $now ) {
		return null;
	}
	return (int) $birth->diff( $now )->y;
}

/** Default only for a new/current-season request; historical rows are never backfilled. */
function ufsc_get_default_fighter_level( $birth_date, $season = '' ) {
	$age = ufsc_competition_age_from_birth_date( $birth_date, $season );
	if ( null === $age ) {
		return '';
	}
	if ( $age >= ufsc_get_veteran_min_age() ) {
		return 'veteran';
	}
	return $age < 18 ? 'assaut' : 'classe_b';
}

function ufsc_get_allowed_fighter_levels( $birth_date, $season = '' ) {
	$age = ufsc_competition_age_from_birth_date( $birth_date, $season );
	if ( null === $age ) {
		return array_keys( ufsc_get_sport_level_options() );
	}
	if ( $age < ufsc_get_combat_min_age() ) {
		return array( 'assaut' );
	}
	if ( $age < 18 ) {
		return array( 'assaut', 'combat' );
	}
	if ( $age <= 40 ) {
		return array( 'assaut', 'classe_b', 'classe_a', 'pro' );
	}
	return array( 'assaut', 'veteran' );
}

function ufsc_get_sport_level_options_for_athlete( $birth_date, $season = '' ) {
	$options = ufsc_get_sport_level_options();
	return array_intersect_key( $options, array_flip( ufsc_get_allowed_fighter_levels( $birth_date, $season ) ) );
}

function ufsc_is_selectable_fighter_level( $level ) {
	return isset( ufsc_get_sport_level_options()[ ufsc_normalize_fighter_level( $level ) ] );
}

/**
 * Server-side business validation.
 *
 * Historical rows are never rewritten automatically. The $allow_empty flag is
 * intentionally kept for draft/admin compatibility; the actual age/category
 * rules still apply whenever a current selectable value is submitted.
 */
function ufsc_validate_fighter_level( $level, $birth_date, $allow_empty = true, $season = '' ) {
	$level = ufsc_normalize_fighter_level( $level );
	if ( '' === $level && $allow_empty ) {
		return true;
	}
	if ( 'debutant' === $level && $allow_empty ) {
		return true;
	}
	if ( ! ufsc_is_selectable_fighter_level( $level ) ) {
		return new WP_Error( 'ufsc_invalid_fighter_level', ufsc_get_sport_level_required_message() );
	}
	$age = ufsc_competition_age_from_birth_date( $birth_date, $season );
	if ( null === $age ) {
		return new WP_Error( 'ufsc_invalid_birth_date_for_level', __( 'Une date de naissance valide est requise pour contrôler la catégorie de pratique.', 'ufsc-clubs' ) );
	}

	// Assaut remains available from the youth categories through adults.
	$allowed = array( 'assaut' );

	// Ring combat is available from Cadet/Cadette 2e année through Juniors.
	if ( $age >= ufsc_get_combat_min_age() && $age < 18 ) {
		$allowed[] = 'combat';
	}

	// Senior combat is limited to 18–40 years for the 2026-2027 referential.
	if ( $age >= 18 && $age <= 40 ) {
		$allowed = array_merge( $allowed, array( 'classe_b', 'classe_a', 'pro' ) );
	}

	// Keep the historical value while presenting it explicitly as Vétéran Assaut.
	if ( $age >= ufsc_get_veteran_min_age() ) {
		$allowed[] = 'veteran';
	}

	if ( ! in_array( $level, array_unique( $allowed ), true ) ) {
		if ( $age < ufsc_get_combat_min_age() ) {
			$message = __( 'Pour cette catégorie d’âge, la pratique proposée est Assaut.', 'ufsc-clubs' );
		} elseif ( $age < 18 ) {
			$message = __( 'Pour un Cadet/Cadette 2e année ou Junior, sélectionnez Assaut ou Combat.', 'ufsc-clubs' );
		} elseif ( $age <= 40 ) {
			$message = __( 'Pour un Senior 18–40 ans, sélectionnez Assaut, Classe B, Classe A ou Pro.', 'ufsc-clubs' );
		} else {
			$message = __( 'Après 40 ans, les classes Senior Combat B/A/Pro ne sont plus proposées ; sélectionnez une pratique Assaut compatible.', 'ufsc-clubs' );
		}
		return new WP_Error( 'ufsc_invalid_fighter_level', $message );
	}
	return true;
}
