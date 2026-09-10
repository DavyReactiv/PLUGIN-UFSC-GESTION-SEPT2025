<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production hotfix for the club licence portal.
 *
 * Scope is deliberately narrow:
 * - keep the canonical included-quota finalisation on its final path;
 * - remove obsolete ASPTT/FSASPTT/CR opt-ins from active FFST forms without
 *   overwriting an existing current-season value during an edit;
 * - add short contextual guidance around quota finalisation;
 * - make plugin-rendered logout use a nonce-protected POST action.
 *
 * No schema migration and no historical row rewrite is performed here.
 */

/** @return string[] */
function ufsc_portal_hotfix_legacy_optin_fields() {
    return array( 'infos_fsasptt', 'infos_asptt', 'infos_cr' );
}

/** Capture the original final intent before the canonical runtime can normalise it. */
function ufsc_portal_hotfix_capture_final_request() {
    $GLOBALS['ufsc_portal_hotfix_final_request'] = false;
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return;
    }
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( ! in_array( $action, array( 'ufsc_add_licence', 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
        return;
    }
    $intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';
    $GLOBALS['ufsc_portal_hotfix_final_request'] = in_array( $intent, array( 'add_to_cart', 'submit_for_validation' ), true );
}
add_action( 'admin_init', 'ufsc_portal_hotfix_capture_final_request', -1 );

/**
 * Preserve old communication opt-ins on an existing row when the active FFST
 * form no longer displays them. A zero stays zero; a stored one stays one.
 */
function ufsc_portal_hotfix_preserve_existing_legacy_optins() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return;
    }
    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    if ( ! in_array( $action, array( 'ufsc_save_licence', 'ufsc_update_licence' ), true ) ) {
        return;
    }
    $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0;
    if ( $licence_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    $missing = array();
    foreach ( ufsc_portal_hotfix_legacy_optin_fields() as $field ) {
        if ( ! array_key_exists( $field, $_POST ) ) {
            $missing[] = $field;
        }
    }
    if ( ! $missing ) {
        return;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
    $select = array_values( array_intersect( $missing, $columns ) );
    if ( ! $select ) {
        return;
    }
    $quoted = implode( ', ', array_map( static function( $field ) { return '`' . $field . '`'; }, $select ) );
    $row = $wpdb->get_row( $wpdb->prepare( "SELECT {$quoted} FROM `{$table}` WHERE id = %d LIMIT 1", $licence_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! $row ) {
        return;
    }
    foreach ( $select as $field ) {
        if ( ! empty( $row->{$field} ) ) {
            $_POST[ $field ] = '1';
        }
    }
}
add_action( 'admin_init', 'ufsc_portal_hotfix_preserve_existing_legacy_optins', -2 );

/** Restore the final intent when the canonical service has really reserved this licence. */
function ufsc_portal_hotfix_restore_included_intent( $licence_id, $club_id ) {
    if ( empty( $GLOBALS['ufsc_portal_hotfix_final_request'] ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }
    $licence_id = absint( $licence_id );
    $club_id    = absint( $club_id );
    if ( $licence_id < 1 || $club_id < 1 ) {
        return;
    }
    $intent = isset( $_POST['ufsc_submit_action'] ) && ! is_array( $_POST['ufsc_submit_action'] )
        ? sanitize_key( wp_unslash( $_POST['ufsc_submit_action'] ) )
        : '';
    if ( 'continue' !== $intent ) {
        return;
    }

    global $wpdb;
    $table = ufsc_get_licences_table();
    $reserved = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT is_included FROM `{$table}` WHERE id = %d AND club_id = %d LIMIT 1",
            $licence_id,
            $club_id
        )
    ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( 1 === $reserved ) {
        // The allocator is idempotent for this same licence. Keeping add_to_cart
        // here lets the Unified Handler reach its included-success branch and
        // never creates a Woo line while a pack credit is confirmed.
        $_POST['ufsc_submit_action'] = 'add_to_cart';
    }
}

function ufsc_portal_hotfix_restore_created_intent( $licence_id, $club_id ) {
    ufsc_portal_hotfix_restore_included_intent( $licence_id, $club_id );
}
add_action( 'ufsc_licence_created', 'ufsc_portal_hotfix_restore_created_intent', 1, 2 );

function ufsc_portal_hotfix_restore_updated_intent( $club_id ) {
    $licence_id = isset( $_POST['licence_id'] ) ? absint( wp_unslash( $_POST['licence_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- observer runs inside the verified licence request.
    if ( $licence_id > 0 ) {
        ufsc_portal_hotfix_restore_included_intent( $licence_id, $club_id );
    }
}
add_action( 'ufsc_licence_updated', 'ufsc_portal_hotfix_restore_updated_intent', 1, 1 );

/** Remove one legacy opt-in label from rendered active forms. */
function ufsc_portal_hotfix_strip_legacy_optin_label( $output, $field ) {
    $field = preg_quote( $field, '~' );
    $pattern = '~<label\b[^>]*>\s*<input\b(?=[^>]*type=["\']checkbox["\'])(?=[^>]*name=["\'][^"\']*' . $field . '[^"\']*["\'])[^>]*>.*?</label>~isu';
    return (string) preg_replace( $pattern, '', $output );
}

/** Add concise guidance and remove obsolete partner choices from active FFST UI. */
function ufsc_portal_hotfix_filter_licence_ui( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    if ( ! in_array( $tag, array( 'ufsc_add_licence', 'ufsc_club_licences' ), true ) ) {
        return $output;
    }

    foreach ( ufsc_portal_hotfix_legacy_optin_fields() as $field ) {
        $output = ufsc_portal_hotfix_strip_legacy_optin_label( $output, $field );
    }
    $output = preg_replace( '~<div class=["\']ufsc-form-field["\']>\s*</div>~i', '', $output );

    if ( false === strpos( $output, 'ufsc-licence-final-actions' ) || false !== strpos( $output, 'ufsc-licence-quota-guide' ) ) {
        return $output;
    }
    if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) || ! function_exists( 'ufsc_journey_pack_state' ) ) {
        return $output;
    }

    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    if ( $club_id < 1 ) {
        return $output;
    }
    $season = function_exists( 'ufsc_journey_current_season' ) ? ufsc_journey_current_season() : '';
    $state  = ufsc_journey_pack_state( $club_id, $season );
    $used = absint( $state['used'] ?? 0 );
    $limit = max( 1, absint( $state['limit'] ?? 10 ) );
    $included = ! empty( $state['included'] );

    if ( $included ) {
        $next = min( $limit, $used + 1 );
        $output = str_replace( 'Récapitulatif avant panier', 'Vérification avant envoi', $output );
        $message = sprintf(
            __( 'Quota actuel : %1$d/%2$d. « Enregistrer en brouillon » ne consomme aucune place. « Envoyer pour validation » fera passer cette licence dans le quota inclus (%3$d/%2$d), sans panier ni paiement. Après l’envoi, le dossier sera en attente de validation UFSC.', 'ufsc-clubs' ),
            $used,
            $limit,
            $next
        );
    } else {
        $output = str_replace( 'Récapitulatif avant panier', 'Vérification avant panier', $output );
        $message = sprintf(
            __( 'Quota actuel : %1$d/%2$d. Le brouillon ne déclenche aucun paiement. Le quota inclus étant complet, seule cette licence supplémentaire sera ajoutée au panier lors de la finalisation.', 'ufsc-clubs' ),
            $used,
            $limit
        );
    }

    $guide = '<div class="ufsc-licence-quota-guide" role="note"><strong>'
        . esc_html__( 'Que va-t-il se passer ?', 'ufsc-clubs' )
        . '</strong><span>' . esc_html( $message ) . '</span></div>';
    $marker = '<p class="ufsc-cart-confirmation">';
    if ( false !== strpos( $output, $marker ) ) {
        $output = str_replace( $marker, $guide . $marker, $output );
    }
    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_portal_hotfix_filter_licence_ui', 120, 4 );

/** Build a cache-safe logout form instead of relying on a cached wp-login logout URL. */
function ufsc_portal_hotfix_logout_form( $label = '', $redirect = '' ) {
    $label = trim( wp_strip_all_tags( (string) $label ) );
    if ( '' === $label ) {
        $label = __( 'Déconnexion', 'ufsc-clubs' );
    }
    $redirect = wp_validate_redirect( (string) $redirect, home_url( '/' ) );
    $html  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ufsc-secure-logout-form" style="display:inline">';
    $html .= '<input type="hidden" name="action" value="ufsc_portal_logout">';
    $html .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect ) . '">';
    $html .= wp_nonce_field( 'ufsc_portal_logout', '_wpnonce', true, false );
    $html .= '<button type="submit" class="ufsc-logout-button ufsc-logout-button--secure" style="border:0;background:none;padding:0;cursor:pointer">' . esc_html( $label ) . '</button>';
    $html .= '</form>';
    return $html;
}

/** Replace plugin-generated logout anchors in all UFSC auth shortcode variants. */
function ufsc_portal_hotfix_filter_logout_output( $output, $tag, $attr, $m ) {
    unset( $m );
    if ( ! in_array( $tag, array( 'ufsc_logout_button', 'ufsc_user_status', 'ufsc_login_form' ), true ) ) {
        return $output;
    }
    $redirect = home_url( '/' );
    if ( 'ufsc_logout_button' === $tag && is_array( $attr ) && ! empty( $attr['redirect'] ) ) {
        $redirect = (string) $attr['redirect'];
    }
    return (string) preg_replace_callback(
        '~<a\b(?=[^>]*class=["\'][^"\']*\bufsc-logout-button\b[^"\']*["\'])[^>]*>(.*?)</a>~isu',
        static function( $match ) use ( $redirect ) {
            return ufsc_portal_hotfix_logout_form( wp_strip_all_tags( $match[1] ?? '' ), $redirect );
        },
        $output
    );
}
add_filter( 'do_shortcode_tag', 'ufsc_portal_hotfix_filter_logout_output', 150, 4 );

/** Nonce-protected logout endpoint, safe even when the surrounding page is cached. */
function ufsc_portal_hotfix_handle_logout() {
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
    check_admin_referer( 'ufsc_portal_logout' );
    $redirect = isset( $_POST['redirect_to'] ) && ! is_array( $_POST['redirect_to'] )
        ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), home_url( '/' ) )
        : home_url( '/' );
    wp_logout();
    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_ufsc_portal_logout', 'ufsc_portal_hotfix_handle_logout' );
