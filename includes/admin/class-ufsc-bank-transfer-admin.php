<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Rapprochement manuel des commandes UFSC réglées par virement bancaire.
 *
 * WooCommerce reste la source de vérité du paiement. La confirmation bancaire
 * est tracée sur la commande puis payment_complete() déclenche le parcours
 * WooCommerce/UFSC canonique déjà en place. Aucun statut licence/affiliation
 * n'est réécrit directement ici.
 */
final class UFSC_Bank_Transfer_Admin {
    const META_CONFIRMED = '_ufsc_bank_transfer_confirmed';
    const META_DATE      = '_ufsc_bank_transfer_confirmed_at';
    const META_USER      = '_ufsc_bank_transfer_confirmed_by';
    const META_REFERENCE = '_ufsc_bank_transfer_reference';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 28 );
        add_action( 'admin_post_ufsc_confirm_bank_transfer', array( __CLASS__, 'handle_confirm' ) );
    }

    public static function register_menu() {
        add_submenu_page(
            'ufsc-dashboard',
            __( 'Virements bancaires UFSC', 'ufsc-clubs' ),
            __( 'Virements', 'ufsc-clubs' ),
            self::menu_capability(),
            'ufsc-bank-transfers',
            array( __CLASS__, 'render' )
        );
    }

    private static function menu_capability() {
        return class_exists( 'UFSC_Permissions' ) ? UFSC_Permissions::CAP_GESTION_MANAGE : 'manage_woocommerce';
    }

    private static function can_reconcile() {
        $ufsc_cap = class_exists( 'UFSC_Permissions' ) && current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
        return $ufsc_cap || current_user_can( 'manage_woocommerce' );
    }

    public static function render() {
        if ( ! self::can_reconcile() ) {
            wp_die( esc_html__( 'Vous n’avez pas l’autorisation de rapprocher les virements.', 'ufsc-clubs' ) );
        }
        if ( ! function_exists( 'wc_get_orders' ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Virements bancaires UFSC', 'ufsc-clubs' ) . '</h1><div class="notice notice-error"><p>' . esc_html__( 'WooCommerce n’est pas disponible.', 'ufsc-clubs' ) . '</p></div></div>';
            return;
        }

        $view = isset( $_GET['transfer_view'] ) && ! is_array( $_GET['transfer_view'] ) ? sanitize_key( wp_unslash( $_GET['transfer_view'] ) ) : 'pending'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre lecture seule.
        if ( ! in_array( $view, array( 'pending', 'confirmed', 'all' ), true ) ) { $view = 'pending'; }
        $orders = self::get_orders( $view );

        echo '<div class="wrap">';
        if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); }
        echo '<h1>' . esc_html__( 'Virements bancaires UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Validez ici uniquement les virements réellement visibles sur le compte bancaire. La confirmation déclenche ensuite le traitement WooCommerce/UFSC normal et conserve une trace complète.', 'ufsc-clubs' ) . '</p>';
        self::render_notice();
        self::render_filters( $view );

        echo '<table class="widefat striped"><thead><tr>';
        foreach ( array( __( 'Commande', 'ufsc-clubs' ), __( 'Club / dossier', 'ufsc-clubs' ), __( 'Date', 'ufsc-clubs' ), __( 'Montant', 'ufsc-clubs' ), __( 'Statut WooCommerce', 'ufsc-clubs' ), __( 'Rapprochement bancaire', 'ufsc-clubs' ) ) as $heading ) {
            echo '<th>' . esc_html( $heading ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( ! $orders ) {
            echo '<tr><td colspan="6">' . esc_html__( 'Aucun virement UFSC ne correspond à ce filtre.', 'ufsc-clubs' ) . '</td></tr>';
        }
        foreach ( $orders as $order ) { self::render_order_row( $order ); }
        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__( 'Une confirmation bancaire n’est jamais supprimée silencieusement. En cas d’erreur comptable, corrigez la commande WooCommerce afin de conserver l’historique.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
    }

    private static function render_filters( $current ) {
        $base = admin_url( 'admin.php?page=ufsc-bank-transfers' );
        $items = array(
            'pending'   => __( 'À vérifier', 'ufsc-clubs' ),
            'confirmed' => __( 'Confirmés', 'ufsc-clubs' ),
            'all'       => __( 'Tous', 'ufsc-clubs' ),
        );
        echo '<p class="subsubsub">';
        $links = array();
        foreach ( $items as $key => $label ) {
            $url = add_query_arg( 'transfer_view', $key, $base );
            $links[] = '<a href="' . esc_url( $url ) . '"' . ( $current === $key ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
        }
        echo wp_kses_post( implode( ' | ', $links ) );
        echo '</p><div class="clear"></div>';
    }

    private static function render_notice() {
        $code = isset( $_GET['ufsc_transfer_notice'] ) && ! is_array( $_GET['ufsc_transfer_notice'] ) ? sanitize_key( wp_unslash( $_GET['ufsc_transfer_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- message de redirection.
        $messages = array(
            'confirmed'         => array( 'success', __( 'Virement confirmé. WooCommerce a reçu la confirmation de paiement.', 'ufsc-clubs' ) ),
            'already_confirmed' => array( 'info', __( 'Ce virement avait déjà été confirmé. Aucune seconde validation n’a été appliquée.', 'ufsc-clubs' ) ),
            'missing_check'     => array( 'warning', __( 'Cochez « Virement reçu sur le compte » avant de confirmer.', 'ufsc-clubs' ) ),
            'invalid_order'     => array( 'error', __( 'La commande est introuvable ou n’est pas une commande UFSC par virement.', 'ufsc-clubs' ) ),
            'blocked_status'    => array( 'error', __( 'Le statut actuel de la commande ne permet pas de confirmer ce virement.', 'ufsc-clubs' ) ),
        );
        if ( ! isset( $messages[ $code ] ) ) { return; }
        list( $type, $message ) = $messages[ $code ];
        echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
    }

    private static function get_orders( $view ) {
        $statuses = 'pending' === $view ? array( 'pending', 'on-hold' ) : array( 'pending', 'on-hold', 'processing', 'completed', 'failed', 'cancelled', 'refunded' );
        $raw = wc_get_orders( array(
            'limit'   => 200,
            'status'  => $statuses,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        ) );
        $orders = array();
        foreach ( (array) $raw as $order ) {
            if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_payment_method' ) ) || 'bacs' !== $order->get_payment_method() ) { continue; }
            if ( ! self::is_ufsc_order( $order ) ) { continue; }
            $confirmed = 'yes' === (string) $order->get_meta( self::META_CONFIRMED, true );
            if ( 'pending' === $view && $confirmed ) { continue; }
            if ( 'confirmed' === $view && ! $confirmed ) { continue; }
            $orders[] = $order;
        }
        return $orders;
    }

    private static function is_ufsc_order( $order ) {
        $affiliation_product = function_exists( 'ufsc_get_affiliation_product_id' ) ? absint( ufsc_get_affiliation_product_id() ) : 0;
        $licence_product = function_exists( 'ufsc_get_licence_product_id' ) ? absint( ufsc_get_licence_product_id() ) : 0;
        foreach ( $order->get_items() as $item ) {
            $product_id = is_callable( array( $item, 'get_product_id' ) ) ? absint( $item->get_product_id() ) : 0;
            if ( $product_id && in_array( $product_id, array_filter( array( $affiliation_product, $licence_product ) ), true ) ) { return true; }
            foreach ( array( '_ufsc_club_id', 'ufsc_club_id', '_ufsc_licence_ids', 'ufsc_licence_ids', 'ufsc_licence_id', 'ufsc_action', 'ufsc_item_type', '_ufsc_affiliation_request_type' ) as $meta_key ) {
                $value = $item->get_meta( $meta_key, true );
                if ( ! empty( $value ) ) { return true; }
            }
        }
        return false;
    }

    private static function render_order_row( $order ) {
        $confirmed = 'yes' === (string) $order->get_meta( self::META_CONFIRMED, true );
        $order_id = absint( $order->get_id() );
        $edit_url = is_callable( array( $order, 'get_edit_order_url' ) ) ? $order->get_edit_order_url() : admin_url( 'post.php?post=' . $order_id . '&action=edit' );
        $club = self::resolve_order_club( $order );
        $date = $order->get_date_created();
        $date_label = $date ? $date->date_i18n( 'd/m/Y H:i' ) : '—';
        $status_label = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status();

        echo '<tr>';
        echo '<td><strong><a href="' . esc_url( $edit_url ) . '">#' . esc_html( $order->get_order_number() ) . '</a></strong></td>';
        echo '<td>' . esc_html( $club['name'] ?: __( 'Dossier UFSC', 'ufsc-clubs' ) ) . ( $club['id'] ? '<br><span class="description">Club #' . esc_html( $club['id'] ) . '</span>' : '' ) . '</td>';
        echo '<td>' . esc_html( $date_label ) . '</td>';
        echo '<td><strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></td>';
        echo '<td>' . esc_html( $status_label ) . '</td>';
        echo '<td>';
        if ( $confirmed ) {
            $confirmed_at = (string) $order->get_meta( self::META_DATE, true );
            $confirmed_by = absint( $order->get_meta( self::META_USER, true ) );
            $reference = (string) $order->get_meta( self::META_REFERENCE, true );
            $user = $confirmed_by ? get_userdata( $confirmed_by ) : false;
            echo '<strong>✓ ' . esc_html__( 'Virement reçu et confirmé', 'ufsc-clubs' ) . '</strong>';
            if ( $confirmed_at ) { echo '<br><span class="description">' . esc_html( $confirmed_at ) . '</span>'; }
            if ( $user ) { echo '<br><span class="description">' . esc_html( sprintf( __( 'Validé par %s', 'ufsc-clubs' ), $user->display_name ) ) . '</span>'; }
            if ( $reference ) { echo '<br><span class="description">' . esc_html( sprintf( __( 'Référence : %s', 'ufsc-clubs' ), $reference ) ) . '</span>'; }
        } elseif ( in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            echo '<input type="hidden" name="action" value="ufsc_confirm_bank_transfer">';
            echo '<input type="hidden" name="order_id" value="' . esc_attr( $order_id ) . '">';
            wp_nonce_field( 'ufsc_confirm_bank_transfer_' . $order_id );
            echo '<p><label><input type="checkbox" name="transfer_received" value="1"> <strong>' . esc_html__( 'Virement reçu sur le compte', 'ufsc-clubs' ) . '</strong></label></p>';
            echo '<p><label>' . esc_html__( 'Référence bancaire (optionnel)', 'ufsc-clubs' ) . '<br><input type="text" name="bank_reference" value="" maxlength="191"></label></p>';
            echo '<button type="submit" class="button button-primary">' . esc_html__( 'Confirmer le virement', 'ufsc-clubs' ) . '</button>';
            echo '</form>';
        } else {
            echo '<span class="description">' . esc_html__( 'Confirmation manuelle indisponible pour ce statut.', 'ufsc-clubs' ) . '</span>';
        }
        echo '</td></tr>';
    }

    public static function handle_confirm() {
        if ( ! self::can_reconcile() ) { wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), 403 ); }
        $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
        if ( 'POST' !== $request_method ) { wp_die( esc_html__( 'Méthode de requête invalide.', 'ufsc-clubs' ), 405 ); }
        $order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
        check_admin_referer( 'ufsc_confirm_bank_transfer_' . $order_id );
        if ( ! $order_id || ! function_exists( 'wc_get_order' ) ) { self::redirect( 'invalid_order' ); }

        $order = wc_get_order( $order_id );
        if ( ! $order || 'bacs' !== $order->get_payment_method() || ! self::is_ufsc_order( $order ) ) { self::redirect( 'invalid_order' ); }
        if ( 'yes' === (string) $order->get_meta( self::META_CONFIRMED, true ) ) { self::redirect( 'already_confirmed' ); }
        if ( empty( $_POST['transfer_received'] ) ) { self::redirect( 'missing_check' ); }
        if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) { self::redirect( 'blocked_status' ); }

        $reference = isset( $_POST['bank_reference'] ) && ! is_array( $_POST['bank_reference'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_reference'] ) ) : '';
        $user_id = get_current_user_id();
        $confirmed_at = current_time( 'mysql' );
        $order->update_meta_data( self::META_CONFIRMED, 'yes' );
        $order->update_meta_data( self::META_DATE, $confirmed_at );
        $order->update_meta_data( self::META_USER, $user_id );
        $order->update_meta_data( self::META_REFERENCE, $reference );
        $note = sprintf(
            __( 'Virement bancaire confirmé par l’administration UFSC le %1$s par l’utilisateur #%2$d%3$s.', 'ufsc-clubs' ),
            $confirmed_at,
            $user_id,
            $reference ? ' — Référence : ' . $reference : ''
        );
        $order->add_order_note( $note, false, true );
        $order->save();

        // WooCommerce remains authoritative: existing payment hooks update the
        // affiliation/licence state and payment trace exactly once.
        if ( is_callable( array( $order, 'needs_payment' ) ) && $order->needs_payment() ) {
            $order->payment_complete();
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'bank_transfer_confirmed', array(
                'order_id' => $order_id,
                'user_id' => $user_id,
                'reference' => $reference,
                'payment_method' => 'bacs',
                'confirmed_at' => $confirmed_at,
            ) );
        }
        do_action( 'ufsc_bank_transfer_confirmed', $order_id, $user_id, $reference );
        self::redirect( 'confirmed' );
    }

    private static function resolve_order_club( $order ) {
        $club_id = 0;
        foreach ( $order->get_items() as $item ) {
            foreach ( array( '_ufsc_club_id', 'ufsc_club_id' ) as $key ) {
                $club_id = absint( $item->get_meta( $key, true ) );
                if ( $club_id ) { break 2; }
            }
        }
        if ( ! $club_id ) { return array( 'id' => 0, 'name' => '' ); }
        global $wpdb;
        $settings = class_exists( 'UFSC_SQL' ) ? UFSC_SQL::get_settings() : array();
        $table = $settings['table_clubs'] ?? '';
        if ( ! $table ) { return array( 'id' => $club_id, 'name' => '' ); }
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $pk = in_array( 'id', $columns, true ) ? 'id' : ( in_array( 'club_id', $columns, true ) ? 'club_id' : '' );
        $name_col = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'name', $columns, true ) ? 'name' : '' );
        if ( ! $pk || ! $name_col ) { return array( 'id' => $club_id, 'name' => '' ); }
        $name = $wpdb->get_var( $wpdb->prepare( "SELECT `{$name_col}` FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return array( 'id' => $club_id, 'name' => (string) $name );
    }

    private static function redirect( $code ) {
        $url = add_query_arg( array( 'page' => 'ufsc-bank-transfers', 'transfer_view' => 'pending', 'ufsc_transfer_notice' => sanitize_key( $code ) ), admin_url( 'admin.php' ) );
        wp_safe_redirect( $url );
        exit;
    }
}

UFSC_Bank_Transfer_Admin::init();
