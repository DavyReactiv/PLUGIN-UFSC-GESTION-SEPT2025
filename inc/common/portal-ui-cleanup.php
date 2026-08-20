<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Final Club portal presentation layer.
 *
 * The portal previously loaded multiple CSS files redefining the same selectors
 * (.ufsc-club-hero, .ufsc-club-account__nav, .ufsc-pack-card, logo editor, etc.).
 * This module removes those competing presentation layers and loads one scoped
 * stylesheet after the canonical ufsc-front.css base.
 */
function ufsc_portal_cleanup_assets() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }

    // Historical presentation layers: keep their PHP/business logic and JS, but
    // never let their competing CSS participate in cascade resolution.
    foreach ( array(
        'ufsc-club-journey',
        'ufsc-structural-portal',
        'ufsc-p0-dev-recipe-v2',
        'ufsc-p0-dev-recipe-v3',
        'ufsc-p0-quota-cart-kpi',
    ) as $handle ) {
        wp_dequeue_style( $handle );
        wp_deregister_style( $handle );
    }

    $css = 'assets/css/ufsc-portal-clean.css';
    $js  = 'assets/js/ufsc-portal-clean.js';
    $renewal_js = 'assets/js/ufsc-renewal-production-flow.js';
    $version_css = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $css ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $version_js  = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $js ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );
    $version_renewal_js = function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( $renewal_js ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null );

    wp_enqueue_style( 'ufsc-portal-clean', UFSC_CL_URL . $css, array(), $version_css );
    wp_enqueue_script( 'ufsc-portal-clean', UFSC_CL_URL . $js, array(), $version_js, true );
    wp_enqueue_script( 'ufsc-renewal-production-flow', UFSC_CL_URL . $renewal_js, array( 'ufsc-portal-clean' ), $version_renewal_js, true );
}
add_action( 'wp_enqueue_scripts', 'ufsc_portal_cleanup_assets', 999 );

/**
 * Add a body class to every front page where a Club portal shortcode is used.
 * This keeps all layout repairs strictly scoped to the UFSC portal.
 */
function ufsc_portal_cleanup_body_class( $classes ) {
    global $post;
    $classes = is_array( $classes ) ? $classes : array();
    if ( $post && is_string( $post->post_content ?? null ) ) {
        foreach ( array( 'ufsc_club_dashboard', 'ufsc_club_profile', 'ufsc_club_licences', 'ufsc_add_licence' ) as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                $classes[] = 'ufsc-portal-clean-page';
                break;
            }
        }
    }
    return array_values( array_unique( $classes ) );
}
add_filter( 'body_class', 'ufsc_portal_cleanup_body_class', 99 );
