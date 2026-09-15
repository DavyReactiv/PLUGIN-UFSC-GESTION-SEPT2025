<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Documents officiels FFST.
 *
 * Le module travaille exclusivement sur des copies des modèles officiels.
 * Il ne modifie aucune fiche club/licence, aucune saison ni aucune commande.
 */
final class UFSC_FFST_Official_Template_Admin {
    const AFFILIATION_ACTION = 'ufsc_ffst_generate_official_template_affiliation';
    const LICENCES_ACTION    = 'ufsc_ffst_generate_licences';
    const AFFILIATION_DOCX   = '01-AFFIL-REAFFIL-FFST-26-27.docx';

    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'render_panel' ) );
        add_action( 'admin_post_' . self::AFFILIATION_ACTION, array( __CLASS__, 'handle_affiliation' ) );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    public static function render_panel() {
        if ( ! self::can_manage() ) { return; }
        if ( ! isset( $_GET['page'] ) || 'ufsc-ffst-documents' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
            return;
        }

        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
        if ( ! $club_id ) { return; }
        $season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
            ? sanitize_text_field( wp_unslash( $_GET['season'] ) )
            : self::current_season();

        echo '<style>
            .ufsc-ffst-official-pack{max-width:1280px;box-sizing:border-box;margin:16px 20px 12px 0!important;padding:18px 20px!important;border-left:4px solid #2271b1!important;border-radius:8px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.04)}
            .ufsc-ffst-official-pack h2{margin:0 0 7px;font-size:18px}
            .ufsc-ffst-official-pack p{max-width:980px;margin:0 0 14px;color:#50575e}
            .ufsc-ffst-official-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
            .ufsc-ffst-official-actions form{margin:0}
            .ufsc-ffst-official-actions .description{flex:1 1 420px;max-width:760px}
            @media(max-width:782px){.ufsc-ffst-official-pack{margin-right:10px!important}.ufsc-ffst-official-actions{align-items:stretch}.ufsc-ffst-official-actions form,.ufsc-ffst-official-actions .button{width:100%}}
        </style>';
        echo '<div class="notice notice-info ufsc-ffst-official-pack">';
        echo '<h2>' . esc_html__( 'Pack transmission FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Les téléchargements utilisent les documents types FFST 2026-2027 et les préremplissent à partir des données déjà présentes dans UFSC Gestion. Aucun document maison n’est généré.', 'ufsc-clubs' ) . '</p>';
        echo '<div class="ufsc-ffst-official-actions">';
        self::render_button( self::AFFILIATION_ACTION, $club_id, $season, __( 'Télécharger affiliation FFST préremplie (.docx)', 'ufsc-clubs' ), true );
        if ( class_exists( 'UFSC_FFST_Export_Admin' ) ) {
            self::render_button( self::LICENCES_ACTION, $club_id, $season, __( 'Télécharger licences dirigeants (.xlsx)', 'ufsc-clubs' ), false );
        }
        echo '<span class="description">' . esc_html__( 'Le modèle FFST original reste intact. Une copie est créée pour chaque génération.', 'ufsc-clubs' ) . '</span>';
        echo '</div></div>';
    }

    private static function render_button( $action, $club_id, $season, $label, $new_tab ) {
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $new_tab ? ' target="_blank"' : '' ) . '>';
        echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
        echo '<input type="hidden" name="club_id" value="' . esc_attr( $club_id ) . '">';
        echo '<input type="hidden" name="season" value="' . esc_attr( $season ) . '">';
        wp_nonce_field( $action . '_' . $club_id . '_' . $season );
        echo '<button type="submit" class="button button-primary">' . esc_html( $label ) . '</button>';
        echo '</form>';
    }

    public static function handle_affiliation() {
        list( $club_id, $season ) = self::guard_request( self::AFFILIATION_ACTION );
        if ( ! class_exists( 'ZipArchive' ) || ! class_exists( 'DOMDocument' ) ) {
            wp_die( esc_html__( 'Le serveur ne dispose pas des extensions nécessaires pour préremplir le modèle Word FFST.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        $club = self::get_club( $club_id );
        if ( ! $club ) {
            wp_die( esc_html__( 'Club introuvable.', 'ufsc-clubs' ), '', array( 'response' => 404 ) );
        }

        $template = self::resolve_affiliation_template();
        if ( ! $template ) {
            wp_die(
                wp_kses_post( sprintf(
                    __( 'Le modèle Word officiel FFST converti en DOCX est absent. Déposez <strong>%s</strong> dans le même dossier WordPress que le document officiel .doc, puis relancez la génération. Aucun document simplifié ne sera généré à sa place.', 'ufsc-clubs' ),
                    esc_html( self::AFFILIATION_DOCX )
                ) ),
                esc_html__( 'Modèle FFST manquant', 'ufsc-clubs' ),
                array( 'response' => 500 )
            );
        }

        $licences = self::get_licences( $club_id, $season );
        $people   = self::build_people( $club, $licences );
        $target   = wp_tempnam( 'ufsc-ffst-affiliation-' . $club_id . '.docx' );
        if ( ! $target || ! copy( $template, $target ) ) {
            wp_die( esc_html__( 'Impossible de créer la copie de travail du modèle FFST.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        try {
            self::fill_affiliation_docx( $target, $club, $people );
        } catch ( Throwable $e ) {
            @unlink( $target );
            if ( function_exists( 'ufsc_log_error' ) ) { ufsc_log_error( 'FFST official DOCX: ' . $e->getMessage() ); }
            wp_die( esc_html__( 'Le modèle FFST n’a pas pu être prérempli. Le document original n’a pas été modifié.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        if ( function_exists( 'ufsc_audit_log' ) ) {
            ufsc_audit_log( 'ffst_official_affiliation_docx_generated', array( 'club_id' => $club_id, 'season' => $season ) );
        }

        $club_name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        $filename = 'ffst-affiliation-' . sanitize_title( $club_name ?: 'club-' . $club_id ) . '-' . sanitize_title( $season ) . '.docx';
        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
        header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
        header( 'Content-Length: ' . filesize( $target ) );
        readfile( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        @unlink( $target );
        exit;
    }

    private static function resolve_affiliation_template() {
        $uploads = wp_upload_dir();
        $candidate = trailingslashit( $uploads['basedir'] ) . '2026/09/' . self::AFFILIATION_DOCX;
        if ( is_readable( $candidate ) ) { return $candidate; }

        $bundled = defined( 'UFSC_CL_DIR' ) ? UFSC_CL_DIR . 'assets/ffst/' . self::AFFILIATION_DOCX : '';
        if ( $bundled && is_readable( $bundled ) ) { return $bundled; }
        return '';
    }

    private static function fill_affiliation_docx( $path, $club, array $people ) {
        $zip = new ZipArchive();
        if ( true !== $zip->open( $path ) ) { throw new RuntimeException( 'DOCX open failed.' ); }
        $xml = $zip->getFromName( 'word/document.xml' );
        if ( false === $xml ) { $zip->close(); throw new RuntimeException( 'document.xml missing.' ); }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if ( ! $dom->loadXML( $xml ) ) { $zip->close(); throw new RuntimeException( 'Invalid document.xml.' ); }
        $xp = new DOMXPath( $dom );
        $xp->registerNamespace( 'w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main' );
        $tables = $xp->query( '//w:tbl' );
        if ( ! $tables || $tables->length < 10 ) { $zip->close(); throw new RuntimeException( 'Unexpected official FFST template structure.' ); }

        $affiliation = self::value( $club, array( 'numero_affiliation_ffst' ) );
        $discipline  = self::value( $club, array( 'discipline', 'disciplines', 'discipline_principale', 'activite_principale' ) );
        self::replace_text_fragment( $xp, 'REAFFILIATION N°', 'REAFFILIATION N° ' . self::fallback( $affiliation ) );
        self::replace_text_fragment( $xp, 'DISCIPLINE(S) PRATIQUEE(S)', 'DISCIPLINE(S) PRATIQUEE(S) : ' . self::fallback( $discipline ) );

        $club_table = $tables->item( 3 );
        self::set_table_cell( $xp, $club_table, 0, 1, self::fallback( self::value( $club, array( 'nom', 'name', 'club_name' ) ) ) );
        self::set_table_cell( $xp, $club_table, 1, 1, self::fallback( self::full_club_address( $club ) ) );
        self::set_table_cell( $xp, $club_table, 2, 1, self::fallback( self::value( $club, array( 'telephone', 'tel', 'phone' ) ) ) );
        self::set_table_cell( $xp, $club_table, 3, 1, self::fallback( self::value( $club, array( 'email', 'mail', 'club_email' ) ) ) );
        self::set_table_cell( $xp, $club_table, 4, 1, self::fallback( self::value( $club, array( 'url_site', 'site_web', 'website', 'site' ) ) ) );
        $decl_date = self::value( $club, array( 'date_declaration' ) );
        $decl_no   = self::value( $club, array( 'rna_number', 'rna', 'num_declaration', 'numero_recepisse', 'declaration_prefecture' ) );
        self::set_table_cell( $xp, $club_table, 5, 1, 'Date : ' . self::fallback( $decl_date ) . '     N° : ' . self::fallback( $decl_no ) );
        $agr_date = self::value( $club, array( 'date_agrement_js', 'date_agrement', 'agrement_date' ) );
        $agr_no   = self::value( $club, array( 'numero_agrement_js', 'numero_agrement', 'agrement_numero' ) );
        self::set_table_cell( $xp, $club_table, 6, 1, 'Date : ' . self::fallback( $agr_date ) . '     N° : ' . self::fallback( $agr_no ) );

        $hall_table = $tables->item( 4 );
        self::set_table_cell( $xp, $hall_table, 0, 1, self::fallback( self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ) ) );

        for ( $i = 0; $i < 5; $i++ ) {
            self::fill_person_table( $xp, $tables->item( 5 + $i ), isset( $people[ $i ] ) ? $people[ $i ] : array() );
        }

        $new_xml = $dom->saveXML();
        if ( false === $zip->addFromString( 'word/document.xml', $new_xml ) ) { $zip->close(); throw new RuntimeException( 'DOCX write failed.' ); }
        $zip->close();
    }

    private static function fill_person_table( DOMXPath $xp, DOMNode $table, array $person ) {
        $name = self::join_non_empty( array(
            self::value( $person, array( 'nom', 'nom_licence', 'last_name' ) ),
            self::value( $person, array( 'prenom', 'first_name' ) ),
        ), ' ' );
        self::set_table_cell( $xp, $table, 0, 1, self::fallback( $name ) );
        self::set_table_cell( $xp, $table, 1, 1, 'Le : ' . self::fallback( self::value( $person, array( 'date_naissance', 'birth_date', 'dob' ) ) ) );
        $birth_place = self::join_non_empty( array(
            self::value( $person, array( 'ville_naissance', 'birth_city' ) ),
            self::value( $person, array( 'departement_naissance', 'dept_naissance', 'birth_department', 'pays_naissance', 'birth_country' ) ),
        ), ' - ' );
        self::set_table_cell( $xp, $table, 1, 2, 'Ville + Dept (ou Pays) : ' . self::fallback( $birth_place ) );
        self::set_table_cell( $xp, $table, 2, 1, 'Père : ' . self::fallback( self::value( $person, array( 'pere_nom_prenom', 'nom_prenom_pere', 'pere' ) ) ) );
        self::set_table_cell( $xp, $table, 2, 2, 'Mère : ' . self::fallback( self::value( $person, array( 'mere_nom_prenom', 'nom_prenom_mere', 'mere' ) ) ) );
        self::set_table_cell( $xp, $table, 3, 1, self::fallback( self::person_address( $person ) ) );
        self::set_table_cell( $xp, $table, 4, 1, self::fallback( self::value( $person, array( 'tel_mobile', 'telephone', 'tel', 'phone' ) ) ) );
        self::set_table_cell( $xp, $table, 5, 1, self::fallback( self::value( $person, array( 'email', 'mail' ) ) ) );
    }

    private static function set_table_cell( DOMXPath $xp, DOMNode $table, $row_index, $cell_index, $value ) {
        $rows = $xp->query( './w:tr', $table );
        if ( ! $rows || ! $rows->item( $row_index ) ) { return; }
        $cells = $xp->query( './w:tc', $rows->item( $row_index ) );
        $cell = $cells ? $cells->item( $cell_index ) : null;
        if ( ! $cell ) { return; }

        $paragraphs = $xp->query( './w:p', $cell );
        if ( $paragraphs ) {
            for ( $i = $paragraphs->length - 1; $i >= 0; $i-- ) { $cell->removeChild( $paragraphs->item( $i ) ); }
        }
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $p = $cell->ownerDocument->createElementNS( $ns, 'w:p' );
        $r = $cell->ownerDocument->createElementNS( $ns, 'w:r' );
        $t = $cell->ownerDocument->createElementNS( $ns, 'w:t' );
        $t->setAttributeNS( 'http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve' );
        $t->appendChild( $cell->ownerDocument->createTextNode( (string) $value ) );
        $r->appendChild( $t );
        $p->appendChild( $r );
        $cell->appendChild( $p );
    }

    private static function replace_text_fragment( DOMXPath $xp, $needle, $replacement ) {
        foreach ( $xp->query( '//w:t' ) as $node ) {
            if ( false !== stripos( (string) $node->nodeValue, $needle ) ) {
                $node->nodeValue = preg_replace( '/' . preg_quote( $needle, '/' ) . '.*$/iu', $replacement, (string) $node->nodeValue, 1 );
                return;
            }
        }
    }

    private static function build_people( $club, array $licences ) {
        $wanted = array( 'president', 'secretaire', 'tresorier', 'coach', 'coach' );
        $result = array();
        $used = array();

        foreach ( $wanted as $index => $wanted_role ) {
            $found = array();
            foreach ( $licences as $key => $licence ) {
                if ( isset( $used[ $key ] ) ) { continue; }
                $role = strtolower( remove_accents( self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) ) );
                $match = 'coach' === $wanted_role
                    ? (bool) preg_match( '/entraineur|instructeur|coach|educateur|enseignant/', $role )
                    : false !== strpos( $role, $wanted_role );
                if ( $match ) {
                    $found = (array) $licence;
                    $used[ $key ] = true;
                    break;
                }
            }

            $prefix = 'coach' === $wanted_role ? 'entraineur' : $wanted_role;
            $club_person = array();
            if ( $index < 3 || ( 3 === $index && 'coach' === $wanted_role ) ) {
                $club_person = array(
                    'nom'                    => self::value( $club, array( $prefix . '_nom' ) ),
                    'prenom'                 => self::value( $club, array( $prefix . '_prenom' ) ),
                    'email'                  => self::value( $club, array( $prefix . '_email' ) ),
                    'telephone'              => self::value( $club, array( $prefix . '_tel', $prefix . '_telephone' ) ),
                    'date_naissance'         => self::value( $club, array( $prefix . '_date_naissance' ) ),
                    'ville_naissance'        => self::value( $club, array( $prefix . '_ville_naissance' ) ),
                    'departement_naissance'  => self::value( $club, array( $prefix . '_departement_naissance' ) ),
                    'pays_naissance'         => self::value( $club, array( $prefix . '_pays_naissance' ) ),
                    'adresse'                => self::value( $club, array( $prefix . '_adresse' ) ),
                    'code_postal'            => self::value( $club, array( $prefix . '_code_postal' ) ),
                    'ville'                  => self::value( $club, array( $prefix . '_ville' ) ),
                );
            }

            if ( ! $found ) {
                $found = $club_person;
            } elseif ( $club_person ) {
                foreach ( $club_person as $key => $value ) {
                    if ( ( ! isset( $found[ $key ] ) || '' === trim( (string) $found[ $key ] ) ) && '' !== trim( (string) $value ) ) {
                        $found[ $key ] = $value;
                    }
                }
            }

            $result[] = $found;
        }
        return $result;
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", absint( $club_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        if ( ! $club_col ) { return array(); }
        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
            if ( ! in_array( $candidate, $columns, true ) ) { continue; }
            if ( 'season_end_year' === $candidate ) {
                $end_year = preg_match( '/^\d{4}-(\d{4})$/', $season, $m ) ? (int) $m[1] : 0;
                return $end_year ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `season_end_year`=%d ORDER BY id ASC", $club_id, $end_year ) ) : array(); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND REPLACE(TRIM(`{$candidate}`), '/', '-')=%s ORDER BY id ASC", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return array();
    }

    private static function guard_request( $action ) {
        if ( ! self::can_manage() || 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }
        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }
        check_admin_referer( $action . '_' . $club_id . '_' . $season );
        return array( $club_id, $season );
    }

    private static function full_club_address( $club ) {
        return self::join_non_empty( array(
            self::value( $club, array( 'adresse', 'adresse_siege', 'address' ) ),
            self::value( $club, array( 'complement_adresse' ) ),
            self::join_non_empty( array( self::value( $club, array( 'code_postal', 'cp', 'postal_code' ) ), self::value( $club, array( 'ville', 'city' ) ) ), ' ' ),
        ), ', ' );
    }

    private static function person_address( array $person ) {
        return self::join_non_empty( array(
            self::value( $person, array( 'adresse', 'address' ) ),
            self::value( $person, array( 'suite_adresse', 'complement_adresse' ) ),
            self::join_non_empty( array( self::value( $person, array( 'code_postal', 'cp', 'postal_code' ) ), self::value( $person, array( 'ville', 'city' ) ) ), ' ' ),
        ), ', ' );
    }

    private static function current_season() {
        if ( class_exists( 'UFSC_Season_Service' ) ) { return (string) UFSC_Season_Service::get_current_season(); }
        return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
    }

    private static function value( $row, array $keys ) {
        foreach ( $keys as $key ) {
            if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; }
            if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; }
        }
        return '';
    }

    private static function fallback( $value ) {
        return '' !== trim( (string) $value ) ? (string) $value : __( 'À compléter', 'ufsc-clubs' );
    }

    private static function join_non_empty( array $values, $separator ) {
        $values = array_values( array_filter( array_map( 'trim', array_map( 'strval', $values ) ), static function( $value ) { return '' !== $value; } ) );
        return implode( $separator, $values );
    }
}
