<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Admin metaboxes for UFSC clubs
 */
class UFSC_CL_Club_Metaboxes {
    /**
     * Register hooks
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'register_metaboxes' ) );
        add_action( 'save_post_ufsc_club', array( __CLASS__, 'save_club_meta' ) );
    }

    /**
     * Register club meta boxes
     */
    public static function register_metaboxes() {
        add_meta_box(
            'ufsc_club_documents',
            __( 'Documents', 'ufsc-clubs' ),
            array( __CLASS__, 'render_documents_metabox' ),
            'ufsc_club',
            'side'
        );

        add_meta_box(
            'ufsc_club_licences',
            __( 'Licences', 'ufsc-clubs' ),
            array( __CLASS__, 'render_licences_metabox' ),
            'ufsc_club',
            'side'
        );

        add_meta_box(
            'ufsc_club_renewal',
            __( 'Saison / Renouvellement', 'ufsc-clubs' ),
            array( __CLASS__, 'render_renewal_metabox' ),
            'ufsc_club',
            'side'
        );
    }

    /**
     * Display club documents
     */
    public static function render_documents_metabox( $post ) {
        wp_nonce_field( 'ufsc_save_club_meta', 'ufsc_club_meta_nonce' );

        $club_id = $post->ID;
        $doc_types = apply_filters( 'ufsc_club_documents_types', array(
            'statuts' => __( 'Statuts', 'ufsc-clubs' ),
            'assurance' => __( 'Attestation d\'assurance', 'ufsc-clubs' ),
            'rib' => __( 'RIB', 'ufsc-clubs' ),
            'attestation_ufsc' => __( 'Attestation UFSC', 'ufsc-clubs' )
        ) );

        echo '<ul>';
        $has_docs = false;
        foreach ( $doc_types as $slug => $label ) {
            $attachment_id = (int) get_option( 'ufsc_club_doc_' . $slug . '_' . $club_id );
            if ( $attachment_id ) {
                $url = wp_get_attachment_url( $attachment_id );
                if ( $url ) {
                    $has_docs = true;
                    echo '<li><a href="' . esc_url( $url ) . '" download>' . esc_html( $label ) . '</a></li>';
                }
            }
        }
        if ( ! $has_docs ) {
            echo '<li>' . esc_html__( 'Aucun document.', 'ufsc-clubs' ) . '</li>';
        }
        echo '</ul>';
    }

    /**
     * Display licence count and link
     */
    public static function render_licences_metabox( $post ) {
        global $wpdb;

        $club_id = $post->ID;
        $settings = UFSC_SQL::get_settings();
        $lic_table = $settings['table_licences'];
        $club_col = ufsc_lic_col( 'club_id' );

        $count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$lic_table}` WHERE `{$club_col}` = %d", $club_id ) );
        $link  = admin_url( 'admin.php?page=ufsc-licences&club_id=' . $club_id );

        echo '<p>' . sprintf( esc_html__( '%d licences', 'ufsc-clubs' ), $count ) . '</p>';
        echo '<p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Voir les licences', 'ufsc-clubs' ) . '</a></p>';
    }



    /**
     * Render season and affiliation renewal controls.
     */
    public static function render_renewal_metabox( $post ) {
        $club_id         = absint( $post->ID );
        $current_season  = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';
        $next_season     = function_exists( 'ufsc_get_next_season' ) ? ufsc_get_next_season() : '';
        $renew_open      = function_exists( 'ufsc_is_renewal_window_open' ) ? ufsc_is_renewal_window_open() : false;
        $aff_season      = function_exists( 'ufsc_get_affiliation_season' ) ? ufsc_get_affiliation_season( $club_id ) : '';
        $renewed         = function_exists( 'ufsc_is_affiliation_renewed' ) ? ufsc_is_affiliation_renewed( $club_id, $next_season ) : false;
        $wc_settings     = function_exists( 'ufsc_get_woocommerce_settings' ) ? ufsc_get_woocommerce_settings() : array();
        $renew_start_ts   = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
        $renew_open_label = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );

        echo '<p><strong>' . esc_html__( 'Saison courante :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $current_season ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Affiliation :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $aff_season ? $aff_season : __( 'Non définie', 'ufsc-clubs' ) ) . '</p>';

        if ( $renew_open && ! $renewed && $aff_season !== $next_season && ! empty( $wc_settings['product_affiliation_id'] ) ) {
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
            wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' );
            echo '<input type="hidden" name="action" value="ufsc_add_to_cart">';
            echo '<input type="hidden" name="product_id" value="' . esc_attr( (int) $wc_settings['product_affiliation_id'] ) . '">';
            echo '<input type="hidden" name="ufsc_club_id" value="' . esc_attr( $club_id ) . '">';
            echo '<input type="hidden" name="ufsc_action" value="renew_affiliation">';
            echo '<input type="hidden" name="ufsc_target_season" value="' . esc_attr( $next_season ) . '">';
            echo '<button type="submit" class="button button-primary">' . esc_html__( 'Renouveler affiliation', 'ufsc-clubs' ) . '</button>';
            echo '</form>';
        } elseif ( ! $renew_open ) {
            echo '<p>' . esc_html( sprintf( __( 'Renouvellement %1$s ouvert à partir du %2$s', 'ufsc-clubs' ), $next_season, $renew_open_label ) ) . '</p>';
        }
    }

    /**
     * Save club data
     */
    public static function save_club_meta( $post_id ) {
        if ( ! isset( $_POST['ufsc_club_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ufsc_club_meta_nonce'], 'ufsc_save_club_meta' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $email  = isset( $_POST['ufsc_club_email'] ) ? sanitize_email( $_POST['ufsc_club_email'] ) : '';
        $region = isset( $_POST['ufsc_club_region'] ) ? sanitize_text_field( $_POST['ufsc_club_region'] ) : '';

        update_post_meta( $post_id, 'ufsc_club_email', $email );
        update_post_meta( $post_id, 'ufsc_club_region', $region );

        global $wpdb;
        $settings   = UFSC_SQL::get_settings();
        $table      = $settings['table_clubs'];
        $pk_col     = ufsc_club_col( 'id' );
        $name_col   = ufsc_club_col( 'nom' );
        $email_col  = ufsc_club_col( 'email' );
        $region_col = ufsc_club_col( 'region' );

        $wpdb->update(
            $table,
            array(
                $name_col   => get_the_title( $post_id ),
                $email_col  => $email,
                $region_col => $region,
            ),
            array( $pk_col => $post_id ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
}

add_action( 'init', array( 'UFSC_CL_Club_Metaboxes', 'init' ) );
