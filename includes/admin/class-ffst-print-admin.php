<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Impression/export interne des documents FFST.
 *
 * Les anciens modèles Word .doc ne sont pas modifiés côté serveur : le plugin
 * produit une vue d'impression HTML préremplie, utilisable directement avec
 * « Imprimer / Enregistrer en PDF » du navigateur. Le modèle officiel original
 * reste disponible en téléchargement pour contrôle.
 */
final class UFSC_FFST_Print_Admin {
    const AFFILIATION_TEMPLATE_URL = 'https://ufsc-france.fr/wp-content/uploads/2026/09/01-AFFIL-REAFFIL-FFST-26-27.doc';
    const INSURANCE_TEMPLATE_URL   = 'https://ufsc-france.fr/wp-content/uploads/2026/09/04-ATTESTATIONS-ASSURANCES-A-SIGNER-26-27.doc';
    const LEADERS_TEMPLATE_URL     = 'https://ufsc-france.fr/wp-content/uploads/2026/09/02-BORDEREAU-LICENCES-DIRIGEANTS-26-27.xls';

    public static function init() {
        add_action( 'admin_post_ufsc_ffst_print_affiliation', array( __CLASS__, 'print_affiliation' ) );
        add_action( 'admin_post_ufsc_ffst_print_insurance', array( __CLASS__, 'print_insurance' ) );
        add_action( 'admin_footer', array( __CLASS__, 'render_actions' ) );
    }

    private static function can_manage() {
        return current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    private static function current_season() {
        return class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}` = %d LIMIT 1", absint( $club_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : 'club_id' );
        $season_col = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
        if ( $season_col ) {
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}` = %d AND `{$season_col}` = %s ORDER BY id ASC", absint( $club_id ), $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}` = %d ORDER BY id ASC", absint( $club_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function value( $row, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }

    private static function role_label( $licence ) {
        return self::value( $licence, array( 'fonction', 'role', 'poste', 'position' ) );
    }

    private static function is_leader( $licence ) {
        $role = strtolower( remove_accents( self::role_label( $licence ) ) );
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'coach' ) as $needle ) {
            if ( false !== strpos( $role, $needle ) ) { return true; }
        }
        return false;
    }

    public static function render_actions() {
        if ( ! self::can_manage() || ! is_admin() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-ffst-documents' !== $page || ! $club_id ) { return; }

        $affiliation_url = wp_nonce_url( admin_url( 'admin-post.php?action=ufsc_ffst_print_affiliation&club_id=' . $club_id ), 'ufsc_ffst_print_affiliation_' . $club_id );
        $insurance_url   = wp_nonce_url( admin_url( 'admin-post.php?action=ufsc_ffst_print_insurance&club_id=' . $club_id ), 'ufsc_ffst_print_insurance_' . $club_id );

        echo '<div class="wrap ufsc-ffst-print-actions"><div class="postbox" style="padding:18px">';
        echo '<h2 style="margin-top:0">' . esc_html__( '5. Impression forcée / documents officiels', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Vous pouvez imprimer immédiatement une version préremplie, même si le dossier n’est pas complet. Les champs absents restent signalés comme « À compléter » sur le document.', 'ufsc-clubs' ) . '</p>';
        echo '<div class="ufsc-ffst-action-grid">';
        echo '<div class="ufsc-ffst-action-card"><h3>' . esc_html__( 'Affiliation / réaffiliation', 'ufsc-clubs' ) . '</h3><p><a class="button button-primary" target="_blank" rel="noopener" href="' . esc_url( $affiliation_url ) . '">' . esc_html__( 'Imprimer / enregistrer en PDF', 'ufsc-clubs' ) . '</a></p><p><a href="' . esc_url( self::AFFILIATION_TEMPLATE_URL ) . '">' . esc_html__( 'Télécharger le modèle officiel .doc', 'ufsc-clubs' ) . '</a></p></div>';
        echo '<div class="ufsc-ffst-action-card"><h3>' . esc_html__( 'Attestations assurances', 'ufsc-clubs' ) . '</h3><p><a class="button" target="_blank" rel="noopener" href="' . esc_url( $insurance_url ) . '">' . esc_html__( 'Imprimer / enregistrer en PDF', 'ufsc-clubs' ) . '</a></p><p><a href="' . esc_url( self::INSURANCE_TEMPLATE_URL ) . '">' . esc_html__( 'Télécharger le modèle officiel .doc', 'ufsc-clubs' ) . '</a></p></div>';
        echo '<div class="ufsc-ffst-action-card"><h3>' . esc_html__( 'Bordereau licences dirigeants', 'ufsc-clubs' ) . '</h3><p>' . esc_html__( 'Utilisez le générateur Excel de l’étape précédente pour conserver les colonnes et la structure du fichier officiel.', 'ufsc-clubs' ) . '</p><p><a href="' . esc_url( self::LEADERS_TEMPLATE_URL ) . '">' . esc_html__( 'Télécharger le modèle officiel .xls', 'ufsc-clubs' ) . '</a></p></div>';
        echo '</div></div></div>';
    }

    public static function print_affiliation() {
        self::guard_request( 'ufsc_ffst_print_affiliation_' );
        $club_id = absint( $_GET['club_id'] );
        $club = self::get_club( $club_id );
        if ( ! $club ) { wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ) ); }
        $season = self::current_season();
        $licences = self::get_licences( $club_id, $season );
        $leaders = array_values( array_filter( $licences, array( __CLASS__, 'is_leader' ) ) );

        $rows = array(
            __( 'Nom du club', 'ufsc-clubs' ) => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            __( 'Adresse du siège', 'ufsc-clubs' ) => self::value( $club, array( 'adresse', 'adresse_siege', 'adresse_complete', 'address' ) ),
            __( 'Code postal', 'ufsc-clubs' ) => self::value( $club, array( 'code_postal', 'postal_code', 'cp' ) ),
            __( 'Ville', 'ufsc-clubs' ) => self::value( $club, array( 'ville', 'city' ) ),
            __( 'Téléphone', 'ufsc-clubs' ) => self::value( $club, array( 'telephone', 'tel', 'phone' ) ),
            __( 'E-mail', 'ufsc-clubs' ) => self::value( $club, array( 'email', 'mail', 'club_email' ) ),
            __( 'Site Internet', 'ufsc-clubs' ) => self::value( $club, array( 'site', 'site_web', 'website', 'url' ) ),
            __( 'N° RNA / déclaration', 'ufsc-clubs' ) => self::value( $club, array( 'rna', 'numero_recepisse', 'declaration_prefecture', 'numero_declaration' ) ),
            __( 'Salle d’entraînement', 'ufsc-clubs' ) => self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ),
        );

