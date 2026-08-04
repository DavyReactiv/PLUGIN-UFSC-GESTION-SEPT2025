<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical values for the licence sporting level. */
function ufsc_get_fighter_levels() {
	return array(
		'assaut'    => __( 'Assaut', 'ufsc-clubs' ),
		'classe_c'  => __( 'Classe C', 'ufsc-clubs' ),
		'classe_b'  => __( 'Classe B', 'ufsc-clubs' ),
		'classe_a'  => __( 'Classe A', 'ufsc-clubs' ),
		'veteran'   => __( 'Vétéran', 'ufsc-clubs' ),
	);
}

function ufsc_fighter_level_label( $level ) {
	$levels = ufsc_get_fighter_levels();
	$key    = sanitize_key( (string) $level );
	return isset( $levels[ $key ] ) ? $levels[ $key ] : __( 'Non renseigné', 'ufsc-clubs' );
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
	$level = sanitize_key( (string) $level );
	if ( '' === $level && $allow_empty ) {
		return true;
	}
	$age = ufsc_age_from_birth_date( $birth_date );
	if ( null === $age ) {
		return new WP_Error( 'ufsc_invalid_birth_date_for_level', __( 'Une date de naissance valide est requise pour contrôler le niveau sportif.', 'ufsc-clubs' ) );
	}
	$allowed = $age < 18 ? array( 'assaut' ) : array( 'classe_c', 'classe_b', 'classe_a' );
	if ( $age >= ufsc_get_veteran_min_age() ) {
		$allowed[] = 'veteran';
	}
	if ( ! in_array( $level, $allowed, true ) ) {
		return new WP_Error( 'ufsc_invalid_fighter_level', $age < 18
			? __( 'Niveau sportif incohérent : un mineur peut uniquement choisir Assaut.', 'ufsc-clubs' )
			: sprintf( __( 'Niveau sportif incohérent : Classe C, Classe B ou Classe A sont autorisées dès 18 ans; Vétéran à partir de %d ans.', 'ufsc-clubs' ), ufsc_get_veteran_min_age() ) );
	}
	return true;
}
