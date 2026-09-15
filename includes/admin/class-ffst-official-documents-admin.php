<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pack de transmission FFST depuis l'administration UFSC.
 *
 * Cette couche est volontairement en lecture seule : elle ne modifie ni les
 * clubs, ni les licences, ni les saisons, ni les commandes WooCommerce.
 */
final class UFSC_FFST_Official_Documents_Admin {

    const AFFILIATION_ACTION = 'ufsc_ffst_generate_official_affiliation_doc';
    const LICENCES_ACTION    = 'ufsc_ffst_generate_licences';

    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'render_transmission_panel' ) );
        add_action( 'admin_post_' . self::AFFILIATION_ACTION, array( __CLASS__, 'handle_affiliation_word' ) );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' )
            && current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    public static function render_transmission_panel() {
        if ( ! self::can_manage() ) { return; }
        if ( ! isset( $_GET['page'] ) || 'ufsc-ffst-documents' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
            return;
        }

        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
        if ( ! $club_id ) { return; }

        $season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
            ? sanitize_text_field( wp_unslash( $_GET['season'] ) )
            : self::current_season();
        if ( ! $season ) { $season = self::current_season(); }

        echo '<div class="notice notice-info" style="border-left-color:#2271b1;padding:14px 16px;margin-top:16px;">';
        echo '<h2 style="margin:0 0 8px;">' . esc_html__( 'Pack transmission FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<p style="margin:0 0 12px;">' . esc_html__( 'Générez les deux documents à transmettre à la FFST à partir des données déjà enregistrées dans UFSC Gestion. Aucune fiche club, licence, commande ou saison n’est modifiée.', 'ufsc-clubs' ) . '</p>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';

        self::render_post_button(
            self::AFFILIATION_ACTION,
            $club_id,
            $season,
            __( 'Télécharger affiliation FFST (.doc)', 'ufsc-clubs' ),
            true
        );

        if ( class_exists( 'UFSC_FFST_Export_Admin' ) ) {
            self::render_post_button(
                self::LICENCES_ACTION,
                $club_id,
                $season,
                __( 'Télécharger licences dirigeants (.xlsx)', 'ufsc-clubs' ),
                false
            );
        }

        echo '<span class="description">' . esc_html__( 'Le formulaire Word est prérempli avec les informations disponibles ; les champs absents sont signalés « À compléter » pour contrôle avant envoi.', 'ufsc-clubs' ) . '</span>';
        echo '</div></div>';
    }

    private static function render_post_button( $action, $club_id, $season, $label, $new_tab ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;"' . ( $new_tab ? ' target="_blank"' : '' ) . '>';
        echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        wp_nonce_field( $action . '_' . $club_id . '_' . $season );
        echo '<button type="submit" class="button button-primary">' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    public static function handle_affiliation_word() {
        if ( ! self::can_manage() ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season  = isset( $_POST['season'] ) && ! is_array( $_POST['season'] )
            ? sanitize_text_field( wp_unslash( $_POST['season'] ) )
            : '';
        if ( ! $club_id || ! $season ) {
            wp_die( esc_html__( 'Club ou saison invalide.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }

        check_admin_referer( self::AFFILIATION_ACTION . '_' . $club_id . '_' . $season );

        $club = self::get_club( $club_id );
        if ( ! $club ) {
            wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ), '', array( 'response' => 404 ) );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log(
                'ffst_official_affiliation_word_generated',
                array(
                    'club_id' => $club_id,
                    'season'  => $season,
                )
            );
        }

        $club_name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        $filename  = 'ffst-affiliation-' . sanitize_title( $club_name ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '.doc';

        nocache_headers();
        header( 'Content-Type: application/msword; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'X-Content-Type-Options: nosniff' );

        echo "\xEF\xBB\xBF"; // BOM UTF-8 pour Word.
        echo '<!doctype html><html><head><meta charset="UTF-8"><title>Affiliation FFST</title>';
        echo '<style>
            @page{size:A4;margin:12mm}
            body{font-family:Arial,sans-serif;color:#111;font-size:10.5pt;line-height:1.25}
            h1{font-size:17pt;text-align:center;margin:0 0 4mm}
            h2{font-size:12pt;background:#e9eef4;padding:2mm;margin:5mm 0 2mm}
            .meta{text-align:center;color:#444;margin-bottom:4mm}
            .warning{border:1px solid #b98b00;background:#fff8db;padding:2.5mm;margin:3mm 0}
            table{width:100%;border-collapse:collapse;margin:0 0 3mm;table-layout:fixed}
            th,td{border:1px solid #777;padding:2mm;vertical-align:top;text-align:left}
            th{width:35%;background:#f3f4f5}
            .missing{font-weight:bold;color:#9c1c1c}
            .signatures td{height:23mm}
            .footer{font-size:8.5pt;color:#555;margin-top:5mm}
        </style></head><body>';

        echo '<h1>' . esc_html__( 'DEMANDE D’AFFILIATION / RÉAFFILIATION FFST', 'ufsc-clubs' ) . '</h1>';
        echo '<div class="meta">' . esc_html( sprintf( __( 'Saison %1$s — dossier UFSC club #%2$d', 'ufsc-clubs' ), $season, $club_id ) ) . '</div>';
        echo '<div class="warning"><strong>' . esc_html__( 'Contrôle avant envoi :', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'document prérempli depuis UFSC Gestion. Vérifier les informations et compléter les mentions manquantes avant transmission à la FFST.', 'ufsc-clubs' ) . '</div>';

        self::render_section(
            __( 'Association / club', 'ufsc-clubs' ),
            array(
                __( 'Nom du club', 'ufsc-clubs' )             => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
                __( 'N° affiliation FFST', 'ufsc-clubs' )     => self::value( $club, array( 'numero_affiliation_ffst', 'num_affiliation' ) ),
                __( 'N° déclaration / RNA', 'ufsc-clubs' )    => self::first_non_empty( array( self::value( $club, array( 'rna_number' ) ), self::value( $club, array( 'num_declaration' ) ) ) ),
                __( 'Date de déclaration', 'ufsc-clubs' )     => self::value( $club, array( 'date_declaration' ) ),
                __( 'SIREN', 'ufsc-clubs' )                   => self::value( $club, array( 'siren' ) ),
                __( 'Adresse', 'ufsc-clubs' )                 => self::join_non_empty( array( self::value( $club, array( 'adresse' ) ), self::value( $club, array( 'complement_adresse' ) ) ), ', ' ),
                __( 'Code postal / Ville', 'ufsc-clubs' )     => self::join_non_empty( array( self::value( $club, array( 'code_postal' ) ), self::value( $club, array( 'ville' ) ) ), ' ' ),
                __( 'Téléphone', 'ufsc-clubs' )               => self::value( $club, array( 'telephone' ) ),
                __( 'E-mail', 'ufsc-clubs' )                  => self::value( $club, array( 'email' ) ),
                __( 'Site internet', 'ufsc-clubs' )           => self::value( $club, array( 'url_site' ) ),
            )
        );

        self::render_officer_section( $club );

        self::render_section(
            __( 'Documents et contrôle UFSC', 'ufsc-clubs' ),
            array(
                __( 'Statuts', 'ufsc-clubs' )                 => self::document_state( $club, array( 'doc_statuts', 'statuts' ) ),
                __( 'Récépissé / déclaration', 'ufsc-clubs' ) => self::document_state( $club, array( 'doc_recepisse', 'recepisse' ) ),
                __( 'Journal officiel', 'ufsc-clubs' )        => self::document_state( $club, array( 'doc_jo', 'jo' ) ),
                __( 'PV d’assemblée générale', 'ufsc-clubs' ) => self::document_state( $club, array( 'doc_pv_ag', 'pv_ag' ) ),
                __( 'Contrat d’engagement républicain', 'ufsc-clubs' ) => self::document_state( $club, array( 'doc_cer', 'cer', 'doc_attestation_cer', 'attestation_cer' ) ),
            )
        );

        echo '<h2>' . esc_html__( 'Validation et signatures', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="signatures"><tr><th>' . esc_html__( 'Président du club', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Visa / contrôle UFSC', 'ufsc-clubs' ) . '</th></tr><tr><td>Date :<br><br>Signature :</td><td>Date :<br><br>Visa :</td></tr></table>';
        echo '<p class="footer">' . esc_html__( 'Généré automatiquement depuis UFSC Gestion. Cet export est en lecture seule et ne modifie aucune donnée du club ni aucune licence.', 'ufsc-clubs' ) . '</p>';
        echo '</body></html>';
        exit;
    }

    private static function render_officer_section( $club ) {
        echo '<h2>' . esc_html__( 'Dirigeants', 'ufsc-clubs' ) . '</h2>';
        echo '<table><thead><tr><th>' . esc_html__( 'Fonction', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Nom et prénom', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Téléphone', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'E-mail', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';

        foreach ( array(
            'president'  => __( 'Président', 'ufsc-clubs' ),
            'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ),
            'tresorier'  => __( 'Trésorier', 'ufsc-clubs' ),
        ) as $prefix => $label ) {
            $name = self::join_non_empty(
                array(
                    self::value( $club, array( $prefix . '_nom' ) ),
                    self::value( $club, array( $prefix . '_prenom' ) ),
                ),
                ' '
            );
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td>';
            echo '<td>' . self::formatted_value( $name ) . '</td>';
            echo '<td>' . self::formatted_value( self::value( $club, array( $prefix . '_tel' ) ) ) . '</td>';
            echo '<td>' . self::formatted_value( self::value( $club, array( $prefix . '_email' ) ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_section( $title, array $rows ) {
        echo '<h2>' . esc_html( $title ) . '</h2><table><tbody>';
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . self::formatted_value( $value ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function formatted_value( $value ) {
        $value = trim( (string) $value );
        if ( '' === $value ) {
            return '<span class="missing">' . esc_html__( 'À compléter', 'ufsc-clubs' ) . '</span>';
        }
        return esc_html( $value );
    }

    private static function document_state( $club, array $keys ) {
        $value = self::value( $club, $keys );
        return '' !== trim( (string) $value ) ? __( 'Présent dans UFSC Gestion', 'ufsc-clubs' ) : '';
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_clubs_table()
            : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) )
            : 'id';
        if ( ! $table || ! $pk ) { return null; }

        return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1", absint( $club_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::get_current_season();
        }
        return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    }

    private static function value( $row, array $keys ) {
        foreach ( $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) {
                return (string) $row->{$key};
            }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) {
                return (string) $row[ $key ];
            }
        }
        return '';
    }

    private static function first_non_empty( array $values ) {
        foreach ( $values as $value ) {
            if ( '' !== trim( (string) $value ) ) { return (string) $value; }
        }
        return '';
    }

    private static function join_non_empty( array $values, $separator ) {
        $values = array_values( array_filter( array_map( 'trim', array_map( 'strval', $values ) ), static function( $value ) {
            return '' !== $value;
        } ) );
        return implode( $separator, $values );
    }
}
