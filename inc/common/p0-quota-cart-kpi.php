<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * P0 DEV-recipe fixes: one server decision for included-vs-paid licences,
 * actionable licence detail CTA, validated-only season KPI and safe profile
 * enrichment placement. No historical row is rewritten by these display fixes.
 */

function ufsc_p0_current_season() {
    return class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
}

function ufsc_p0_get_licence_row( $licence_id ) {
    global $wpdb;
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) { return null; }
    $table = ufsc_get_licences_table();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
}

function ufsc_p0_pack_decision( $licence ) {
    $season = ufsc_p0_current_season();
    $club_id = absint( $licence->club_id ?? 0 );
    $limit = function_exists( 'ufsc_get_pack_included_limit' )
        ? absint( ufsc_get_pack_included_limit() )
        : absint( ( function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings()['included_licenses'] ?? 10 : 10 ) );
    $limit = max( 0, $limit );

    $payment_status = sanitize_key( (string) ( $licence->payment_status ?? '' ) );
    if ( in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true ) ) {
        return array( 'included' => true, 'used' => min( $limit, $limit ), 'limit' => $limit, 'remaining' => 0, 'season' => $season );
    }

    $usage = function_exists( 'ufsc_get_pack_usage' ) ? (array) ufsc_get_pack_usage( $club_id, $season ) : array();
    $used = isset( $usage['total'] ) ? absint( $usage['total'] ) : ( isset( $usage['used'] ) ? absint( $usage['used'] ) : 0 );
    $included = $limit > 0 && $used < $limit;

    return array(
        'included'  => $included,
        'used'      => min( $used, $limit ),
        'limit'     => $limit,
        'remaining' => max( 0, $limit - $used ),
        'season'    => $season,
    );
}

function ufsc_p0_user_can_manage_licence( $licence ) {
    if ( ! $licence ) { return false; }
    if ( current_user_can( 'manage_options' ) ) { return true; }
    $user_club = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    return $user_club > 0 && $user_club === absint( $licence->club_id ?? 0 );
}

