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