        self::document_header( __( 'Demande d’affiliation / réaffiliation FFST', 'ufsc-clubs' ), $season, $club_id );
        echo '<h2>' . esc_html__( 'Informations du club', 'ufsc-clubs' ) . '</h2><table><tbody>';
        foreach ( $rows as $label => $value ) {
            echo '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ?: __( 'À compléter', 'ufsc-clubs' ) ) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>' . esc_html__( 'Dirigeants / encadrement', 'ufsc-clubs' ) . '</h2><table><thead><tr><th>Fonction</th><th>Nom</th><th>Prénom</th><th>Date de naissance</th><th>E-mail</th></tr></thead><tbody>';
        foreach ( $leaders as $licence ) {
            echo '<tr><td>' . esc_html( self::role_label( $licence ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'nom', 'last_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'prenom', 'first_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'email', 'mail' ) ) ?: '—' ) . '</td></tr>';
        }
        if ( ! $leaders ) { echo '<tr><td colspan="5">' . esc_html__( 'Dirigeants à compléter dans UFSC Gestion.', 'ufsc-clubs' ) . '</td></tr>'; }
        echo '</tbody></table>';
        self::document_footer();
    }

    public static function print_insurance() {
        self::guard_request( 'ufsc_ffst_print_insurance_' );
        $club_id = absint( $_GET['club_id'] );
        $club = self::get_club( $club_id );
        if ( ! $club ) { wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ) ); }
        $season = self::current_season();
        $licences = self::get_licences( $club_id, $season );

        self::document_header( __( 'Attestations assurances FFST – liste de préparation', 'ufsc-clubs' ), $season, $club_id );
        echo '<p>' . esc_html__( 'Cette vue sert à préparer et contrôler les attestations à faire signer. Elle ne remplace pas la signature exigée sur le document officiel FFST.', 'ufsc-clubs' ) . '</p>';
        echo '<table><thead><tr><th>Nom</th><th>Prénom</th><th>Date de naissance</th><th>N° licence</th><th>Signature</th></tr></thead><tbody>';
        foreach ( $licences as $licence ) {
            echo '<tr><td>' . esc_html( self::value( $licence, array( 'nom', 'last_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'prenom', 'first_name' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ) ?: '—' ) . '</td><td>' . esc_html( self::value( $licence, array( 'numero_licence', 'licence_number', 'numero_asptt', 'asptt_number' ) ) ?: '—' ) . '</td><td style="height:38px"></td></tr>';
        }
        echo '</tbody></table>';
        self::document_footer();
    }

    private static function guard_request( $nonce_prefix ) {
        if ( ! self::can_manage() ) { wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ) ); }
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0;
        if ( ! $club_id ) { wp_die( esc_html__( 'Club invalide.', 'ufsc-clubs' ) ); }
        check_admin_referer( $nonce_prefix . $club_id );
    }

    private static function document_header( $title, $season, $club_id ) {
        nocache_headers();
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '"><title>' . esc_html( $title ) . '</title><style>@page{size:A4;margin:12mm}body{font-family:Arial,sans-serif;color:#1d2327;font-size:12px;line-height:1.35;max-width:190mm;margin:0 auto}h1{font-size:21px;margin:0 0 5mm}h2{font-size:15px;margin:6mm 0 2mm}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #777;padding:6px;vertical-align:top;overflow-wrap:anywhere}th{background:#f1f1f1;text-align:left}.meta{display:flex;justify-content:space-between;margin-bottom:6mm;padding-bottom:3mm;border-bottom:2px solid #222}.actions{margin:0 0 6mm}.actions button{padding:8px 14px;font-weight:700}@media print{.actions{display:none}body{max-width:none}}</style></head><body>';
        echo '<div class="actions"><button onclick="window.print()">' . esc_html__( 'Imprimer / Enregistrer en PDF', 'ufsc-clubs' ) . '</button></div>';
        echo '<div class="meta"><strong>UFSC — FFST</strong><span>' . esc_html( sprintf( __( 'Saison %1$s — Club #%2$d', 'ufsc-clubs' ), $season, $club_id ) ) . '</span></div>';
        echo '<h1>' . esc_html( $title ) . '</h1>';
    }

    private static function document_footer() {
        echo '<p style="margin-top:7mm;font-size:10px;color:#555">' . esc_html__( 'Document de préparation généré depuis UFSC Gestion. Vérifier les données avant transmission à la FFST.', 'ufsc-clubs' ) . '</p></body></html>';
        exit;
    }
}

UFSC_FFST_Print_Admin::init();
