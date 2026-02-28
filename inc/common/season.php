<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC season helpers (01/08 -> 31/07).
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
