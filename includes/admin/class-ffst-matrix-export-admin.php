<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Exports matrices FFST multi-clubs.
 *
 * Module strictement en lecture seule : il ne modifie aucune fiche club,
 * licence, commande, quota, renouvellement ou saison.
 */
final class UFSC_FFST_Matrix_Export_Admin {
    const ACTION = 'ufsc_ffst_export_matrix';

    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'render_panel' ), 40 );
        add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_export' ) );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' ) && current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }

    public static function render_panel() {
        if ( ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( 'ufsc-ffst-documents' !== $page ) { return; }

        $clubs = self::get_clubs();
        $season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : self::current_season(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <style id="ufsc-ffst-matrix-export-style">
            .ufsc-ffst-matrix{max-width:1180px;margin:18px auto!important;padding:20px!important;border-left:4px solid #135e96!important;border-radius:10px;background:#fff;box-sizing:border-box;box-shadow:0 1px 2px rgba(0,0,0,.05)}
            .ufsc-ffst-matrix h2{margin:0 0 6px;font-size:19px}.ufsc-ffst-matrix p{margin:0 0 14px;color:#50575e}
            .ufsc-ffst-matrix__toolbar{display:grid;grid-template-columns:180px minmax(220px,1fr) auto;gap:10px;align-items:end;margin-bottom:12px}
            .ufsc-ffst-matrix label{display:block;font-weight:600;margin-bottom:5px}.ufsc-ffst-matrix input[type="text"],.ufsc-ffst-matrix select{width:100%;min-height:36px}
            .ufsc-ffst-matrix__list{border:1px solid #dcdcde;border-radius:8px;max-height:300px;overflow:auto;background:#f8f9fa;margin-bottom:14px}
            .ufsc-ffst-matrix__club{display:grid;grid-template-columns:30px minmax(180px,1fr) 170px 130px;gap:10px;align-items:center;padding:9px 12px;border-bottom:1px solid #e7e7e7;background:#fff}
            .ufsc-ffst-matrix__club:last-child{border-bottom:0}.ufsc-ffst-matrix__club-name{font-weight:600}.ufsc-ffst-matrix__muted{color:#646970;font-size:12px}
            .ufsc-ffst-matrix__actions{display:flex;flex-wrap:wrap;gap:8px}.ufsc-ffst-matrix__count{font-weight:600;color:#135e96}
            @media(max-width:782px){.ufsc-ffst-matrix{width:calc(100% - 20px)}.ufsc-ffst-matrix__toolbar{grid-template-columns:1fr}.ufsc-ffst-matrix__club{grid-template-columns:28px 1fr}.ufsc-ffst-matrix__club .ufsc-ffst-matrix__muted{grid-column:2}.ufsc-ffst-matrix__actions .button{width:100%}}
        </style>
        <div class="notice notice-info ufsc-ffst-matrix">
            <h2><?php echo esc_html__( 'Exports matrices FFST', 'ufsc-clubs' ); ?></h2>
            <p><?php echo esc_html__( 'Sélectionnez les clubs à transmettre puis générez une matrice Excel dédiée aux affiliations, aux dirigeants ou aux pratiquants. Ces exports sont indépendants des documents officiels déjà en place.', 'ufsc-clubs' ); ?></p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ufsc-ffst-matrix-form">
                <input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>">
                <?php wp_nonce_field( self::ACTION ); ?>
                <div class="ufsc-ffst-matrix__toolbar">
                    <div><label for="ufsc-matrix-season"><?php echo esc_html__( 'Saison', 'ufsc-clubs' ); ?></label><input type="text" id="ufsc-matrix-season" name="season" value="<?php echo esc_attr( $season ); ?>" pattern="\d{4}-\d{4}" required></div>
                    <div><label for="ufsc-matrix-search"><?php echo esc_html__( 'Rechercher un club', 'ufsc-clubs' ); ?></label><input type="text" id="ufsc-matrix-search" placeholder="Nom, ville, n° affiliation…"></div>
                    <div><button type="button" class="button" id="ufsc-matrix-select-visible"><?php echo esc_html__( 'Sélectionner les clubs affichés', 'ufsc-clubs' ); ?></button></div>
                </div>
                <div class="ufsc-ffst-matrix__list" id="ufsc-matrix-list">
                    <?php foreach ( $clubs as $club ) :
                        $id = absint( self::value( $club, array( 'id', 'club_id' ) ) );
                        $name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
                        $city = self::value( $club, array( 'ville', 'city' ) );
                        $aff = self::value( $club, array( 'numero_affiliation_ffst' ) );
                        $search = remove_accents( strtolower( $name . ' ' . $city . ' ' . $aff ) );
                        ?>
                        <label class="ufsc-ffst-matrix__club" data-search="<?php echo esc_attr( $search ); ?>">
                            <input type="checkbox" name="club_ids[]" value="<?php echo esc_attr( $id ); ?>">
                            <span class="ufsc-ffst-matrix__club-name"><?php echo esc_html( $name ?: 'Club #' . $id ); ?></span>
                            <span class="ufsc-ffst-matrix__muted"><?php echo esc_html( $city ); ?></span>
                            <span class="ufsc-ffst-matrix__muted"><?php echo esc_html( $aff ? 'FFST ' . $aff : 'N° FFST non renseigné' ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p><span class="ufsc-ffst-matrix__count" id="ufsc-matrix-count">0</span> <?php echo esc_html__( 'club(s) sélectionné(s)', 'ufsc-clubs' ); ?></p>
                <div class="ufsc-ffst-matrix__actions">
                    <button class="button button-primary" type="submit" name="matrix_type" value="affiliations"><?php echo esc_html__( 'Exporter matrice affiliations (.xlsx)', 'ufsc-clubs' ); ?></button>
                    <button class="button" type="submit" name="matrix_type" value="dirigeants"><?php echo esc_html__( 'Exporter matrice dirigeants (.xlsx)', 'ufsc-clubs' ); ?></button>
                    <button class="button" type="submit" name="matrix_type" value="pratiquants"><?php echo esc_html__( 'Exporter matrice pratiquants (.xlsx)', 'ufsc-clubs' ); ?></button>
                </div>
            </form>
        </div>
        <script>
        (function(){
            var form=document.getElementById('ufsc-ffst-matrix-form'); if(!form){return;}
            var search=document.getElementById('ufsc-matrix-search'), list=document.getElementById('ufsc-matrix-list'), count=document.getElementById('ufsc-matrix-count'), select=document.getElementById('ufsc-matrix-select-visible');
            function norm(v){return (v||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').trim();}
            function refresh(){var n=0;list.querySelectorAll('input[type=checkbox]').forEach(function(cb){if(cb.checked)n++;});count.textContent=n;}
            search.addEventListener('input',function(){var q=norm(search.value);list.querySelectorAll('.ufsc-ffst-matrix__club').forEach(function(row){row.style.display=!q||norm(row.getAttribute('data-search')).indexOf(q)!==-1?'grid':'none';});});
            select.addEventListener('click',function(){list.querySelectorAll('.ufsc-ffst-matrix__club').forEach(function(row){if(row.style.display!=='none'){row.querySelector('input[type=checkbox]').checked=true;}});refresh();});
            list.addEventListener('change',refresh);refresh();
            form.addEventListener('submit',function(e){if(!list.querySelector('input[type=checkbox]:checked')){e.preventDefault();alert('Sélectionnez au moins un club.');}});
        })();
        </script>
        <?php
    }

    public static function handle_export() {
        if ( ! self::can_manage() || 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( self::ACTION );
        if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
            wp_die( esc_html__( 'Le moteur Excel est indisponible.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        $type = isset( $_POST['matrix_type'] ) && ! is_array( $_POST['matrix_type'] ) ? sanitize_key( wp_unslash( $_POST['matrix_type'] ) ) : '';
        $season = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        $club_ids = isset( $_POST['club_ids'] ) && is_array( $_POST['club_ids'] ) ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['club_ids'] ) ) ) ) ) : array();
        if ( ! in_array( $type, array( 'affiliations', 'dirigeants', 'pratiquants' ), true ) || ! preg_match( '/^\d{4}-\d{4}$/', $season ) || ! $club_ids ) {
            wp_die( esc_html__( 'Paramètres d’export FFST invalides.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }

        $clubs = self::get_clubs_by_ids( $club_ids );
        if ( ! $clubs ) { wp_die( esc_html__( 'Aucun club sélectionné n’a été trouvé.', 'ufsc-clubs' ), '', array( 'response' => 404 ) ); }

        if ( 'affiliations' === $type ) { $spreadsheet = self::build_affiliations_workbook( $clubs, $season ); }
        elseif ( 'dirigeants' === $type ) { $spreadsheet = self::build_leaders_workbook( $clubs, $season ); }
        else { $spreadsheet = self::build_practitioners_workbook( $clubs, $season ); }

        self::send_workbook( $spreadsheet, 'matrice-ffst-' . $type . '-' . sanitize_title( $season ) . '.xlsx' );
    }

    private static function build_affiliations_workbook( array $clubs, $season ) {
        $ss = new \\PhpOffice\\PhpSpreadsheet\\Spreadsheet();
        $sheet = $ss->getActiveSheet(); $sheet->setTitle( 'Saisie clubs' );
        $headers = self::affiliation_headers();
        $sheet->setCellValue( 'A1', 'Matrice FFST ' . $season . ' — Affiliation / Réaffiliation' );
        $sheet->mergeCells( 'A1:DA1' );
        $sheet->setCellValue( 'A2', 'Export généré depuis UFSC Gestion. Les champs non disponibles restent vides et sont signalés dans la feuille Contrôle.' );
        $sheet->mergeCells( 'A2:DA2' );
        $sheet->fromArray( $headers, null, 'A4' );
        $row = 5;
        foreach ( $clubs as $club ) { $sheet->fromArray( self::affiliation_row( $club ), null, 'A' . $row++ ); }
        self::style_data_sheet( $sheet, count( $headers ), $row - 1, 4 );

        $dict = $ss->createSheet(); $dict->setTitle( 'Dictionnaire champs' );
        $dict->fromArray( array( 'Ordre', 'Section', 'Champ Excel', 'Remarque' ), null, 'A1' );
        $i = 1; foreach ( $headers as $header ) { $dict->fromArray( array( $i++, self::affiliation_section( $header ), $header, self::field_remark( $header ) ), null, 'A' . $i ); }
        self::style_data_sheet( $dict, 4, $i, 1 );

        $reserved = $ss->createSheet(); $reserved->setTitle( 'Réservé FFST' );
        $reserved->fromArray( array( array( 'Champ du document officiel', 'À remplir par' ), array( 'Dossier reçu le', 'FFST' ), array( 'Accord donné le', 'FFST' ), array( 'Bordereau d’affiliation envoyé le', 'FFST' ), array( 'Rappel de pièce(s) manquante(s)', 'FFST' ), array( 'Demandée(s) le', 'FFST' ), array( 'Date d’affiliation de l’association', 'FFST' ), array( 'N° d’affiliation', 'FFST' ), array( 'Code activité', 'FFST' ), array( 'Observations', 'FFST' ) ), null, 'A1' );
        self::style_data_sheet( $reserved, 2, 10, 1 );

        self::add_control_sheet( $ss, $clubs, $season, 'Affiliation' );
        $ss->setActiveSheetIndex( 0 );
        return $ss;
    }

    private static function build_leaders_workbook( array $clubs, $season ) {
        $ss = new \\PhpOffice\\PhpSpreadsheet\\Spreadsheet();
        $sheet = $ss->getActiveSheet(); $sheet->setTitle( 'Dirigeants' );
        $headers = array( 'Club', 'N° affiliation FFST', 'Saison', 'Fonction', 'Nom', 'Prénom', 'Date de naissance', 'Ville de naissance', 'Département de naissance', 'Pays de naissance', 'Père (si né à l’étranger)', 'Mère (si née à l’étranger)', 'Adresse', 'Complément d’adresse', 'Code postal', 'Ville', 'Téléphone', 'E-mail', 'N° licence UFSC', 'N° licence FFST', 'État' );
        $sheet->fromArray( $headers, null, 'A1' );
        $row = 2;
        foreach ( $clubs as $club ) {
            $licences = self::get_licences_for_club( absint( self::value( $club, array( 'id', 'club_id' ) ) ), $season );
            foreach ( self::leader_rows( $club, $licences, $season ) as $data ) { $sheet->fromArray( $data, null, 'A' . $row++ ); }
        }
        self::style_data_sheet( $sheet, count( $headers ), $row - 1, 1 );
        self::add_control_sheet( $ss, $clubs, $season, 'Dirigeants' );
        $ss->setActiveSheetIndex( 0 );
        return $ss;
    }

    private static function build_practitioners_workbook( array $clubs, $season ) {
        $ss = new \\PhpOffice\\PhpSpreadsheet\\Spreadsheet();
        $sheet = $ss->getActiveSheet(); $sheet->setTitle( 'Pratiquants' );
        $headers = array( 'Club', 'N° affiliation FFST', 'Saison', 'Discipline', 'Code discipline FFST', 'N° licence UFSC', 'N° licence FFST', 'Nom', 'Prénom', 'Sexe', 'Date de naissance', 'Adresse', 'Complément d’adresse', 'Code postal', 'Ville', 'Téléphone', 'E-mail', 'Fonction FFST', 'Pratique', 'Statut licence', 'État' );
        $sheet->fromArray( $headers, null, 'A1' );
        $row = 2;
        foreach ( $clubs as $club ) {
            $club_id = absint( self::value( $club, array( 'id', 'club_id' ) ) );
            foreach ( self::get_licences_for_club( $club_id, $season ) as $licence ) {
                if ( self::is_leader_licence( $licence ) ) { continue; }
                $sheet->fromArray( self::practitioner_row( $club, $licence, $season ), null, 'A' . $row++ );
            }
        }
        self::style_data_sheet( $sheet, count( $headers ), $row - 1, 1 );
        self::add_control_sheet( $ss, $clubs, $season, 'Pratiquants' );
        $ss->setActiveSheetIndex( 0 );
        return $ss;
    }

    private static function affiliation_headers() {
        return array( 'Type demande','N° réaffiliation','Discipline(s) pratiquée(s) - en-tête','Nom du club','Club - Adresse','Club - Complément d’adresse','Club - Code postal','Club - Ville','Téléphone club','Mail club','Site internet','Déclaration Préfecture - Date','Déclaration Préfecture - N°','Agrément Jeunesse et Sports - Date','Agrément Jeunesse et Sports - N°','Salle - Adresse','Salle - Complément d’adresse','Salle - Code postal','Salle - Ville','Président - Nom de naissance','Président - Prénom','Président - Date de naissance','Président - Ville de naissance','Président - Département / Pays de naissance','Président - Père - Nom de naissance et prénom','Président - Mère - Nom de naissance et prénom','Président - Adresse','Président - Complément d’adresse','Président - Code postal','Président - Ville','Président - Téléphone perso','Président - Mail perso','Secrétaire - Nom de naissance','Secrétaire - Prénom','Secrétaire - Date de naissance','Secrétaire - Ville de naissance','Secrétaire - Département / Pays de naissance','Secrétaire - Père - Nom de naissance et prénom','Secrétaire - Mère - Nom de naissance et prénom','Secrétaire - Adresse','Secrétaire - Complément d’adresse','Secrétaire - Code postal','Secrétaire - Ville','Secrétaire - Téléphone','Secrétaire - Mail perso','Trésorier - Nom de naissance','Trésorier - Prénom','Trésorier - Date de naissance','Trésorier - Ville de naissance','Trésorier - Département / Pays de naissance','Trésorier - Père - Nom de naissance et prénom','Trésorier - Mère - Nom de naissance et prénom','Trésorier - Adresse','Trésorier - Complément d’adresse','Trésorier - Code postal','Trésorier - Ville','Trésorier - Téléphone','Trésorier - Mail perso','Entraîneur 1 - Nom de naissance','Entraîneur 1 - Prénom','Entraîneur 1 - Date de naissance','Entraîneur 1 - Ville de naissance','Entraîneur 1 - Département / Pays de naissance','Entraîneur 1 - Père - Nom de naissance et prénom','Entraîneur 1 - Mère - Nom de naissance et prénom','Entraîneur 1 - Adresse','Entraîneur 1 - Complément d’adresse','Entraîneur 1 - Code postal','Entraîneur 1 - Ville','Entraîneur 1 - Téléphone','Entraîneur 1 - Mail perso','Entraîneur 2 - Nom de naissance','Entraîneur 2 - Prénom','Entraîneur 2 - Date de naissance','Entraîneur 2 - Ville de naissance','Entraîneur 2 - Département / Pays de naissance','Entraîneur 2 - Père - Nom de naissance et prénom','Entraîneur 2 - Mère - Nom de naissance et prénom','Entraîneur 2 - Adresse','Entraîneur 2 - Complément d’adresse','Entraîneur 2 - Code postal','Entraîneur 2 - Ville','Entraîneur 2 - Téléphone','Entraîneur 2 - Mail perso','Correspondance - Nom','Correspondance - Prénom','Correspondance - Adresse','Correspondance - Complément d’adresse','Correspondance - Code postal','Correspondance - Ville','Correspondance - Tél. bureau','Correspondance - Tél. domicile','Correspondance - Mail','Discipline 1 - Nom exact','Discipline 1 - Code FFST','Discipline 2 - Nom exact','Discipline 2 - Code FFST','Discipline 3 - Nom exact','Discipline 3 - Code FFST','Manifestation 1','Manifestation 2','Manifestation 3','Manifestation 4','Date signature club','Nom / qualité du signataire' );
    }

    private static function affiliation_row( $club ) {
        $aff = self::value( $club, array( 'numero_affiliation_ffst' ) );
        $disciplines = self::split_values( self::value( $club, array( 'disciplines_ffst', 'disciplines', 'discipline' ) ) );
        $codes = self::split_values( self::value( $club, array( 'codes_disciplines_ffst', 'code_discipline' ) ) );
        $row = array( $aff ? 'RÉAFFILIATION' : 'AFFILIATION', $aff, self::value( $club, array( 'disciplines_ffst', 'disciplines', 'discipline' ) ), self::value( $club, array( 'nom', 'name', 'club_name' ) ), self::value( $club, array( 'adresse' ) ), self::value( $club, array( 'complement_adresse' ) ), self::value( $club, array( 'code_postal' ) ), self::value( $club, array( 'ville' ) ), self::value( $club, array( 'telephone', 'tel' ) ), self::value( $club, array( 'email', 'mail' ) ), self::value( $club, array( 'url_site', 'site_web', 'website' ) ), self::value( $club, array( 'date_declaration' ) ), self::value( $club, array( 'num_declaration', 'rna_number', 'rna' ) ), self::value( $club, array( 'date_agrement_js' ) ), self::value( $club, array( 'numero_agrement_js' ) ), self::value( $club, array( 'adresse_salle' ) ), self::value( $club, array( 'complement_adresse_salle' ) ), self::value( $club, array( 'code_postal_salle' ) ), self::value( $club, array( 'ville_salle' ) ) );
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur' ) as $prefix ) {
            $row[] = self::value( $club, array( $prefix . '_nom' ) ); $row[] = self::value( $club, array( $prefix . '_prenom' ) ); $row[] = self::value( $club, array( $prefix . '_date_naissance' ) ); $row[] = self::value( $club, array( $prefix . '_ville_naissance' ) );
            $row[] = self::join_non_empty( array( self::value( $club, array( $prefix . '_departement_naissance' ) ), self::value( $club, array( $prefix . '_pays_naissance' ) ) ), ' / ' );
            $row[] = self::value( $club, array( $prefix . '_pere_nom_prenom' ) ); $row[] = self::value( $club, array( $prefix . '_mere_nom_prenom' ) ); $row[] = self::value( $club, array( $prefix . '_adresse' ) ); $row[] = self::value( $club, array( $prefix . '_complement_adresse' ) ); $row[] = self::value( $club, array( $prefix . '_code_postal' ) ); $row[] = self::value( $club, array( $prefix . '_ville' ) ); $row[] = self::value( $club, array( $prefix . '_tel', $prefix . '_telephone' ) ); $row[] = self::value( $club, array( $prefix . '_email' ) );
        }
        for ( $i = 0; $i < 13; $i++ ) { $row[] = ''; } // Entraîneur 2 non stocké séparément à ce jour.
        $row[] = self::value( $club, array( 'correspondant_nom' ) ); $row[] = self::value( $club, array( 'correspondant_prenom' ) );
        for ( $i = 0; $i < 6; $i++ ) { $row[] = ''; }
        $row[] = self::value( $club, array( 'correspondant_tel' ) ); $row[] = ''; $row[] = self::value( $club, array( 'correspondant_email' ) );
        for ( $i = 0; $i < 3; $i++ ) { $row[] = isset( $disciplines[ $i ] ) ? $disciplines[ $i ] : ''; $row[] = isset( $codes[ $i ] ) ? $codes[ $i ] : ''; }
        for ( $i = 0; $i < 5; $i++ ) { $row[] = ''; }
        $row[] = trim( self::join_non_empty( array( self::value( $club, array( 'signataire_prenom' ) ), self::value( $club, array( 'signataire_nom' ) ), self::value( $club, array( 'signataire_qualite' ) ) ), ' - ' ) );
        return array_slice( array_pad( $row, 105, '' ), 0, 105 );
    }

    private static function leader_rows( $club, array $licences, $season ) {
        $rows = array();
        foreach ( array( 'president' => 'Président', 'secretaire' => 'Secrétaire', 'tresorier' => 'Trésorier', 'entraineur' => 'Entraîneur / instructeur' ) as $prefix => $label ) {
            $nom = self::value( $club, array( $prefix . '_nom' ) ); $prenom = self::value( $club, array( $prefix . '_prenom' ) );
            if ( '' === trim( $nom . $prenom ) ) { continue; }
            $lic = self::find_person_licence( $licences, $nom, $prenom, $label );
            $missing = array(); foreach ( array( $nom => 'nom', $prenom => 'prénom', self::value( $club, array( $prefix . '_date_naissance' ) ) => 'date de naissance' ) as $value => $name ) { if ( '' === trim( (string) $value ) ) { $missing[] = $name; } }
            $rows[] = array( self::value( $club, array( 'nom', 'name', 'club_name' ) ), self::value( $club, array( 'numero_affiliation_ffst' ) ), $season, $label, $nom, $prenom, self::value( $club, array( $prefix . '_date_naissance' ) ), self::value( $club, array( $prefix . '_ville_naissance' ) ), self::value( $club, array( $prefix . '_departement_naissance' ) ), self::value( $club, array( $prefix . '_pays_naissance' ) ), self::value( $club, array( $prefix . '_pere_nom_prenom' ) ), self::value( $club, array( $prefix . '_mere_nom_prenom' ) ), self::value( $club, array( $prefix . '_adresse' ) ), self::value( $club, array( $prefix . '_complement_adresse' ) ), self::value( $club, array( $prefix . '_code_postal' ) ), self::value( $club, array( $prefix . '_ville' ) ), self::value( $club, array( $prefix . '_tel', $prefix . '_telephone' ) ), self::value( $club, array( $prefix . '_email' ) ), $lic ? self::value( $lic, array( 'numero_licence_ufsc', 'numero_licence', 'licence_number' ) ) : '', $lic ? self::value( $lic, array( 'numero_licence_ffst', 'licence_ffst' ) ) : '', $missing ? 'À compléter : ' . implode( ', ', $missing ) : 'OK' );
        }
        return $rows;
    }

    private static function practitioner_row( $club, $licence, $season ) {
        $required = array( 'nom' => self::value( $licence, array( 'nom', 'nom_licence', 'last_name' ) ), 'prénom' => self::value( $licence, array( 'prenom', 'first_name' ) ), 'date de naissance' => self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ), 'adresse' => self::value( $licence, array( 'adresse', 'address' ) ) );
        $missing = array(); foreach ( $required as $label => $value ) { if ( '' === trim( (string) $value ) ) { $missing[] = $label; } }
        return array( self::value( $club, array( 'nom', 'name', 'club_name' ) ), self::value( $club, array( 'numero_affiliation_ffst' ) ), $season, self::value( $licence, array( 'discipline', 'discipline_nom', 'sport' ) ) ?: self::value( $club, array( 'disciplines_ffst' ) ), self::value( $licence, array( 'code_discipline_ffst', 'code_discipline' ) ) ?: self::value( $club, array( 'codes_disciplines_ffst' ) ), self::value( $licence, array( 'numero_licence_ufsc', 'numero_licence', 'licence_number' ) ), self::value( $licence, array( 'numero_licence_ffst', 'licence_ffst' ) ), self::value( $licence, array( 'nom', 'nom_licence', 'last_name' ) ), self::value( $licence, array( 'prenom', 'first_name' ) ), self::value( $licence, array( 'sexe', 'genre', 'gender' ) ), self::value( $licence, array( 'date_naissance', 'birth_date', 'dob' ) ), self::value( $licence, array( 'adresse', 'address' ) ), self::value( $licence, array( 'complement_adresse', 'address2' ) ), self::value( $licence, array( 'code_postal', 'postal_code', 'cp' ) ), self::value( $licence, array( 'ville', 'city' ) ), self::value( $licence, array( 'telephone', 'tel', 'phone' ) ), self::value( $licence, array( 'email', 'mail' ) ), 'P', self::value( $licence, array( 'type_pratique', 'pratique', 'competition_loisir' ) ), self::value( $licence, array( 'statut', 'status' ) ), $missing ? 'À compléter : ' . implode( ', ', $missing ) : 'OK' );
    }

    private static function is_leader_licence( $licence ) {
        $role = strtolower( remove_accents( self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) ) );
        foreach ( array( 'president', 'secretaire', 'tresorier', 'entraineur', 'instructeur', 'dirigeant' ) as $needle ) { if ( false !== strpos( $role, $needle ) ) { return true; } }
        return false;
    }

    private static function find_person_licence( array $licences, $nom, $prenom, $role_label ) {
        $n = self::normalize( $nom ); $p = self::normalize( $prenom ); $role_n = self::normalize( $role_label );
        foreach ( $licences as $lic ) { if ( $n && $p && self::normalize( self::value( $lic, array( 'nom', 'nom_licence', 'last_name' ) ) ) === $n && self::normalize( self::value( $lic, array( 'prenom', 'first_name' ) ) ) === $p ) { return $lic; } }
        foreach ( $licences as $lic ) { $role = self::normalize( self::value( $lic, array( 'role', 'fonction', 'poste', 'position' ) ) ); if ( $role && ( false !== strpos( $role_n, $role ) || false !== strpos( $role, $role_n ) ) ) { return $lic; } }
        return null;
    }

    private static function get_clubs() {
        global $wpdb; $table = self::clubs_table(); if ( ! $table ) { return array(); }
        return (array) $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY nom ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery
    }

    private static function get_clubs_by_ids( array $ids ) {
        global $wpdb; $table = self::clubs_table(); if ( ! $table ) { return array(); }
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ); if ( ! $ids ) { return array(); }
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id IN ({$placeholders}) ORDER BY nom ASC", ...$ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences_for_club( $club_id, $season ) {
        global $wpdb; $table = self::licences_table(); if ( ! $table ) { return array(); }
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' ); if ( ! $club_col ) { return array(); }
        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $season_col ) {
            if ( ! in_array( $season_col, $columns, true ) ) { continue; }
            if ( 'season_end_year' === $season_col ) { $end = preg_match( '/^\d{4}-(\d{4})$/', $season, $m ) ? (int) $m[1] : 0; return $end ? (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `season_end_year`=%d", $club_id, $end ) ) : array(); }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND REPLACE(TRIM(`{$season_col}`), '/', '-')=%s", $club_id, $season ) );
        }
        return array();
    }

    private static function clubs_table() { global $wpdb; return class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' ); }
    private static function licences_table() { global $wpdb; return class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' ); }

    private static function add_control_sheet( $ss, array $clubs, $season, $type ) {
        $sheet = $ss->createSheet(); $sheet->setTitle( 'Contrôle' );
        $sheet->fromArray( array( 'Club', 'Saison', 'Type export', 'N° affiliation FFST', 'Observation' ), null, 'A1' ); $row = 2;
        foreach ( $clubs as $club ) { $missing = array(); foreach ( array( 'nom' => 'nom club', 'adresse' => 'adresse', 'code_postal' => 'code postal', 'ville' => 'ville' ) as $field => $label ) { if ( '' === trim( self::value( $club, array( $field ) ) ) ) { $missing[] = $label; } } $sheet->fromArray( array( self::value( $club, array( 'nom', 'name', 'club_name' ) ), $season, $type, self::value( $club, array( 'numero_affiliation_ffst' ) ), $missing ? 'À compléter : ' . implode( ', ', $missing ) : 'OK' ), null, 'A' . $row++ ); }
        self::style_data_sheet( $sheet, 5, $row - 1, 1 );
    }

    private static function style_data_sheet( $sheet, $column_count, $last_row, $header_row ) {
        if ( $column_count < 1 ) { return; }
        $last_col = \\PhpOffice\\PhpSpreadsheet\\Cell\\Coordinate::stringFromColumnIndex( $column_count );
        $sheet->freezePane( 'A' . ( $header_row + 1 ) );
        $sheet->getStyle( 'A' . $header_row . ':' . $last_col . $header_row )->getFont()->setBold( true )->getColor()->setARGB( 'FFFFFFFF' );
        $sheet->getStyle( 'A' . $header_row . ':' . $last_col . $header_row )->getFill()->setFillType( \\PhpOffice\\PhpSpreadsheet\\Style\\Fill::FILL_SOLID )->getStartColor()->setARGB( 'FF135E96' );
        $sheet->getStyle( 'A' . $header_row . ':' . $last_col . max( $header_row, $last_row ) )->getAlignment()->setVertical( \\PhpOffice\\PhpSpreadsheet\\Style\\Alignment::VERTICAL_TOP )->setWrapText( true );
        for ( $c = 1; $c <= $column_count; $c++ ) { $sheet->getColumnDimension( \\PhpOffice\\PhpSpreadsheet\\Cell\\Coordinate::stringFromColumnIndex( $c ) )->setWidth( $column_count > 40 ? 22 : 20 ); }
        $sheet->setAutoFilter( 'A' . $header_row . ':' . $last_col . $header_row );
    }

    private static function send_workbook( $spreadsheet, $filename ) {
        $tmp = wp_tempnam( $filename ); if ( ! $tmp ) { wp_die( esc_html__( 'Impossible de créer le fichier temporaire.', 'ufsc-clubs' ), '', array( 'response' => 500 ) ); }
        $writer = new \\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx( $spreadsheet ); $writer->save( $tmp );
        nocache_headers(); header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ); header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' ); header( 'Content-Length: ' . filesize( $tmp ) ); readfile( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        @unlink( $tmp ); exit;
    }

    private static function current_season() { if ( function_exists( 'ufsc_get_current_season' ) ) { return (string) ufsc_get_current_season(); } $y=(int) wp_date('Y'); $m=(int) wp_date('n'); $start=$m>=8?$y:$y-1; return $start . '-' . ( $start + 1 ); }
    private static function split_values( $value ) { return array_values( array_filter( array_map( 'trim', preg_split( '/[;,|\n\r]+/', (string) $value ) ) ) ); }
    private static function join_non_empty( array $values, $separator ) { return implode( $separator, array_values( array_filter( array_map( static function( $v ){ return trim( (string) $v ); }, $values ), static function( $v ){ return '' !== $v; } ) ) ); }
    private static function normalize( $value ) { return strtolower( trim( remove_accents( (string) $value ) ) ); }
    private static function value( $row, array $keys ) { foreach ( $keys as $key ) { if ( is_object( $row ) && isset( $row->{$key} ) && '' !== trim( (string) $row->{$key} ) ) { return (string) $row->{$key}; } if ( is_array( $row ) && isset( $row[ $key ] ) && '' !== trim( (string) $row[ $key ] ) ) { return (string) $row[ $key ]; } } return ''; }
    private static function affiliation_section( $header ) { foreach ( array( 'Président','Secrétaire','Trésorier','Entraîneur 1','Entraîneur 2','Correspondance','Salle','Discipline','Manifestation' ) as $section ) { if ( 0 === strpos( $header, $section ) ) { return $section; } } return false !== strpos( $header, 'Club' ) || false !== strpos( $header, 'Préfecture' ) || false !== strpos( $header, 'Agrément' ) ? 'Renseignements administratifs — Club' : 'Demande / signature'; }
    private static function field_remark( $header ) { if ( false !== strpos( $header, 'Père' ) || false !== strpos( $header, 'Mère' ) ) { return 'À renseigner si la personne est née à l’étranger.'; } if ( false !== strpos( $header, 'Adresse' ) || false !== strpos( $header, 'Code postal' ) || false !== strpos( $header, 'Ville' ) ) { return 'Adresse structurée en champs distincts.'; } return ''; }
}

UFSC_FFST_Matrix_Export_Admin::init();
