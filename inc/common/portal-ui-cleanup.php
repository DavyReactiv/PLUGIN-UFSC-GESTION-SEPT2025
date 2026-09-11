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
 * Add the portal body class on the Compte Club route and on pages that expose a
 * Club portal shortcode directly in post_content.
 *
 * Production may render the Compte Club shortcode through Elementor metadata,
 * in which case has_shortcode( $post->post_content ) cannot see it. The stable
 * layout styles are intentionally scoped to body.ufsc-portal-clean-page, so the
 * canonical page slug is a safe presentation-only fallback.
 */
function ufsc_portal_cleanup_body_class( $classes ) {
    global $post;
    $classes = is_array( $classes ) ? $classes : array();

    $is_compte_club = function_exists( 'is_page' ) && is_page( 'compte-club' );
    if ( $is_compte_club ) {
        $classes[] = 'ufsc-portal-clean-page';
    } elseif ( $post && is_string( $post->post_content ?? null ) ) {
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

/**
 * DEV-only breadcrumb for the renewal POST. The previous debug log contained
 * rendering decisions but no evidence that the final submit reached admin-post.
 * Never log profile fields or other personal data: IDs, intent and target season
 * are sufficient to diagnose the workflow boundary.
 */
function ufsc_production_log_renewal_post() {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    if ( 'POST' !== $method || 'ufsc_bulk_renew_licences' !== $action ) { return; }

    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) ) : '';
    $season = isset( $_POST['ufsc_target_season'] ) && ! is_array( $_POST['ufsc_target_season'] ) ? sanitize_text_field( wp_unslash( $_POST['ufsc_target_season'] ) ) : '';
    $club_id = isset( $_POST['ufsc_club_id'] ) ? absint( wp_unslash( $_POST['ufsc_club_id'] ) ) : 0;
    $ids = array();
    foreach ( array( 'ufsc_renew_ids', 'source_ids', 'renew_licence_ids' ) as $key ) {
        if ( isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ) {
            $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) ) ) );
            break;
        }
    }

    error_log( '[UFSC Gestion] renewal POST ' . wp_json_encode( array(
        'intent' => $intent,
        'club_id' => $club_id,
        'target_season' => $season,
        'source_ids' => $ids,
    ) ) );
}
add_action( 'admin_init', 'ufsc_production_log_renewal_post', 1 );

/** Log the canonical renewal marker after finalisation, without personal data. */
function ufsc_production_log_renewal_shutdown_result() {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) { return; }
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
    $intent = isset( $_POST['ufsc_renew_intent'] ) && ! is_array( $_POST['ufsc_renew_intent'] ) ? sanitize_key( wp_unslash( $_POST['ufsc_renew_intent'] ) ) : '';
    if ( 'ufsc_bulk_renew_licences' !== $action || 'add_to_cart' !== $intent ) { return; }

    $season = class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    $ids = array();
    if ( isset( $_POST['ufsc_renew_ids'] ) && is_array( $_POST['ufsc_renew_ids'] ) ) {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['ufsc_renew_ids'] ) ) ) ) );
    }
    $markers = array();
    if ( function_exists( 'ufsc_get_renewed_licence_marker' ) ) {
        foreach ( $ids as $source_id ) {
            $markers[ $source_id ] = absint( ufsc_get_renewed_licence_marker( $source_id, $season ) );
        }
    }

    error_log( '[UFSC Gestion] renewal FINAL ' . wp_json_encode( array(
        'target_season' => $season,
        'source_ids' => $ids,
        'renewal_markers' => $markers,
    ) ) );
}
add_action( 'shutdown', 'ufsc_production_log_renewal_shutdown_result', 999 );
