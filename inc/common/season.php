<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC season helpers (01/08 -> 31/07) + compatible storage helpers.
 */

function ufsc_get_season_for_date( $ts ) {
    $ts    = absint( $ts );
    $month = (int) wp_date( 'n', $ts );
    $year  = (int) wp_date( 'Y', $ts );

    $start_year = ( $month >= 8 ) ? $year : ( $year - 1 );
    return sprintf( '%d-%d', $start_year, $start_year + 1 );
}

function ufsc_get_current_season() {
    return ufsc_get_season_for_date( current_time( 'timestamp' ) );
}

function ufsc_get_current_season_label() {
    return ufsc_get_current_season();
}

function ufsc_get_next_season() {
    $current = ufsc_get_current_season();
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $current, $m ) ) {
        return sprintf( '%d-%d', (int) $m[1] + 1, (int) $m[2] + 1 );
    }

    $y = (int) wp_date( 'Y', current_time( 'timestamp' ) );
    return sprintf( '%d-%d', $y, $y + 1 );
}

function ufsc_get_renewal_window_day_month() {
    $settings = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
    $day      = isset( $settings['renewal_window_day'] ) ? absint( $settings['renewal_window_day'] ) : 30;
    $month    = isset( $settings['renewal_window_month'] ) ? absint( $settings['renewal_window_month'] ) : 7;

    if ( $day < 1 || $day > 31 ) { $day = 30; }
    if ( $month < 1 || $month > 12 ) { $month = 7; }

    return array( $day, $month );
}

function ufsc_get_renewal_window_start_ts() {
    $current = ufsc_get_current_season();
    $end     = 0;

    if ( preg_match( '/^(\d{4})-(\d{4})$/', $current, $m ) ) {
        $end = (int) $m[2];
    }
    if ( $end <= 0 ) {
        $end = (int) wp_date( 'Y', current_time( 'timestamp' ) );
    }

    list( $day, $month ) = ufsc_get_renewal_window_day_month();
    return (int) strtotime( sprintf( '%04d-%02d-%02d 00:00:00', $end, $month, $day ) );
}

function ufsc_is_renewal_window_open() {
    return current_time( 'timestamp' ) >= ufsc_get_renewal_window_start_ts();
}

function ufsc_get_season_bounds( $season ) {
    $season = sanitize_text_field( (string) $season );
    if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $m ) ) {
        return array( 0, 0 );
    }

    $start = (int) strtotime( sprintf( '%04d-08-01 00:00:00', (int) $m[1] ) );
    $end   = (int) strtotime( sprintf( '%04d-07-31 23:59:59', (int) $m[2] ) );

    return array( $start, $end );
}

function ufsc_get_season_end_year_from_label( $season_label ) {
    $season_label = sanitize_text_field( (string) $season_label );
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $season_label, $matches ) ) {
        return (int) $matches[2];
    }

    return 0;
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

function ufsc_set_option_noautoload( $key, $value ) {
    global $wpdb;

    $key = sanitize_key( (string) $key );
    if ( '' === $key ) {
        return;
    }

    if ( false === get_option( $key, false ) ) {
        add_option( $key, $value, '', 'no' );
        return;
    }

    update_option( $key, $value, false );

    if ( isset( $wpdb->options ) ) {
        $wpdb->update(
            $wpdb->options,
            array( 'autoload' => 'no' ),
            array( 'option_name' => $key ),
            array( '%s' ),
            array( '%s' )
        );
    }
}

function ufsc_get_option( $key, $default = '' ) {
    return get_option( sanitize_key( (string) $key ), $default );
}

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
            $option_value = ufsc_get_option( 'ufsc_licence_season_' . $licence_id, '' );
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

    ufsc_set_option_noautoload( 'ufsc_licence_season_' . $licence_id, $season );
}

function ufsc_get_affiliation_season( $club_id, $season = '' ) {
    global $wpdb;

    $club_id = absint( $club_id );
    $season  = sanitize_text_field( (string) $season );

    if ( $club_id <= 0 || ! function_exists( 'ufsc_get_clubs_table' ) ) {
        return null;
    }

    $table      = ufsc_get_clubs_table();
    $season_col = ufsc_get_detected_season_column( $table );
    if ( '' !== $season_col ) {
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT `{$season_col}` FROM `{$table}` WHERE id = %d", $club_id ) );
        if ( 'season_end_year' === $season_col ) {
            $end = absint( $raw );
            if ( $end > 0 ) {
                return sprintf( '%d-%d', $end - 1, $end );
            }
        } elseif ( is_string( $raw ) && '' !== $raw ) {
            return sanitize_text_field( $raw );
        }
    }

    if ( '' !== $season ) {
        $option_value = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id . '_' . $season, '' );
        return is_string( $option_value ) && '' !== $option_value ? sanitize_text_field( $option_value ) : null;
    }

    foreach ( array( ufsc_get_current_season(), ufsc_get_next_season() ) as $candidate ) {
        $option_value = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id . '_' . $candidate, '' );
        if ( is_string( $option_value ) && '' !== $option_value ) {
            return sanitize_text_field( $option_value );
        }
    }

    $legacy = ufsc_get_option( 'ufsc_affiliation_season_' . $club_id, '' );
    return is_string( $legacy ) && '' !== $legacy ? sanitize_text_field( $legacy ) : null;
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

    ufsc_set_option_noautoload( 'ufsc_affiliation_season_' . $club_id . '_' . $season, $season );
}

function ufsc_get_renewed_licence_marker( $source_licence_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
    return absint( ufsc_get_option( $key, 0 ) );
}

function ufsc_mark_renewed_licence_marker( $source_licence_id, $target_season, $new_licence_id ) {
    $key = sprintf( 'ufsc_renewed_licence_%d_%s', absint( $source_licence_id ), sanitize_key( $target_season ) );
    ufsc_set_option_noautoload( $key, absint( $new_licence_id ) );
}

function ufsc_is_affiliation_renewed( $club_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
    return (bool) ufsc_get_option( $key, 0 );
}

function ufsc_mark_affiliation_renewed( $club_id, $target_season ) {
    $key = sprintf( 'ufsc_renewed_affiliation_%d_%s', absint( $club_id ), sanitize_key( $target_season ) );
    ufsc_set_option_noautoload( $key, 1 );
}

function ufsc_get_renewal_copy_fields() {
    return array(
        'nom',
        'nom_licence',
        'prenom',
        'email',
        'adresse',
        'code_postal',
        'ville',
        'tel_fixe',
        'tel_mobile',
        'date_naissance',
        'sexe',
        'nationalite',
        'competition',
        'surclassement',
        'piece_identite',
        'photo_identite',
    );
}
