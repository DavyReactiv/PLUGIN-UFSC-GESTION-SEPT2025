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

/**
 * The affiliation includes ten licences as a business rule, not as an optional
 * checkout preference. Keep the legacy setting readable but never let a saved
 * false value bypass the included quota and send an included licence to Woo.
 */
function ufsc_journey_enforce_pack_business_rule( $settings ) {
    $settings = is_array( $settings ) ? $settings : array();
    $settings['auto_consume_included'] = 1;
    return $settings;
}
add_filter( 'option_ufsc_woocommerce_settings', 'ufsc_journey_enforce_pack_business_rule', 100 );

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
    // A historical licence is renewed into the active season. Quota decisions
    // always belong to the target season, never to the archived source season.
    $licence_season = function_exists( 'ufsc_get_licence_season_label' )
        ? (string) ufsc_get_licence_season_label( $licence )
        : (string) ( $licence->season ?? $licence->saison ?? '' );
    $current_season = ufsc_journey_current_season();
    $season = $licence_season && $licence_season === $current_season ? $licence_season : $current_season;
    $state = ufsc_journey_pack_state( $club_id, $season );
    $payment_status = sanitize_key( (string) ( $licence->payment_status ?? '' ) );
    if ( $licence_season === $current_season && ( ! empty( $licence->is_included ) || in_array( $payment_status, array( 'included', 'incluse', 'pack', 'included_pack' ), true ) ) ) {
        $state['included'] = true;
    }
    $state['source_season'] = $licence_season;
    return $state;
}

/** Persist a lightweight submission audit without requiring a destructive schema migration. */
function ufsc_journey_record_submission( $licence_id, $club_id, $season, $context = 'club' ) {
    global $wpdb;
    $licence_id = absint( $licence_id );
    $club_id    = absint( $club_id );
    if ( $licence_id < 1 || $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) { return; }
    $table   = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $now     = current_time( 'mysql' );
    foreach ( array( 'submitted_at', 'requested_at', 'date_soumission' ) as $column ) {
        if ( in_array( $column, $columns, true ) ) {
            $wpdb->update( $table, array( $column => $now ), array( 'id' => $licence_id, 'club_id' => $club_id ), array( '%s' ), array( '%d', '%d' ) );
            break;
        }
    }
    update_option(
        'ufsc_licence_submission_' . $licence_id,
        array(
            'licence_id' => $licence_id,
            'club_id'    => $club_id,
            'season'     => sanitize_text_field( $season ),
            'submitted_at' => $now,
            'submitted_by' => get_current_user_id(),
            'context'      => sanitize_key( $context ),
        ),
        false
    );
}

/** Capture included submissions performed by the canonical unified handler. */
function ufsc_journey_capture_pending_submission( $club_id ) {
    static $running = false;
    if ( $running || ! function_exists( 'ufsc_get_licences_table' ) ) { return; }
    $running = true;
    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $status_col = in_array( 'statut', $columns, true ) ? 'statut' : ( in_array( 'status', $columns, true ) ? 'status' : '' );
    if ( $status_col ) {
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE club_id=%d AND `{$status_col}`=%s ORDER BY id DESC LIMIT 20", absint( $club_id ), 'en_attente' ) );
        foreach ( (array) $rows as $row ) {
            $payment = sanitize_key( (string) ( $row->payment_status ?? '' ) );
            if ( in_array( $payment, array( 'included', 'incluse', 'pack', 'included_pack' ), true ) && ! get_option( 'ufsc_licence_submission_' . absint( $row->id ), false ) ) {
                ufsc_journey_record_submission( $row->id, $club_id, ufsc_journey_current_season(), 'club_included' );
            }
        }
    }
    $running = false;
}
add_action( 'ufsc_licence_updated', 'ufsc_journey_capture_pending_submission', 50, 1 );

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
        ufsc_journey_record_submission( $licence_id, $club_id, $season, 'club_included' );
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

/** Build the renewal wizard URL for one historical source licence. */
function ufsc_journey_renewal_url( $licence_id, $target_season ) {
    $base = class_exists( 'UFSC_Frontend_Shortcodes' )
        ? UFSC_Frontend_Shortcodes::get_club_portal_url( 'licences-renouvellement' )
        : home_url( '/tableau-de-bord-club/' );
    return add_query_arg(
        array(
            'ufsc_section'    => 'licences-renouvellement',
            'renew_source_id' => absint( $licence_id ),
            'target_season'   => sanitize_text_field( $target_season ),
            'ufsc_renew_step' => 2,
        ),
        $base
    ) . '#ufsc-renouvellement';
}

