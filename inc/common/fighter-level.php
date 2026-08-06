<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical values for the licence sporting level. */
function ufsc_get_fighter_levels() {
	return (array) apply_filters( 'ufsc_sport_level_options', array(
		'debutant'  => __( 'Débutant', 'ufsc-clubs' ),
		'assaut'    => __( 'Assaut', 'ufsc-clubs' ),
		'classe_c'  => __( 'Classe C', 'ufsc-clubs' ),
		'classe_b'  => __( 'Classe B', 'ufsc-clubs' ),
		'classe_a'  => __( 'Classe A', 'ufsc-clubs' ),
		'pro'       => __( 'Pro', 'ufsc-clubs' ),
		'veteran'   => __( 'Vétéran', 'ufsc-clubs' ),
	) );
}

/** Public business-name alias used by forms, cart and integrations. */
function ufsc_get_sport_level_options() { return ufsc_get_fighter_levels(); }

function ufsc_get_sport_level_required_message() {
	return __( 'Le niveau sportif est obligatoire avant de poursuivre.', 'ufsc-clubs' );
}

function ufsc_get_sport_level_help() {
	return __( 'Sélectionnez le niveau correspondant à la pratique actuelle du licencié. Cette information peut modifier les règles sportives, médicales et documentaires applicables.', 'ufsc-clubs' );
}

function ufsc_fighter_level_label( $level ) {
	$levels = ufsc_get_fighter_levels();
	$key    = ufsc_normalize_fighter_level( $level );
	return isset( $levels[ $key ] ) ? $levels[ $key ] : __( 'Non renseigné', 'ufsc-clubs' );
}

function ufsc_normalize_fighter_level( $level ) {
	$raw = trim( (string) $level );
	$raw = function_exists( 'remove_accents' ) ? remove_accents( $raw ) : strtr( $raw, array( 'é' => 'e', 'É' => 'E' ) );
	$key = sanitize_key( str_replace( array( ' ', '-' ), '_', $raw ) );
	$aliases = array( 'debutant' => 'debutant', 'assaut' => 'assaut', 'classe_c' => 'classe_c', 'classe_b' => 'classe_b', 'classe_a' => 'classe_a', 'pro' => 'pro', 'professionnel' => 'pro', 'veteran' => 'veteran' );
	return $aliases[$key] ?? sanitize_key( (string) $level );
}

/**
 * Veteran starts at 41, consistently with the existing UFSC age-category grid.
 * Integrations may adjust this single rule without duplicating validation.
 */
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

/** Server-side business validation. Empty is accepted for legacy rows. */
function ufsc_validate_fighter_level( $level, $birth_date, $allow_empty = true ) {
	$level = ufsc_normalize_fighter_level( $level );
	if ( '' === $level && $allow_empty ) {
		return true;
	}
	$age = ufsc_age_from_birth_date( $birth_date );
	if ( null === $age ) {
		return new WP_Error( 'ufsc_invalid_birth_date_for_level', __( 'Une date de naissance valide est requise pour contrôler le niveau sportif.', 'ufsc-clubs' ) );
	}
	$allowed = $age < 18 ? array( 'debutant', 'assaut' ) : array( 'debutant', 'assaut', 'classe_c', 'classe_b', 'classe_a', 'pro' );
	if ( $age >= ufsc_get_veteran_min_age() ) {
		$allowed[] = 'veteran';
	}
	if ( ! in_array( $level, $allowed, true ) ) {
		return new WP_Error( 'ufsc_invalid_fighter_level', $age < 18
			? __( 'Niveau sportif incohérent : un mineur peut choisir Débutant ou Assaut.', 'ufsc-clubs' )
			: sprintf( __( 'Niveau sportif incohérent. Sélectionnez un niveau officiel; Vétéran est disponible à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) );
	}
	return true;
}
