<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Preview the club pack without reserving a credit. */
function ufsc_p0_club_pack_preview( $club_id ) {
    $club_id = absint( $club_id );
    $season = function_exists( 'ufsc_p0_current_season' ) ? ufsc_p0_current_season() : '';
    $limit = function_exists( 'ufsc_get_pack_included_limit' ) ? absint( ufsc_get_pack_included_limit() ) : 10;
    $usage = function_exists( 'ufsc_get_pack_usage' ) ? (array) ufsc_get_pack_usage( $club_id, $season ) : array();
    $used = absint( $usage['total'] ?? 0 );
    return array(
        'season'    => $season,
        'limit'     => $limit,
        'used'      => min( $used, $limit ),
        'remaining' => max( 0, $limit - $used ),
        'included'  => $used < $limit,
    );
}

/** Replace only the visible finalization label; the canonical add_to_cart intent
 * remains unchanged because it is the server-side trigger that performs pack
 * allocation before deciding whether a WooCommerce line is necessary. */
function ufsc_p0_relabel_finalization_button( $output, $preview ) {
    $included = ! empty( $preview['included'] );
    $label = $included
        ? __( 'Envoyer pour validation — inclus dans votre affiliation', 'ufsc-clubs' )
        : __( 'Ajouter au panier — licence payante', 'ufsc-clubs' );

    $pattern = '~(<button\b[^>]*name="ufsc_submit_action"[^>]*value="add_to_cart"[^>]*>)(.*?)(</button>)~is';
    $output = preg_replace( $pattern, '$1' . esc_html( $label ) . '$3', $output, 1 );

    $message = $included
        ? sprintf(
            __( 'Quota affiliation %1$d/%2$d utilisé — %3$d licence(s) incluse(s) restante(s). Aucun paiement pour cette demande tant qu’une place incluse reste disponible.', 'ufsc-clubs' ),
            $preview['used'],
            $preview['limit'],
            $preview['remaining']
        )
        : sprintf(
            __( 'Quota affiliation %1$d/%2$d atteint — cette licence est supplémentaire et sera ajoutée au panier.', 'ufsc-clubs' ),
            $preview['used'],
            $preview['limit']
        );

    $marker = '<div class="ufsc-final-buttons">';
    if ( false !== strpos( $output, $marker ) ) {
        $notice = '<div class="ufsc-p0-pack-preview ' . ( $included ? 'ufsc-p0-pack-preview--included' : 'ufsc-p0-pack-preview--paid' ) . '" role="status"><strong>'
            . esc_html( $included ? __( 'Licence incluse', 'ufsc-clubs' ) : __( 'Licence payante', 'ufsc-clubs' ) )
            . '</strong><span>' . esc_html( $message ) . '</span></div>';
        $output = str_replace( $marker, $notice . $marker, $output );
    }
    return $output;
}

function ufsc_p0_relabel_quota_ui( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) { return $output; }

    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    if ( $club_id < 1 ) { return $output; }
    $preview = ufsc_p0_club_pack_preview( $club_id );

    if ( 'ufsc_add_licence' === $tag ) {
        $output = ufsc_p0_relabel_finalization_button( $output, $preview );
    }

    if ( 'ufsc_club_licences' === $tag && isset( $_GET['edit_licence'] ) ) {
        $output = ufsc_p0_relabel_finalization_button( $output, $preview );
    }

    // A renewal batch can straddle the last included credit, so its CTA must be
    // neutral rather than promise that every selected dossier goes to the cart.
    if ( 'ufsc_club_licences' === $tag && false !== strpos( $output, 'name="ufsc_renew_intent" value="add_to_cart"' ) ) {
        $output = preg_replace(
            '~(<button\b[^>]*name="ufsc_renew_intent"[^>]*value="add_to_cart"[^>]*>)(.*?)(</button>)~is',
            '$1' . esc_html__( 'Finaliser les licences — quota appliqué automatiquement', 'ufsc-clubs' ) . '$3',
            $output,
            1
        );
        $output = str_replace(
            __( 'Ajouter au panier', 'ufsc-clubs' ),
            __( 'Finaliser', 'ufsc-clubs' ),
            $output
        );
    }

    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_p0_relabel_quota_ui', 35, 4 );