/** Replace one direct-cart form with the canonical licence/renewal decision. */
function ufsc_journey_replace_detail_cart_form( $output, $licence ) {
    if ( ! $licence ) { return $output; }
    $id = absint( $licence->id ?? 0 );
    if ( $id < 1 ) { return $output; }
    $decision = ufsc_journey_licence_decision( $licence );
    $current  = ufsc_journey_current_season();
    $source   = (string) ( $decision['source_season'] ?? '' );
    $form_pattern = '~<form\b[^>]*>(?:(?!</form>).)*?<input[^>]+name=["\']action["\'][^>]+value=["\']ufsc_add_to_cart["\'][^>]*>(?:(?!</form>).)*?<input[^>]+name=["\']ufsc_license_ids["\'][^>]+value=["\']' . preg_quote( (string) $id, '~' ) . '["\'][^>]*>(?:(?!</form>).)*?</form>~is';

    if ( $source && $current && $source !== $current ) {
        $label = $decision['included']
            ? __( 'Renouveler — inclus dans votre affiliation', 'ufsc-clubs' )
            : __( 'Renouveler cette licence', 'ufsc-clubs' );
        $replacement = '<div class="ufsc-journey-renewal-cta"><div class="ufsc-journey-decision ' . ( $decision['included'] ? 'ufsc-journey-decision--included' : 'ufsc-journey-decision--paid' ) . '"><strong>'
            . ( $decision['included'] ? esc_html__( 'Renouvellement inclus', 'ufsc-clubs' ) : esc_html__( 'Renouvellement supplémentaire', 'ufsc-clubs' ) )
            . '</strong><span>'
            . ( $decision['included'] ? esc_html( sprintf( __( '%d licence(s) incluse(s) restante(s). Le renouvellement ne passe pas par le panier.', 'ufsc-clubs' ), $decision['remaining'] ) ) : esc_html__( 'Le quota inclus est utilisé. Le paiement sera demandé après vérification.', 'ufsc-clubs' ) )
            . '</span></div><a class="ufsc-btn ufsc-btn-primary" href="' . esc_url( ufsc_journey_renewal_url( $id, $current ) ) . '">' . esc_html( $label ) . '</a></div>';
        return preg_replace( $form_pattern, $replacement, $output, 1 );
    }

    $status = function_exists( 'ufsc_get_licence_status_from_record' ) ? ufsc_get_licence_status_from_record( $licence ) : sanitize_key( (string) ( $licence->statut ?? '' ) );
    if ( in_array( $status, array( 'brouillon', 'non_payee', 'a_regler' ), true ) ) {
        $finalize = ufsc_journey_render_finalize_form( $licence );
        if ( $finalize ) { return preg_replace( $form_pattern, $finalize, $output, 1 ); }
    }
    return $output;
}