function ufsc_p0_render_licence_decision_form( $licence ) {
    if ( ! $licence || ! ufsc_p0_user_can_manage_licence( $licence ) ) { return ''; }

    $decision = ufsc_p0_pack_decision( $licence );
    $licence_id = absint( $licence->id ?? 0 );
    $club_id = absint( $licence->club_id ?? 0 );
    if ( $licence_id < 1 || $club_id < 1 ) { return ''; }

    $product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
    $product_ready = $product_id > 0 && ( ! function_exists( 'ufsc_is_woocommerce_product_available' ) || ufsc_is_woocommerce_product_available( $product_id ) );
    $next_position = min( $decision['limit'], $decision['used'] + 1 );

    ob_start();
    ?>
    <div class="ufsc-p0-licence-decision <?php echo $decision['included'] ? 'ufsc-p0-licence-decision--included' : 'ufsc-p0-licence-decision--paid'; ?>">
        <?php if ( $decision['included'] ) : ?>
            <strong><?php esc_html_e( 'Licence incluse dans votre affiliation', 'ufsc-clubs' ); ?></strong>
            <p><?php echo esc_html( sprintf( __( 'Cette licence utilisera la place %1$d/%2$d incluse dans votre affiliation %3$s. Aucun paiement n’est nécessaire.', 'ufsc-clubs' ), $next_position, $decision['limit'], $decision['season'] ) ); ?></p>
        <?php else : ?>
            <strong><?php esc_html_e( 'Licence supplémentaire — paiement requis', 'ufsc-clubs' ); ?></strong>
            <p><?php echo esc_html( sprintf( __( 'Le quota de %1$d licences incluses pour %2$s est atteint. Cette licence doit être réglée via WooCommerce.', 'ufsc-clubs' ), $decision['limit'], $decision['season'] ) ); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-p0-finalize-form">
            <?php wp_nonce_field( 'ufsc_p0_finalize_licence_' . $licence_id, '_wpnonce' ); ?>
            <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
            <input type="hidden" name="action" value="ufsc_p0_finalize_licence">
            <input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( $club_id ); ?>">
            <input type="hidden" name="ufsc_license_ids" value="<?php echo esc_attr( $licence_id ); ?>">
            <input type="hidden" name="ufsc_licence_id" value="<?php echo esc_attr( $licence_id ); ?>">
            <input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">
            <button type="submit" class="ufsc-btn ufsc-btn-small ufsc-btn-primary" <?php disabled( ! $decision['included'] && ! $product_ready ); ?>>
                <?php echo $decision['included'] ? esc_html__( 'Envoyer pour validation — inclus dans votre affiliation', 'ufsc-clubs' ) : esc_html__( 'Ajouter au panier — licence payante', 'ufsc-clubs' ); ?>
            </button>
            <?php if ( ! $decision['included'] && ! $product_ready ) : ?>
                <p class="ufsc-message ufsc-warning"><?php esc_html_e( 'Le produit Licence UFSC n’est pas disponible. Vérifiez sa configuration WooCommerce.', 'ufsc-clubs' ); ?></p>
            <?php endif; ?>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Finalize one current-season licence. Allocation is performed server-side at
 * click time to avoid races: included => no cart line; exhausted => canonical
 * WooCommerce handler receives the canonical product ID and the original nonce.
 */
function ufsc_p0_handle_finalize_licence() {
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $licence_id = isset( $_POST['ufsc_licence_id'] ) ? absint( wp_unslash( $_POST['ufsc_licence_id'] ) ) : 0;
    check_admin_referer( 'ufsc_p0_finalize_licence_' . $licence_id );
    $licence = ufsc_p0_get_licence_row( $licence_id );
    if ( ! $licence || ! ufsc_p0_user_can_manage_licence( $licence ) ) {
        wp_die( esc_html__( 'Licence inaccessible.', 'ufsc-clubs' ) );
    }

    $club_id = absint( $licence->club_id ?? 0 );
    $season = ufsc_p0_current_season();
    $licence_season = function_exists( 'ufsc_get_licence_season_label' ) ? (string) ufsc_get_licence_season_label( $licence ) : ( function_exists( 'ufsc_get_licence_season' ) ? (string) ufsc_get_licence_season( $licence_id ) : $season );
    if ( $season && $licence_season && str_replace( '/', '-', $licence_season ) !== str_replace( '/', '-', $season ) ) {
        wp_die( esc_html__( 'Cette licence appartient à une autre saison et ne peut pas être finalisée depuis ce dossier.', 'ufsc-clubs' ) );
    }

    $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $season ) : array( 'allowed' => false );
    if ( empty( $gate['allowed'] ) ) {
        $redirect = wp_get_referer() ?: home_url();
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( (string) ( $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) ) ), $redirect ) );
        exit;
    }

    $role = sanitize_key( (string) ( $licence->role ?? '' ) );
    $allocation = function_exists( 'ufsc_allocate_pack_credit' ) ? ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role ) : array( 'included' => false );
    if ( is_wp_error( $allocation ) ) {
        $redirect = wp_get_referer() ?: home_url();
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( $allocation->get_error_message() ), $redirect ) );
        exit;
    }

    if ( ! empty( $allocation['included'] ) ) {
        global $wpdb;
        $table = ufsc_get_licences_table();
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        if ( class_exists( 'UFSC_Licence_Status' ) ) {
            UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id, 'club_id' => $club_id ), 'en_attente', array( '%d', '%d' ) );
        } elseif ( in_array( 'statut', $columns, true ) ) {
            $wpdb->update( $table, array( 'statut' => 'en_attente' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
        }
        if ( in_array( 'payment_status', $columns, true ) ) {
            $wpdb->update( $table, array( 'payment_status' => 'included' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
        }
        do_action( 'ufsc_licence_updated', $club_id );
        $redirect = wp_get_referer() ?: home_url();
        wp_safe_redirect( add_query_arg( array( 'licence_included' => 1, 'licence_id' => $licence_id ), $redirect ) );
        exit;
    }

    if ( ! function_exists( 'ufsc_handle_add_to_cart_secure' ) ) {
        wp_die( esc_html__( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ) );
    }
    $_POST['product_id'] = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
    $_POST['action'] = 'ufsc_add_to_cart';
    $_POST['ufsc_club_id'] = $club_id;
    $_POST['ufsc_license_ids'] = (string) $licence_id;
    ufsc_handle_add_to_cart_secure();
}
add_action( 'admin_post_ufsc_p0_finalize_licence', 'ufsc_p0_handle_finalize_licence' );

/** Correct #537 placement: remove its first-form injection before installing ours. */
if ( function_exists( 'ufsc_enrich_club_profile_shortcode_output' ) ) {
    remove_filter( 'do_shortcode_tag', 'ufsc_enrich_club_profile_shortcode_output', 20 );
}

function ufsc_p0_enrich_shortcode_output( $output, $tag, $attr, $m ) {
    unset( $attr, $m );

    if ( 'ufsc_club_profile' === $tag && is_user_logged_in() && function_exists( 'ufsc_get_user_club_id' ) ) {
        $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
        if ( $club_id > 0 && function_exists( 'ufsc_render_account_action_box' ) && function_exists( 'ufsc_render_affiliation_trace_box' ) ) {
            $insert = ufsc_render_account_action_box( $club_id, ufsc_p0_current_season() ) . ufsc_render_affiliation_trace_box( $club_id, ufsc_p0_current_season() );
            $class_pos = strpos( $output, 'class="ufsc-club-form ufsc-club-profile"' );
            if ( false !== $class_pos ) {
                $before = substr( $output, 0, $class_pos );
                $form_pos = strrpos( $before, '<form' );
                if ( false !== $form_pos ) {
                    $output = substr( $output, 0, $form_pos ) . $insert . substr( $output, $form_pos );
                }
            }
        }
    }

    if ( 'ufsc_club_dashboard' === $tag && is_user_logged_in() && class_exists( 'UFSC_Stats' ) && function_exists( 'ufsc_get_user_club_id' ) ) {
        $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
        $season = ufsc_p0_current_season();
        $stats = $club_id > 0 ? UFSC_Stats::get_club_stats( $club_id, $season ) : array();
        $validated = absint( $stats['validated_licences'] ?? 0 );
        $label = sprintf( __( 'Licences %s', 'ufsc-clubs' ), $season );
        $replacement_label = sprintf( __( 'Licences actives %s', 'ufsc-clubs' ), $season );
        $pattern = '~(<span class="ufsc-kpi-tile-label">)' . preg_quote( esc_html( $label ), '~' ) . '(</span>\s*<strong class="ufsc-kpi-tile-value">)\d+(</strong>)~u';
        $output = preg_replace( $pattern, '$1' . esc_html( $replacement_label ) . '$2' . $validated . '$3', $output, 1 );
    }

    if ( in_array( $tag, array( 'ufsc_club_licences', 'ufsc_licences' ), true ) && is_user_logged_in() ) {
        $licence_id = isset( $_GET['view_licence'] ) ? absint( wp_unslash( $_GET['view_licence'] ) ) : 0;
        if ( $licence_id > 0 ) {
            $licence = ufsc_p0_get_licence_row( $licence_id );
            if ( $licence && ufsc_p0_user_can_manage_licence( $licence ) ) {
                $id = preg_quote( (string) $licence_id, '~' );
                $form_pattern = '~<form\b[^>]*>(?:(?!</form>).)*?<input[^>]+name="ufsc_license_ids"[^>]+value="' . $id . '"[^>]*>(?:(?!</form>).)*?</form>~is';
                $decision_form = ufsc_p0_render_licence_decision_form( $licence );
                if ( '' !== $decision_form ) {
                    $output = preg_replace( $form_pattern, $decision_form, $output, 1 );
                }
            }
        }
    }

    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_p0_enrich_shortcode_output', 30, 4 );

function ufsc_p0_enqueue_layout_css() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    wp_enqueue_style(
        'ufsc-p0-quota-cart-kpi',
        UFSC_CL_URL . 'assets/css/ufsc-p0-quota-cart-kpi.css',
        array( 'ufsc-front', 'ufsc-club-mobile-v2' ),
        function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-p0-quota-cart-kpi.css' ) : ( defined( 'UFSC_CL_VERSION' ) ? UFSC_CL_VERSION : null )
    );
}
add_action( 'wp_enqueue_scripts', 'ufsc_p0_enqueue_layout_css', 40 );
