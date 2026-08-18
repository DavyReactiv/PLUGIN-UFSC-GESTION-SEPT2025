<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Canonical Club/Licence journey.
 * One decision source for included vs paid licences and one presentation layer
 * for the connected-club/affiliation journey.
 */

function ufsc_journey_current_season() {
    return class_exists( 'UFSC_Season_Service' )
        ? (string) UFSC_Season_Service::get_current_season()
        : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
}

function ufsc_journey_pack_state( $club_id, $season = '' ) {
    $club_id = absint( $club_id );
    $season  = $season ? str_replace( '/', '-', sanitize_text_field( $season ) ) : ufsc_journey_current_season();
    $limit   = function_exists( 'ufsc_get_pack_included_limit' ) ? absint( ufsc_get_pack_included_limit() ) : 10;
    $usage   = function_exists( 'ufsc_get_pack_usage' ) ? (array) ufsc_get_pack_usage( $club_id, $season ) : array();
    $used    = absint( $usage['total'] ?? ( $usage['used'] ?? 0 ) );

    return array(
        'club_id'   => $club_id,
        'season'    => $season,
        'limit'     => $limit,
        'used'      => min( $used, $limit ),
        'remaining' => max( 0, $limit - $used ),
        'included'  => $limit > 0 && $used < $limit,
    );
}

function ufsc_journey_get_licence( $licence_id ) {
    global $wpdb;
    $licence_id = absint( $licence_id );
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) { return null; }
    $table = ufsc_get_licences_table();
    return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $licence_id ) );
}

function ufsc_journey_can_manage_licence( $licence ) {
    if ( ! $licence ) { return false; }
    if ( current_user_can( 'manage_options' ) ) { return true; }
    $club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    return $club_id > 0 && $club_id === absint( $licence->club_id ?? 0 );
}

function ufsc_journey_licence_decision( $licence ) {
    $club_id = absint( $licence->club_id ?? 0 );
    $season  = function_exists( 'ufsc_get_licence_season_label' )
        ? (string) ufsc_get_licence_season_label( $licence )
        : (string) ( $licence->season ?? $licence->saison ?? ufsc_journey_current_season() );
    $state = ufsc_journey_pack_state( $club_id, $season );
    $payment_status = sanitize_key( (string) ( $licence->payment_status ?? '' ) );
    if ( ! empty( $licence->is_included ) || in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true ) ) {
        $state['included'] = true;
    }
    return $state;
}

function ufsc_journey_render_finalize_form( $licence ) {
    if ( ! $licence || ! ufsc_journey_can_manage_licence( $licence ) ) { return ''; }
    $id = absint( $licence->id ?? 0 );
    if ( $id < 1 ) { return ''; }
    $decision = ufsc_journey_licence_decision( $licence );
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-journey-finalize">
        <?php wp_nonce_field( 'ufsc_journey_finalize_' . $id ); ?>
        <input type="hidden" name="action" value="ufsc_journey_finalize_licence">
        <input type="hidden" name="licence_id" value="<?php echo esc_attr( $id ); ?>">
        <?php if ( $decision['included'] ) : ?>
            <div class="ufsc-journey-decision ufsc-journey-decision--included">
                <strong><?php esc_html_e( 'Incluse dans votre affiliation', 'ufsc-clubs' ); ?></strong>
                <span><?php echo esc_html( sprintf( __( '%1$d licence(s) incluse(s) restante(s) pour %2$s. Aucun paiement.', 'ufsc-clubs' ), $decision['remaining'], $decision['season'] ) ); ?></span>
            </div>
            <button type="submit" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Envoyer pour validation', 'ufsc-clubs' ); ?></button>
        <?php else : ?>
            <div class="ufsc-journey-decision ufsc-journey-decision--paid">
                <strong><?php esc_html_e( 'Licence supplémentaire', 'ufsc-clubs' ); ?></strong>
                <span><?php esc_html_e( 'Les 10 licences incluses sont utilisées. Cette licence doit être réglée.', 'ufsc-clubs' ); ?></span>
            </div>
            <button type="submit" class="ufsc-btn ufsc-btn-primary"><?php esc_html_e( 'Ajouter au panier — licence payante', 'ufsc-clubs' ); ?></button>
        <?php endif; ?>
    </form>
    <?php
    return ob_get_clean();
}

