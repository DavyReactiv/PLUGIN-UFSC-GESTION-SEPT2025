<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC PATCH: Season helpers for consistent display and storage.
 */

/**
 * Get the current season label (YYYY-YYYY) from Woo settings or date.
 *
 * @return string
 */
function ufsc_get_current_season_label() {
    if ( function_exists( 'ufsc_get_woocommerce_settings' ) ) {
        $settings = ufsc_get_woocommerce_settings();
        if ( ! empty( $settings['season'] ) ) {
            return sanitize_text_field( $settings['season'] );
        }
    }

    if ( class_exists( 'UFSC_Utils' ) ) {
        return UFSC_Utils::get_current_season();
    }

    $year = (int) gmdate( 'Y' );
    return sprintf( '%d-%d', $year, $year + 1 );
}

/**
 * Derive a licence season label from known fields.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_season( $licence ) {
    $fields = array( 'paid_season', 'season', 'saison', 'season_end_year' );
    $value  = '';

    foreach ( $fields as $field ) {
        if ( is_array( $licence ) && isset( $licence[ $field ] ) ) {
            $value = $licence[ $field ];
        } elseif ( is_object( $licence ) && isset( $licence->{$field} ) ) {
            $value = $licence->{$field};
        }

        if ( '' !== $value && null !== $value ) {
            break;
        }
    }

    if ( '' === $value || null === $value ) {
        return ufsc_get_current_season_label();
    }

    if ( 'season_end_year' === $field ) {
        $end_year = absint( $value );
        if ( $end_year > 0 ) {
            return sprintf( '%d-%d', $end_year - 1, $end_year );
        }
    }

    return sanitize_text_field( (string) $value );
}

/**
 * Get season end year from a season label.
 *
 * @param string $season_label Season label (YYYY-YYYY).
 * @return int
 */
function ufsc_get_season_end_year_from_label( $season_label ) {
    $season_label = sanitize_text_field( (string) $season_label );
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $season_label, $matches ) ) {
        return (int) $matches[2];
    }

    return 0;
}
