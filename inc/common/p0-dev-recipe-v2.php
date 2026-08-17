<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * DEV recipe v2.
 * Makes the business state authoritative at render time instead of relying on
 * legacy CTA markup. Drafts inside the 10-place affiliation quota never show a
 * paid CTA. Also fixes the connected-club summary and mobile account density.
 */

function ufsc_p0v2_is_draft_status( $status ) {
    return in_array( sanitize_key( (string) $status ), array( '', 'draft', 'brouillon' ), true );
}

function ufsc_p0v2_replace_detail_cta( $output ) {
    $licence_id = isset( $_GET['view_licence'] ) ? absint( wp_unslash( $_GET['view_licence'] ) ) : 0;
    if ( $licence_id < 1 || ! function_exists( 'ufsc_p0_get_licence_row' ) || ! function_exists( 'ufsc_p0_render_licence_decision_form' ) ) {
        return $output;
    }

    $licence = ufsc_p0_get_licence_row( $licence_id );
    if ( ! $licence || ( function_exists( 'ufsc_p0_user_can_manage_licence' ) && ! ufsc_p0_user_can_manage_licence( $licence ) ) ) {
        return $output;
    }

    $decision = function_exists( 'ufsc_p0_pack_decision' ) ? ufsc_p0_pack_decision( $licence ) : array( 'included' => false );
    $status = $licence->statut ?? ( $licence->status ?? '' );
    if ( ! ufsc_p0v2_is_draft_status( $status ) || empty( $decision['included'] ) ) {
        return $output;
    }

    $form = ufsc_p0_render_licence_decision_form( $licence );
    if ( '' === $form ) { return $output; }

    // Replace any legacy single-licence cart form, regardless of field order.
    $id = preg_quote( (string) $licence_id, '~' );
    $pattern = '~<form\b[^>]*>(?:(?!</form>).)*?(?:name=["\']ufsc_license_ids["\'][^>]*value=["\']' . $id . '["\']|value=["\']' . $id . '["\'][^>]*name=["\']ufsc_license_ids["\'])(?:(?!</form>).)*?</form>~is';
    $count = 0;
    $output = preg_replace( $pattern, $form, $output, 1, $count );

    // Some historical detail templates use a plain cart link/button rather than
    // the canonical form. Replace that actionable control before Modifier.
    if ( 0 === $count && preg_match( '~Ajouter\s+au\s+panier~iu', wp_strip_all_tags( $output ) ) ) {
        $output = preg_replace(
            '~<(?:a|button)\b[^>]*>\s*Ajouter\s+au\s+panier\s*</(?:a|button)>~isu',
            $form,
            $output,
            1
        );
    }

    return $output;
}

function ufsc_p0v2_validated_current_count( $club_id, $season ) {
    if ( class_exists( 'UFSC_Stats' ) ) {
        $stats = (array) UFSC_Stats::get_club_stats( absint( $club_id ), (string) $season );
        return absint( $stats['validated_licences'] ?? 0 );
    }
    return 0;
}

function ufsc_p0v2_shortcode_output( $output, $tag, $attr, $m ) {
    unset( $attr, $m );

    if ( in_array( $tag, array( 'ufsc_club_licences', 'ufsc_licences' ), true ) ) {
        $output = ufsc_p0v2_replace_detail_cta( $output );
    }

    // The connection page is a summary, not a count of DB rows. Drafts must not
    // be presented as current/active licences.
    if ( in_array( $tag, array( 'ufsc_club_login', 'ufsc_login', 'ufsc_club_account' ), true ) && is_user_logged_in() && function_exists( 'ufsc_get_user_club_id' ) ) {
        $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
        $season = function_exists( 'ufsc_p0_current_season' ) ? ufsc_p0_current_season() : '';
        $validated = ufsc_p0v2_validated_current_count( $club_id, $season );
        $output = preg_replace(
            '~(<(?:dt|strong|span|div|p)[^>]*>\s*Licences\s+courantes\s*</(?:dt|strong|span|div|p)>\s*<(?:dd|span|div|p)[^>]*>)\s*\d+~iu',
            '$1' . $validated,
            $output,
            1
        );
    }

    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_p0v2_shortcode_output', 90, 4 );

function ufsc_p0v2_enqueue_assets() {
    if ( is_admin() ) { return; }
    wp_enqueue_style(
        'ufsc-p0-dev-recipe-v2',
        plugins_url( '../../assets/css/ufsc-p0-dev-recipe-v2.css', __FILE__ ),
        array( 'ufsc-p0-quota-cart-kpi' ),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'ufsc_p0v2_enqueue_assets', 100 );