function ufsc_journey_finalize_licence() {
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) ); }
    $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
    check_admin_referer( 'ufsc_journey_finalize_' . $licence_id );
    $licence = ufsc_journey_get_licence( $licence_id );
    if ( ! $licence || ! ufsc_journey_can_manage_licence( $licence ) ) { wp_die( esc_html__( 'Licence inaccessible.', 'ufsc-clubs' ) ); }

    $club_id = absint( $licence->club_id ?? 0 );
    $season  = ufsc_journey_current_season();
    $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' )
        ? ufsc_club_can_manage_licences_for_season( $club_id, $season )
        : array( 'allowed' => false, 'message' => __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) );
    if ( empty( $gate['allowed'] ) ) {
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( (string) ( $gate['message'] ?? __( 'Affiliation annuelle inactive.', 'ufsc-clubs' ) ) ), wp_get_referer() ?: home_url() ) );
        exit;
    }

    $role = sanitize_key( (string) ( $licence->role ?? '' ) );
    $allocation = function_exists( 'ufsc_allocate_pack_credit' )
        ? ufsc_allocate_pack_credit( $licence_id, $club_id, $season, $role )
        : new WP_Error( 'quota_unavailable', __( 'Le quota d’affiliation est indisponible.', 'ufsc-clubs' ) );
    if ( is_wp_error( $allocation ) ) {
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( $allocation->get_error_message() ), wp_get_referer() ?: home_url() ) );
        exit;
    }

    if ( ! empty( $allocation['included'] ) ) {
        global $wpdb;
        $table = ufsc_get_licences_table();
        if ( class_exists( 'UFSC_Licence_Status' ) ) {
            UFSC_Licence_Status::update_status_columns( $table, array( 'id' => $licence_id, 'club_id' => $club_id ), 'en_attente', array( '%d', '%d' ) );
        } else {
            $wpdb->update( $table, array( 'statut' => 'en_attente' ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
        }
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        if ( in_array( 'payment_status', $columns, true ) ) {
            $wpdb->update( $table, array( 'payment_status' => 'included' ), array( 'id' => $licence_id ), array( '%s' ), array( '%d' ) );
        }
        do_action( 'ufsc_licence_updated', $club_id );
        wp_safe_redirect( add_query_arg( array( 'ufsc_message' => 'licence_included', 'licence_id' => $licence_id ), wp_get_referer() ?: home_url() ) );
        exit;
    }

    if ( ! function_exists( 'ufsc_handle_add_to_cart_secure' ) ) { wp_die( esc_html__( 'Le panier WooCommerce est indisponible.', 'ufsc-clubs' ) ); }
    $product_id = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
    if ( $product_id < 1 || ( function_exists( 'ufsc_is_woocommerce_product_available' ) && ! ufsc_is_woocommerce_product_available( $product_id ) ) ) {
        wp_safe_redirect( add_query_arg( 'ufsc_error', rawurlencode( __( 'Le produit Licence UFSC est indisponible. Le dossier a été conservé.', 'ufsc-clubs' ) ), wp_get_referer() ?: home_url() ) );
        exit;
    }
    $_POST['_ufsc_nonce']       = wp_create_nonce( 'ufsc_add_to_cart_action' );
    $_POST['product_id']        = $product_id;
    $_POST['ufsc_action']       = 'new_licence';
    $_POST['ufsc_club_id']      = $club_id;
    $_POST['ufsc_license_ids']  = (string) $licence_id;
    $_POST['ufsc_licence_id']   = $licence_id;
    ufsc_handle_add_to_cart_secure();
}
add_action( 'admin_post_ufsc_journey_finalize_licence', 'ufsc_journey_finalize_licence' );

function ufsc_journey_validated_count( $club_id, $season ) {
    if ( ! class_exists( 'UFSC_Stats' ) ) { return 0; }
    $stats = (array) UFSC_Stats::get_club_stats( absint( $club_id ), $season );
    return absint( $stats['validated_licences'] ?? 0 );
}

function ufsc_journey_existing_club_card( $club_id ) {
    $club_id = absint( $club_id );
    $club = function_exists( 'ufsc_get_user_club' ) ? ufsc_get_user_club( get_current_user_id() ) : null;
    $season = ufsc_journey_current_season();
    $annual = class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliation( $club_id, $season ) : null;
    $active = $annual && in_array( sanitize_key( (string) $annual->status ), array( 'active', 'valide', 'validated' ), true );
    $account_url = class_exists( 'UFSC_Frontend_Shortcodes' ) ? UFSC_Frontend_Shortcodes::get_club_portal_url( 'club-information' ) : home_url( '/compte-club/' );
    $dashboard_url = class_exists( 'UFSC_Frontend_Shortcodes' ) ? UFSC_Frontend_Shortcodes::get_club_portal_url( 'overview' ) : home_url( '/tableau-de-bord-club/' );
    ob_start(); ?>
    <section class="ufsc-card ufsc-existing-club-card">
        <span class="ufsc-existing-club-card__status"><?php echo $active ? '✓ ' . esc_html__( 'Affiliation active', 'ufsc-clubs' ) : esc_html__( 'Club déjà enregistré', 'ufsc-clubs' ); ?></span>
        <h3><?php esc_html_e( 'Votre club dispose déjà de son espace UFSC', 'ufsc-clubs' ); ?></h3>
        <p><?php echo esc_html( sprintf( __( '%1$s est déjà rattaché à votre compte pour la saison %2$s. Il n’est pas nécessaire de créer une nouvelle affiliation.', 'ufsc-clubs' ), $club->nom ?? __( 'Votre club', 'ufsc-clubs' ), $season ) ); ?></p>
        <p class="ufsc-existing-club-card__help"><?php esc_html_e( 'Vous pouvez consulter votre dossier, compléter les informations demandées et gérer vos licences depuis votre espace club.', 'ufsc-clubs' ); ?></p>
        <div class="ufsc-existing-club-card__actions"><a class="ufsc-btn ufsc-btn-primary" href="<?php echo esc_url( $dashboard_url ); ?>"><?php esc_html_e( 'Accéder à mon tableau de bord', 'ufsc-clubs' ); ?></a><a class="ufsc-btn ufsc-btn-secondary" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Consulter mon affiliation', 'ufsc-clubs' ); ?></a></div>
    </section><?php return ob_get_clean();
}

function ufsc_journey_trace_box( $club_id, $season ) {
    if ( ! class_exists( 'UFSC_Season_Archive_Manager' ) ) { return ''; }
    $affiliation = UFSC_Season_Archive_Manager::get_affiliation( absint( $club_id ), $season );
    if ( ! $affiliation ) { return ''; }
    $fmt = static function( $value, $unknown ) {
        $value = trim( (string) $value );
        if ( '' === $value || '0000-00-00 00:00:00' === $value ) { return $unknown; }
        return function_exists( 'mysql2date' ) ? mysql2date( 'd/m/Y à H:i', $value ) : $value;
    };
    $created   = $fmt( $affiliation->created_at ?? '', __( 'Date historique non disponible', 'ufsc-clubs' ) );
    $submitted = $fmt( $affiliation->requested_at ?? '', __( 'Information non disponible', 'ufsc-clubs' ) );
    $paid      = $fmt( $affiliation->paid_at ?? '', __( 'Information de règlement non disponible dans l’historique', 'ufsc-clubs' ) );
    $validated = $fmt( $affiliation->validated_at ?? '', __( 'En attente de validation', 'ufsc-clubs' ) );
    ob_start(); ?>
    <section class="ufsc-card ufsc-affiliation-trace ufsc-affiliation-trace--premium">
        <div class="ufsc-affiliation-trace__head"><span><?php esc_html_e( 'Suivi de votre affiliation', 'ufsc-clubs' ); ?></span><strong><?php echo esc_html( $season ); ?></strong></div>
        <ol class="ufsc-affiliation-trace__steps">
            <li><span class="ufsc-trace-dot">✓</span><div><strong><?php esc_html_e( 'Demande créée', 'ufsc-clubs' ); ?></strong><small><?php echo esc_html( $created ); ?></small></div></li>
            <li><span class="ufsc-trace-dot">✓</span><div><strong><?php esc_html_e( 'Demande transmise', 'ufsc-clubs' ); ?></strong><small><?php echo esc_html( $submitted ); ?></small></div></li>
            <li><span class="ufsc-trace-dot">€</span><div><strong><?php esc_html_e( 'Règlement', 'ufsc-clubs' ); ?></strong><small><?php echo esc_html( $paid ); ?></small></div></li>
            <li><span class="ufsc-trace-dot">✓</span><div><strong><?php esc_html_e( 'Affiliation validée', 'ufsc-clubs' ); ?></strong><small><?php echo esc_html( $validated ); ?> · <?php esc_html_e( 'Validation : UFSC', 'ufsc-clubs' ); ?></small></div></li>
        </ol>
    </section><?php return ob_get_clean();
}

function ufsc_journey_filter_shortcode_output( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    if ( ! is_user_logged_in() ) { return $output; }
    $club_id = function_exists( 'ufsc_get_user_club_id' ) ? absint( ufsc_get_user_club_id( get_current_user_id() ) ) : 0;
    $season  = ufsc_journey_current_season();

    if ( 'ufsc_login_form' === $tag && $club_id > 0 ) {
        $validated = ufsc_journey_validated_count( $club_id, $season );
        $output = preg_replace( '~(<dt>\s*Licences\s+courantes\s*</dt>\s*<dd>)\s*\d+~iu', '$1' . $validated, $output, 1 );
        $output = str_replace( 'Licences courantes', 'Licences actives ' . esc_html( $season ), $output );
    }

    if ( 'ufsc_club_form' === $tag && $club_id > 0 && false !== strpos( $output, 'La gestion de plusieurs clubs' ) ) {
        return ufsc_journey_existing_club_card( $club_id );
    }

    if ( 'ufsc_club_profile' === $tag && $club_id > 0 ) {
        $trace = ufsc_journey_trace_box( $club_id, $season );
        if ( $trace ) {
            $output = preg_replace( '~<section class="ufsc-card ufsc-affiliation-trace">.*?</section>~is', $trace, $output, 1 );
        }
    }

    // The creation/edit form may be rendered by different shortcode aliases.
    if ( false !== strpos( $output, 'ufsc-licence-final-actions' ) && $club_id > 0 ) {
        $decision = ufsc_journey_pack_state( $club_id, $season );
        if ( $decision['included'] ) {
            $output = str_replace( 'Vérification obligatoire avant paiement', 'Vérification obligatoire avant envoi', $output );
            $output = str_replace( 'Une fois le paiement effectué, la licence passe en traitement et ne peut plus être modifiée en autonomie.', 'Une fois la demande envoyée, la licence passe en traitement et ne peut plus être modifiée en autonomie.', $output );
            $output = str_replace( 'Toute correction demandée après paiement est soumise à des frais de traitement administratif de 5 €.', 'Vérifiez soigneusement les informations avant l’envoi pour éviter toute demande de correction.', $output );
            $output = str_replace( 'Enregistrez un brouillon pour compléter la licence plus tard. Ajoutez au panier uniquement lorsque toutes les informations ont été vérifiées.', 'Enregistrez un brouillon pour compléter la licence plus tard. Cette licence est incluse dans votre affiliation et ne nécessite aucun paiement.', $output );
            $output = preg_replace( '~(<button[^>]+name="ufsc_submit_action"[^>]+value="add_to_cart"[^>]*>)\s*Ajouter au panier\s*(</button>)~iu', '$1' . esc_html__( 'Envoyer pour validation — inclus dans votre affiliation', 'ufsc-clubs' ) . '$2', $output, 1 );
        }
    }
    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_journey_filter_shortcode_output', 100, 4 );

function ufsc_journey_enqueue_assets() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    wp_enqueue_style( 'ufsc-club-journey', UFSC_CL_URL . 'assets/css/ufsc-club-journey.css', array( 'ufsc-front' ), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-club-journey.css' ) : UFSC_CL_VERSION );
}
add_action( 'wp_enqueue_scripts', 'ufsc_journey_enqueue_assets', 35 );
