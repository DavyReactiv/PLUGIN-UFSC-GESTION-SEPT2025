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
    if ( function_exists( 'ufsc_get_current_season' ) ) {
        return ufsc_get_current_season();
    }

    if ( class_exists( 'UFSC_Utils' ) ) {
        return UFSC_Utils::get_current_season();
    }

    $year = (int) gmdate( 'Y' );
    return sprintf( '%d-%d', $year, $year + 1 );
}

function ufsc_get_detected_season_column( $table ) {
    static $cache = array();

    if ( isset( $cache[ $table ] ) ) {
        return $cache[ $table ];
    }

    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $col ) {
        if ( in_array( $col, $columns, true ) ) {
            $cache[ $table ] = $col;
            return $col;
        }
    }

    $cache[ $table ] = '';
    return '';
}

/**
 * Derive a licence season label from known fields.
 *
 * @param object|array $licence Licence record.
 * @return string
 */
function ufsc_get_licence_season( $licence ) {
    global $wpdb;

    $licence_id = 0;
    if ( is_numeric( $licence ) ) {
        $licence_id = absint( $licence );
        if ( $licence_id > 0 && function_exists( 'ufsc_get_licences_table' ) ) {
            $table   = ufsc_get_licences_table();
            $licence = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
        }
    } else {
        $licence_id = is_object( $licence ) ? absint( $licence->id ?? 0 ) : absint( $licence['id'] ?? 0 );
    }

    $fields = array( 'paid_season', 'season', 'saison', 'season_end_year' );
    $value  = '';
    $field  = '';

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
        if ( $licence_id > 0 ) {
            $option_value = get_option( 'ufsc_licence_season_' . $licence_id, '' );
            if ( is_string( $option_value ) && '' !== $option_value ) {
                return sanitize_text_field( $option_value );
            }
        }
        return null;
    }

    if ( 'season_end_year' === $field ) {
        $end_year = absint( $value );
        if ( $end_year > 0 ) {
            return sprintf( '%d-%d', $end_year - 1, $end_year );
        }
    }

    return sanitize_text_field( (string) $value );
}

function ufsc_set_licence_season( $licence_id, $season ) {
    global $wpdb;

    $licence_id = absint( $licence_id );
    $season     = sanitize_text_field( (string) $season );
    if ( $licence_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    $table       = ufsc_get_licences_table();
    $season_col  = ufsc_get_detected_season_column( $table );
    $update_data = array();
    $formats     = array();

    if ( 'season_end_year' === $season_col ) {
        $update_data['season_end_year'] = (int) ufsc_get_season_end_year_from_label( $season );
        $formats[]                      = '%d';
    } elseif ( '' !== $season_col ) {
        $update_data[ $season_col ] = $season;
        $formats[]                  = '%s';
    }

    if ( ! empty( $update_data ) ) {
        $wpdb->update( $table, $update_data, array( 'id' => $licence_id ), $formats, array( '%d' ) );
    }

    update_option( 'ufsc_licence_season_' . $licence_id, $season, false );
}

function ufsc_get_affiliation_season( $club_id ) {
    global $wpdb;

    $club_id = absint( $club_id );
    if ( $club_id <= 0 || ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return null;
    }

    $table      = ufsc_get_clubs_table();
    $season_col = ufsc_get_detected_season_column( $table );
    if ( '' !== $season_col ) {
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT `{$season_col}` FROM `{$table}` WHERE id = %d", $club_id ) );
        if ( 'season_end_year' === $season_col ) {
            $end = absint( $raw );
            return $end > 0 ? sprintf( '%d-%d', $end - 1, $end ) : null;
        }
        if ( is_string( $raw ) && '' !== $raw ) {
            return sanitize_text_field( $raw );
        }
    }

    $option_value = get_option( 'ufsc_affiliation_season_' . $club_id, '' );
    return is_string( $option_value ) && '' !== $option_value ? sanitize_text_field( $option_value ) : null;
}

function ufsc_set_affiliation_season( $club_id, $season ) {
    global $wpdb;

    $club_id = absint( $club_id );
    $season  = sanitize_text_field( (string) $season );
    if ( $club_id <= 0 || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return;
    }

    $table       = ufsc_get_clubs_table();
    $season_col  = ufsc_get_detected_season_column( $table );
    $update_data = array();
    $formats     = array();

    if ( 'season_end_year' === $season_col ) {
        $update_data['season_end_year'] = (int) ufsc_get_season_end_year_from_label( $season );
        $formats[]                      = '%d';
    } elseif ( '' !== $season_col ) {
        $update_data[ $season_col ] = $season;
        $formats[]                  = '%s';
    }

    if ( ! empty( $update_data ) ) {
        $wpdb->update( $table, $update_data, array( 'id' => $club_id ), $formats, array( '%d' ) );
    }

    update_option( 'ufsc_affiliation_season_' . $club_id, $season, false );
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


function ufsc_get_renewed_licence_marker( $source_licence_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
    return absint( get_option( $key, 0 ) );
}

function ufsc_mark_renewed_licence_marker( $source_licence_id, $target_season, $new_licence_id ) {
    $key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
    update_option( $key, absint( $new_licence_id ), false );
}

function ufsc_is_affiliation_renewed( $club_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
    return (bool) get_option( $key, 0 );
}

function ufsc_mark_affiliation_renewed( $club_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
    update_option( $key, 1, false );
}
