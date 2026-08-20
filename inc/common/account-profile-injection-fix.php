<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Presentation-only fix for the Compte Club profile enrichment placement.
 *
 * The historical enrichment filter inserted the "Éléments à compléter" and
 * affiliation trace blocks before the first admin-post form found in the
 * profile HTML. The first form is the logo upload form nested inside
 * .ufsc-logo-editor, so the injected cards were constrained to the narrow logo
 * column and rendered almost vertically.
 *
 * This compatibility layer replaces only that output-placement filter and
 * anchors the insertion immediately before the real club profile form.
 */
function ufsc_enrich_club_profile_shortcode_output_safe( $output, $tag, $attr, $m ) {
    unset( $attr, $m );

    if ( 'ufsc_club_profile' !== $tag || ! is_user_logged_in() || ! class_exists( 'UFSC_Frontend_Shortcodes' ) ) {
        return $output;
    }

    $club_id = function_exists( 'ufsc_get_user_club_id' )
        ? absint( ufsc_get_user_club_id( get_current_user_id() ) )
        : 0;
    if ( $club_id < 1 ) {
        return $output;
    }

    $season = class_exists( 'UFSC_Season_Service' )
        ? UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );

    $insert = ufsc_render_account_action_box( $club_id, $season ) . ufsc_render_affiliation_trace_box( $club_id, $season );
    if ( '' === $insert ) {
        return $output;
    }

    // Anchor to the canonical club profile form, never to the nested logo forms.
    $profile_form_marker = 'class="ufsc-club-form ufsc-club-profile"';
    $marker_pos          = strpos( $output, $profile_form_marker );
    if ( false === $marker_pos ) {
        // Fail closed: keep the existing profile untouched rather than inject in
        // an unknown location if the markup changes in a future release.
        return $output;
    }

    $before_marker = substr( $output, 0, $marker_pos );
    $form_pos      = strrpos( $before_marker, '<form' );
    if ( false === $form_pos ) {
        return $output;
    }

    return substr( $output, 0, $form_pos ) . $insert . substr( $output, $form_pos );
}

/**
 * Replace only the historical presentation filter. No business hook changes.
 */
function ufsc_replace_club_profile_enrichment_placement() {
    remove_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output', 20 );
    add_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output_safe', 20, 4 );
}
ufsc_replace_club_profile_enrichment_placement();
