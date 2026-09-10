<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Préparation interne des attestations assurances FFST.
 *
 * Lecture seule sur clubs/licences. Le numéro affiché est exclusivement le
 * numéro de licence FFST ; aucune ancienne référence UFSC/ASPTT n'est requalifiée.
 */
final class UFSC_FFST_Insurance_Admin {
    const INSURANCE_TEMPLATE_URL = 'https://ufsc-france.fr/wp-content/uploads/2026/09/04-ATTESTATIONS-ASSURANCES-A-SIGNER-26-27.doc';

    public static function init() {
        add_action( 'admin_footer', array( __CLASS__, 'render_panel' ) );
        add_action( 'admin_post_ufsc_ffst_print_insurance', array( __CLASS__, 'handle_print' ) );
    }

    private static function can_manage() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function current_season() {
        return class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    }

    private static function normalize_season( $season ) {
        $season = sanitize_text_field( (string) $season );
        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
    }

    private static function club_exists( $club_id ) {
        global $wpdb;
        $club_id = absint( $club_id );
        if ( ! $club_id ) { return false; }
        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_clubs_table()
            : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) )
            : 'id';
        return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_licences_table()
            : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        if ( ! $club_col ) { return array(); }

        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
            if ( ! in_array( $candidate, $columns, true ) ) { continue; }
            if ( 'season_end_year' === $candidate ) {
                $end_year = preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ? (int) $matches[1] : 0;
                return $end_year
                    ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `season_end_year`=%d", $club_id, $end_year ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    : array();
            }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND REPLACE(TRIM(`{$candidate}`), '/', '-')=%s", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // Sécurité : sans colonne saison identifiable, ne jamais mélanger les historiques.
        return array();
    }

    private static function value( $row, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }

    private static function ffst_identifier( $licence ) {
        if ( class_exists( 'UFSC_Identifier_Resolver' ) && method_exists( 'UFSC_Identifier_Resolver', 'read' ) ) {
            $value = UFSC_Identifier_Resolver::read( $licence, 'licence_ffst' );
            if ( $value ) { return (string) $value; }
        }
        return self::value( $licence, array( 'numero_licence_ffst' ) );
    }

    public static function render_panel() {
        if ( ! self::can_manage() || ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage lecture seule.
        if ( 'ufsc-ffst-documents' !== $page || ! $club_id || ! self::club_exists( $club_id ) ) { return; }

        $season = self::normalize_season( self::current_season() );
        if ( ! $season ) { return; }

        echo '<div class="ufsc-ffst-insurance" style="max-width:1180px;margin:18px 20px 0 0">';
        echo '<div class="postbox" style="padding:20px;border-radius:10px">';
        echo '<h2 style="margin-top:0">' . esc_html__( '5. Attestations assurances FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Générez une liste de préparation pour la saison sélectionnée. Un numéro FFST manquant reste « À compléter » : aucun ancien numéro n’est substitué.', 'ufsc-clubs' ) . '</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank">';
        echo '<input type="hidden" name="action" value="ufsc_ffst_print_insurance">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        wp_nonce_field( 'ufsc_ffst_print_insurance_' . $club_id . '_' . $season );
        echo '<button type="submit" class="button button-primary">' . esc_html__( 'Imprimer / enregistrer en PDF', 'ufsc-clubs' ) . '</button> ';
        echo '<a href="' . esc_url( self::INSURANCE_TEMPLATE_URL ) . '" target="_blank" rel="noopener" class="button">' . esc_html__( 'Télécharger le modèle officiel .doc', 'ufsc-clubs' ) . '</a>';
        echo '</form>';
        echo '</div></div>';
    }

    public static function handle_print() {
        if ( ! self::can_manage() || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), 403 );
        }
        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? self::normalize_season( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! $season || ! self::club_exists( $club_id ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), 400 );
        }
        check_admin_referer( 'ufsc_ffst_print_insurance_' . $club_id . '_' . $season );

        $licences = self::get_licences( $club_id, $season );
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><title>' . esc_html__( 'Attestations assurances FFST', 'ufsc-clubs' ) . '</title>';
        echo '<style>@page{size:A4;margin:12mm}body{font-family:Arial,sans-serif;color:#1d2327;font-size:12px;line-height:1.35;max-width:190mm;margin:0 auto}h1{font-size:21px;margin:0 0 4mm}.meta{margin-bottom:5mm;color:#50575e}.warning{padding:3mm;border:1px solid #dba617;background:#fff8e5;margin:4mm 0}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #8c8f94;padding:2.2mm;vertical-align:top;text-align:left}.missing{font-weight:700;color:#8a2424}.actions{margin:6mm 0}@media print{.actions{display:none}}</style></head><body>';
        echo '<div class="actions"><button type="button" onclick="window.print()">' . esc_html__( 'Imprimer / enregistrer en PDF', 'ufsc-clubs' ) . '</button></div>';
        echo '<h1>' . esc_html__( 'Attestations assurances FFST — liste de préparation', 'ufsc-clubs' ) . '</h1>';
        echo '<div class="meta">' . esc_html( sprintf( __( 'Saison %1$s — Club #%2$d — %3$d licence(s)', 'ufsc-clubs' ), $season, $club_id, count( $licences ) ) ) . '</div>';
        echo '<div class="warning">' . esc_html__( 'Document de préparation interne. Il ne remplace pas le modèle officiel à signer. Vérifier tous les champs avant transmission.', 'ufsc-clubs' ) . '</div>';
        echo '<table><thead><tr><th>' . esc_html__( 'Nom', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Prénom', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Date de naissance', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'N° licence FFST', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Signature', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( $licences as $licence ) {
            $ffst = self::ffst_identifier( $licence );
            echo '<tr><td>' . esc_html( self::value( $licence, array( 'nom', 'nom_licence', 'last_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'prenom', 'first_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ) ?: '—' ) . '</td><td' . ( $ffst ? '' : ' class="missing"' ) . '>' . esc_html( $ffst ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td><td style="height:38px"></td></tr>';
        }
        if ( ! $licences ) {
            echo '<tr><td colspan="5" class="missing">' . esc_html__( 'Aucune licence trouvée pour cette saison. Aucun historique d’une autre saison n’a été repris.', 'ufsc-clubs' ) . '</td></tr>';
        }
        echo '</tbody></table><p><small>' . esc_html__( 'UFSC Gestion — lecture seule des licences de la saison.', 'ufsc-clubs' ) . '</small></p></body></html>';
        exit;
    }
}

UFSC_FFST_Insurance_Admin::init();