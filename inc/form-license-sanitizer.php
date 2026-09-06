<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * License form sanitizer for UFSC Gestion
 * Handles sanitization and validation of license form data
 */

/**
 * Sanitize and validate license form POST data.
 *
 * The current form only emits active UFSC / FFST fields. Legacy partner fields
 * are deliberately not synthesized as empty/false values: an edit performed in
 * a later season must never erase historical ASPTT/FSASPTT/Postier data.
 *
 * @param array $post_data Raw $_POST data
 * @param int $submitted_club_id Club ID from form
 * @return array Array with 'data' and 'errors' keys
 */
function ufsc_sanitize_licence_post( $post_data, $submitted_club_id = 0 ) {
    $post_data = wp_unslash( $post_data );

    $data = array();
    $errors = array();

    $data['club_id'] = absint( $submitted_club_id );

    $required_fields = array( 'nom', 'prenom' );
    foreach ( $required_fields as $field ) {
        if ( empty( $post_data[ $field ] ) ) {
            $errors[ $field ] = sprintf( __( '%s est requis', 'ufsc-clubs' ), ucfirst( $field ) );
        }
    }

    $text_fields = array(
        'nom', 'prenom', 'adresse', 'suite_adresse', 'ville', 'profession',
        'note', 'reduction_benevole_num'
    );

    foreach ( $text_fields as $field ) {
        $data[ $field ] = isset( $post_data[ $field ] ) ? sanitize_text_field( $post_data[ $field ] ) : '';
    }

    if ( isset( $post_data['email'] ) ) {
        $email = sanitize_email( $post_data['email'] );
        if ( ! empty( $email ) && ! is_email( $email ) ) {
            $errors['email'] = __( 'Format d\'email invalide', 'ufsc-clubs' );
        }
        $data['email'] = $email;
    } else {
        $data['email'] = '';
    }

    if ( isset( $post_data['date_naissance'] ) && ! empty( $post_data['date_naissance'] ) ) {
        $date_input = sanitize_text_field( $post_data['date_naissance'] );
        $normalized_date = ufsc_normalize_date( $date_input );

        if ( false === $normalized_date ) {
            $errors['date_naissance'] = __( 'Format de date invalide (attendu: AAAA-MM-JJ ou JJ/MM/AAAA)', 'ufsc-clubs' );
        } else {
            $data['date_naissance'] = $normalized_date;
        }
    } else {
        $data['date_naissance'] = '';
    }

    $data['fighter_level'] = isset( $post_data['fighter_level'] ) ? sanitize_key( $post_data['fighter_level'] ) : '';
    if ( function_exists( 'ufsc_validate_fighter_level' ) ) {
        $level_validation = ufsc_validate_fighter_level( $data['fighter_level'], $data['date_naissance'], true );
        if ( is_wp_error( $level_validation ) ) {
            $errors['fighter_level'] = $level_validation->get_error_message();
        }
    }

    $data['sexe'] = isset( $post_data['sexe'] ) && in_array( $post_data['sexe'], array( 'M', 'F' ), true )
        ? $post_data['sexe']
        : 'M';

    // Active booleans only. Never emit legacy partner booleans as zero.
    $boolean_fields = array(
        'reduction_benevole', 'fonction_publique', 'competition', 'diffusion_image',
        'infos_ffst', 'infos_cr', 'infos_partenaires', 'honorabilite',
        'assurance_dommage_corporel', 'assurance_assistance'
    );

    foreach ( $boolean_fields as $field ) {
        $data[ $field ] = ! empty( $post_data[ $field ] ) ? 1 : 0;
    }

    if ( 1 !== $data['reduction_benevole'] ) {
        $data['reduction_benevole_num'] = '';
    }

    $phone_fields = array( 'tel_fixe', 'tel_mobile' );
    foreach ( $phone_fields as $field ) {
        if ( isset( $post_data[ $field ] ) ) {
            $data[ $field ] = preg_replace( '/[^\d+]/', '', $post_data[ $field ] );
        } else {
            $data[ $field ] = '';
        }
    }

    if ( isset( $post_data['code_postal'] ) ) {
        $data['code_postal'] = preg_replace( '/[^A-Za-z0-9\s-]/', '', $post_data['code_postal'] );
    } else {
        $data['code_postal'] = '';
    }

    if ( isset( $post_data['region'] ) ) {
        $region = sanitize_text_field( $post_data['region'] );
        if ( ! empty( $region ) && ! ufsc_is_valid_region( $region ) ) {
            $errors['region'] = __( 'Région non valide', 'ufsc-clubs' );
        }
        $data['region'] = $region;
    } else {
        $data['region'] = '';
    }

    return array(
        'data' => $data,
        'errors' => $errors
    );
}

/**
 * Normalize date from various formats to Y-m-d
 *
 * @param string $date_input Date input in Y-m-d or d/m/Y format
 * @return string|false Normalized date in Y-m-d format or false if invalid
 */
function ufsc_normalize_date( $date_input ) {
    if ( empty( $date_input ) ) {
        return false;
    }

    if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date_input ) ) {
        $date = DateTime::createFromFormat( 'Y-m-d', $date_input );
        if ( $date && $date->format( 'Y-m-d' ) === $date_input ) {
            return $date_input;
        }
    }

    if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_input, $matches ) ) {
        $day = str_pad( $matches[1], 2, '0', STR_PAD_LEFT );
        $month = str_pad( $matches[2], 2, '0', STR_PAD_LEFT );
        $year = $matches[3];

        $date = DateTime::createFromFormat( 'Y-m-d', "$year-$month-$day" );
        if ( $date && $date->format( 'Y-m-d' ) === "$year-$month-$day" ) {
            return "$year-$month-$day";
        }
    }

    return false;
}
