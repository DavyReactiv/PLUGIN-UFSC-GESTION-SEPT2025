<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * DEV recipe v3: authoritative final rendering for the licence detail screen.
 * A draft licence covered by the active affiliation quota must never expose a
 * paid WooCommerce CTA, regardless of which legacy detail markup rendered it.
 */
function ufsc_p0v3_is_draft( $licence ) {
    $status = sanitize_key( (string) ( $licence->statut ?? ( $licence->status ?? '' ) ) );
    return in_array( $status, array( '', 'draft', 'brouillon', 'a_completer' ), true );
}

function ufsc_p0v3_enforce_detail_decision( $output, $tag ) {
    if ( ! in_array( $tag, array( 'ufsc_club_licences', 'ufsc_licences' ), true ) || ! is_user_logged_in() ) {
        return $output;
    }

    $licence_id = isset( $_GET['view_licence'] ) ? absint( wp_unslash( $_GET['view_licence'] ) ) : 0;
    if ( $licence_id < 1 || ! function_exists( 'ufsc_p0_get_licence_row' ) || ! function_exists( 'ufsc_p0_pack_decision' ) || ! function_exists( 'ufsc_p0_render_licence_decision_form' ) ) {
        return $output;
    }

    $licence = ufsc_p0_get_licence_row( $licence_id );
    if ( ! $licence || ( function_exists( 'ufsc_p0_user_can_manage_licence' ) && ! ufsc_p0_user_can_manage_licence( $licence ) ) || ! ufsc_p0v3_is_draft( $licence ) ) {
        return $output;
    }

    $decision = (array) ufsc_p0_pack_decision( $licence );
    if ( empty( $decision['included'] ) ) {
        return $output;
    }

    $decision_form = ufsc_p0_render_licence_decision_form( $licence );
    if ( '' === $decision_form ) {
        return $output;
    }

    // First remove/replace any complete legacy form whose visible action is cart payment.
    $count = 0;
    $output = preg_replace(
        '~<form\b[^>]*>(?:(?!</form>).)*?Ajouter\s+au\s+panier(?:(?!</form>).)*?</form>~isu',
        $decision_form,
        $output,
        1,
        $count
    );

    // Historical templates can render a standalone anchor or button.
    if ( 0 === $count ) {
        $output = preg_replace(
            '~<(?:a|button)\b[^>]*>\s*Ajouter\s+au\s+panier\s*</(?:a|button)>~isu',
            $decision_form,
            $output,
            1,
            $count
        );
    }

    // Last-resort insertion: do not leave a misleading cart action visible.
    if ( 0 === $count && preg_match( '~Ajouter\s+au\s+panier~iu', wp_strip_all_tags( $output ) ) ) {
        $output = preg_replace( '~Ajouter\s+au\s+panier~iu', esc_html__( 'Incluse dans votre affiliation', 'ufsc-clubs' ), $output, 1 );
        $modifier_pos = stripos( $output, '>Modifier<' );
        if ( false !== $modifier_pos ) {
            $anchor_start = strrpos( substr( $output, 0, $modifier_pos ), '<' );
            if ( false !== $anchor_start ) {
                $output = substr( $output, 0, $anchor_start ) . $decision_form . substr( $output, $anchor_start );
            }
        }
    }

    // Payment wording is incorrect for an included licence.
    $replacements = array(
        'Vérification obligatoire avant paiement' => 'Vérification obligatoire avant envoi',
        'Une fois le paiement effectué, la licence passe en traitement et ne peut plus être modifiée en autonomie.' => 'Une fois la demande envoyée, la licence passe en traitement et ne peut plus être modifiée en autonomie.',
        'Toute correction demandée après paiement est soumise à des frais de traitement administratif de 5 €.' => 'Merci de vérifier attentivement les informations avant l’envoi de la demande.',
    );
    $output = strtr( $output, $replacements );

    return $output;
}

function ufsc_p0v3_shortcode_output( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    return ufsc_p0v3_enforce_detail_decision( $output, $tag );
}
add_filter( 'do_shortcode_tag', 'ufsc_p0v3_shortcode_output', 999, 4 );

function ufsc_p0v3_enqueue_assets() {
    if ( is_admin() ) { return; }
    wp_enqueue_style(
        'ufsc-p0-dev-recipe-v3',
        plugins_url( '../../assets/css/ufsc-p0-dev-recipe-v3.css', __FILE__ ),
        array( 'ufsc-p0-dev-recipe-v2' ),
        '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'ufsc_p0v3_enqueue_assets', 999 );