/** Present the renewal assistant according to the current pack state. */
function ufsc_journey_filter_renewal_wizard( $output, $club_id, $season ) {
    if ( false === strpos( $output, 'ufsc-renewal-wizard' ) ) { return $output; }
    $state = ufsc_journey_pack_state( $club_id, $season );
    if ( ! $state['included'] ) {
        $output = str_replace( '>Ajouter au panier<', '>Ajouter au panier — licences payantes<', $output );
        return $output;
    }

    $banner = '<div class="ufsc-journey-renewal-quota" role="status"><strong>' . esc_html__( 'Renouvellements inclus disponibles', 'ufsc-clubs' ) . '</strong><span>'
        . esc_html( sprintf( __( '%1$d/%2$d utilisées — %3$d restante(s). Les renouvellements utilisent d’abord votre quota, sans paiement.', 'ufsc-clubs' ), $state['used'], $state['limit'], $state['remaining'] ) ) . '</span></div>';
    $output = preg_replace( '~(<p class="ufsc-renewal-season-context"[^>]*>.*?</p>)~is', '$1' . $banner, $output, 1 );
    $output = preg_replace( '~(<li[^>]+data-ufsc-step-indicator=["\']3["\'][^>]*>.*?<strong>3</strong>)\s*Ajouter au panier(.*?</li>)~isu', '$1 ' . esc_html__( 'Finaliser', 'ufsc-clubs' ) . '$2', $output, 1 );
    $output = preg_replace_callback(
        '~<button\b(?=[^>]*name=["\']ufsc_renew_intent["\'])(?=[^>]*value=["\']add_to_cart["\'])[^>]*>.*?</button>~is',
        static function( $match ) {
            $button = preg_replace( '~\sdisabled(?:=["\']disabled["\'])?~i', '', $match[0] );
            $button = preg_replace( '~data-ufsc-product-ready=["\'][01]["\']~i', 'data-ufsc-product-ready="1"', $button );
            $button = preg_replace( '~>\s*Ajouter au panier\s*</button>~iu', '>' . esc_html__( 'Finaliser — quota inclus en priorité', 'ufsc-clubs' ) . '</button>', $button );
            return $button;
        },
        $output,
        1
    );
    $output = preg_replace( '~(<span[^>]+id=["\']ufsc-cart-readiness["\'][^>]*>).*?(</span>)~is', '$1' . esc_html__( 'Les licences sélectionnées consommeront d’abord les places incluses. Seules celles au-delà du quota nécessiteront un panier.', 'ufsc-clubs' ) . '$2', $output, 1 );
    return $output;
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
            $output = str_replace( 'Une fois le paiement effectué, la licence passe en traitement et ne peut plus être modifiée en autonomie.', 'Une fois la demande envoyée, la licence passe en traitement UFSC et ne peut plus être modifiée en autonomie.', $output );
            $output = str_replace( 'Toute correction demandée après paiement est soumise à des frais de traitement administratif de 5 €.', 'Vérifiez soigneusement les informations avant l’envoi pour éviter toute demande de correction.', $output );
            $output = str_replace( 'Enregistrez un brouillon pour compléter la licence plus tard. Ajoutez au panier uniquement lorsque toutes les informations ont été vérifiées.', 'Enregistrez un brouillon pour compléter la licence plus tard. Cette licence est incluse dans votre affiliation et ne nécessite aucun paiement.', $output );
            $output = preg_replace( '~(<button[^>]+name="ufsc_submit_action"[^>]+value="add_to_cart"[^>]*>)\s*Ajouter au panier\s*(</button>)~iu', '$1' . esc_html__( 'Envoyer pour validation — inclus dans votre affiliation', 'ufsc-clubs' ) . '$2', $output, 1 );
        } else {
            $output = preg_replace( '~(<button[^>]+name="ufsc_submit_action"[^>]+value="add_to_cart"[^>]*>)\s*Ajouter au panier\s*(</button>)~iu', '$1' . esc_html__( 'Ajouter au panier — licence supplémentaire payante', 'ufsc-clubs' ) . '$2', $output, 1 );
        }
    }

    if ( $club_id > 0 ) {
        $output = ufsc_journey_filter_renewal_wizard( $output, $club_id, $season );
    }

    $view_id = isset( $_GET['view_licence'] ) ? absint( wp_unslash( $_GET['view_licence'] ) ) : 0;
    if ( $view_id > 0 && $club_id > 0 ) {
        $licence = ufsc_journey_get_licence( $view_id );
        if ( $licence && ufsc_journey_can_manage_licence( $licence ) ) {
            $output = ufsc_journey_replace_detail_cart_form( $output, $licence );
        }
    }
    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_journey_filter_shortcode_output', 100, 4 );

/** Admin visibility: make club submissions impossible to miss. */
function ufsc_journey_admin_pending_notice() {
    if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! function_exists( 'ufsc_get_licences_table' ) ) { return; }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $pages = array( 'ufsc_lc_licences', 'ufsc-gestion-licences', 'ufsc-licences', 'ufsc-sql-licences', 'ufsc-sql-licenses' );
    if ( ! in_array( $page, $pages, true ) ) { return; }
    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $status_col = in_array( 'statut', $columns, true ) ? 'statut' : ( in_array( 'status', $columns, true ) ? 'status' : '' );
    if ( ! $status_col ) { return; }
    $season = ufsc_journey_current_season();
    $where = "`{$status_col}` = %s";
    $args  = array( 'en_attente' );
    foreach ( array( 'season', 'saison', 'paid_season' ) as $season_col ) {
        if ( in_array( $season_col, $columns, true ) ) {
            $where .= " AND `{$season_col}` = %s";
            $args[] = $season;
            break;
        }
    }
    $count = absint( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", ...$args ) ) );
    if ( $count < 1 ) { return; }
    echo '<div class="notice notice-warning"><p><strong>' . esc_html( sprintf( _n( '%d licence envoyée par un club est à valider.', '%d licences envoyées par les clubs sont à valider.', $count, 'ufsc-clubs' ), $count ) ) . '</strong> ' . esc_html( sprintf( __( 'Saison %s — ces dossiers ne sont plus des brouillons.', 'ufsc-clubs' ), $season ) ) . '</p></div>';
}
add_action( 'admin_notices', 'ufsc_journey_admin_pending_notice', 20 );

function ufsc_journey_enqueue_assets() {
    if ( is_admin() || ! defined( 'UFSC_CL_URL' ) ) { return; }
    wp_enqueue_style( 'ufsc-club-journey', UFSC_CL_URL . 'assets/css/ufsc-club-journey.css', array( 'ufsc-front' ), function_exists( 'ufsc_asset_version' ) ? ufsc_asset_version( 'assets/css/ufsc-club-journey.css' ) : UFSC_CL_VERSION );
}
add_action( 'wp_enqueue_scripts', 'ufsc_journey_enqueue_assets', 35 );
