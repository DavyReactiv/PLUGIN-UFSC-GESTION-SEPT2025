<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical selectable values for the licence sporting level. */
function ufsc_get_fighter_levels() {
	return (array) apply_filters( 'ufsc_sport_level_options', array(
		'pro'       => __( 'Pro', 'ufsc-clubs' ),
		'classe_a'  => __( 'Classe A', 'ufsc-clubs' ),
		'classe_b'  => __( 'Classe B', 'ufsc-clubs' ),
		'classe_c'  => __( 'Classe C', 'ufsc-clubs' ),
		'assaut'    => __( 'Assaut', 'ufsc-clubs' ),
		'veteran'   => __( 'Vétéran', 'ufsc-clubs' ),
	) );
}

/** Public business-name alias used by forms, cart and integrations. */
function ufsc_get_sport_level_options() { return ufsc_get_fighter_levels(); }

function ufsc_get_sport_level_required_message() {
	return __( 'Merci de vérifier et de sélectionner le niveau correspondant au boxeur avant de finaliser la demande de licence.', 'ufsc-clubs' );
}

function ufsc_get_sport_level_help() {
	return __( 'Merci de vérifier et de sélectionner le niveau correspondant au boxeur avant de finaliser la demande de licence.', 'ufsc-clubs' );
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
		'debutant' => 'debutant', // legacy only: no longer proposed for new licences.
		'assaut' => 'assaut',
		'classe_c' => 'classe_c',
		'classe_b' => 'classe_b',
		'classe_a' => 'classe_a',
		'pro' => 'pro',
		'professionnel' => 'pro',
		'veteran' => 'veteran',
	);
	return $aliases[ $key ] ?? sanitize_key( (string) $level );
}

/** Veteran starts at 41, consistently with the existing UFSC age-category grid. */
function ufsc_get_veteran_min_age() {
	return max( 18, (int) apply_filters( 'ufsc_fighter_level_veteran_min_age', 41 ) );
}

/** Calculate age on the actual day; never trust a browser-computed age. */
function ufsc_age_from_birth_date( $birth_date, $today = '' ) {
	$birth = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $birth_date );
	$now   = DateTimeImmutable::createFromFormat( '!Y-m-d', $today ?: gmdate( 'Y-m-d' ) );
	if ( ! $birth || ! $now || $birth > $now ) {
		return null;
	}
	return (int) $birth->diff( $now )->y;
}

/** Default only for a new/current-season request; historical rows are never backfilled. */
function ufsc_get_default_fighter_level( $birth_date ) {
	$age = ufsc_age_from_birth_date( $birth_date );
	if ( null === $age ) {
		return '';
	}
	return $age < 18 ? 'assaut' : 'classe_c';
}

function ufsc_is_selectable_fighter_level( $level ) {
	return isset( ufsc_get_sport_level_options()[ ufsc_normalize_fighter_level( $level ) ] );
}

/** Server-side business validation. Empty/legacy is accepted only for historical-compatible callers. */
function ufsc_validate_fighter_level( $level, $birth_date, $allow_empty = true ) {
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
	$age = ufsc_age_from_birth_date( $birth_date );
	if ( null === $age ) {
		return new WP_Error( 'ufsc_invalid_birth_date_for_level', __( 'Une date de naissance valide est requise pour contrôler le niveau sportif.', 'ufsc-clubs' ) );
	}
	$allowed = $age < 18 ? array( 'assaut' ) : array( 'assaut', 'classe_c', 'classe_b', 'classe_a', 'pro' );
	if ( $age >= ufsc_get_veteran_min_age() ) {
		$allowed[] = 'veteran';
	}
	if ( ! in_array( $level, $allowed, true ) ) {
		return new WP_Error( 'ufsc_invalid_fighter_level', $age < 18
			? __( 'Pour un mineur, le niveau de licence proposé par défaut est Assaut.', 'ufsc-clubs' )
			: sprintf( __( 'Sélectionnez un niveau compatible avec le licencié. Vétéran est disponible à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) );
	}
	return true;
}
