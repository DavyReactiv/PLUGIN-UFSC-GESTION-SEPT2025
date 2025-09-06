<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Only load the admin class in the dashboard context.
if ( ! is_admin() ) {
    return;
}

class UFSC_SQL_Admin {

    /**
     * Generate status badge with colored dot
     */
    private static function get_status_badge($status, $label = '') {
        if (empty($label)) {
            $label = UFSC_SQL::statuses()[$status] ?? $status;
        }
        
        // Map status to CSS class
        $status_map = array(
            'valide' => 'valid',
            'validee' => 'valid',
            'active' => 'valid',
            'en_attente' => 'pending',
            'attente' => 'pending',
            'pending' => 'pending',
            'a_regler' => 'pending',
            'refuse' => 'rejected',
            'rejected' => 'rejected',
            'desactive' => 'inactive',
            'inactive' => 'inactive',
            'off' => 'inactive'
        );
        
        $css_class = isset($status_map[$status]) ? $status_map[$status] : 'inactive';
        
        return '<span class="ufsc-status-badge ufsc-status-' . esc_attr($css_class) . '">' .
               '<span class="ufsc-status-dot"></span>' .
               esc_html($label) .
               '</span>';
    }

    /* ---------------- Menus cachés pour accès direct ---------------- */
    public static function register_hidden_pages(){
        // Enregistrer les pages cachées pour les actions directes (mentionnées dans les specs)
        add_submenu_page( null, __('Clubs (SQL)','ufsc-clubs'), __('Clubs (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-clubs', array(__CLASS__,'render_clubs') );
        add_submenu_page( null, __('Licences (SQL)','ufsc-clubs'), __('Licences (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-licences', array(__CLASS__,'render_licences') );
        // Alias pour compatibilité avec la spec (licenses vs licences)
        add_submenu_page( null, __('Licences (SQL)','ufsc-clubs'), __('Licences (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-licenses', array(__CLASS__,'render_licences') );
    }

    /* ---------------- Menus complets (obsolète - remplacé par menu unifié) ---------------- */
    public static function register_menus(){
        add_menu_page( __('UFSC – Données (SQL)','ufsc-clubs'), __('UFSC – Données (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql', array(__CLASS__,'render_dashboard'), 'dashicons-database', 59 );
        add_submenu_page( 'ufsc-sql', __('Clubs (SQL)','ufsc-clubs'), __('Clubs (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-clubs', array(__CLASS__,'render_clubs') );
        add_submenu_page( 'ufsc-sql', __('Licences (SQL)','ufsc-clubs'), __('Licences (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-licences', array(__CLASS__,'render_licences') );
        add_submenu_page( 'ufsc-sql', __('Réglages (SQL)','ufsc-clubs'), __('Réglages (SQL)','ufsc-clubs'), 'manage_options', 'ufsc-sql-settings', array(__CLASS__,'render_settings') );
    }

    /* ---------------- Dashboard ---------------- */
    public static function render_dashboard(){
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $c = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$s['table_clubs']}`" );
        $l = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$s['table_licences']}`" );
        echo '<div class="wrap"><h1>UFSC – SQL</h1>';
        echo UFSC_CL_Utils::kpi_cards(array(
            array('label'=>__('Clubs (SQL)','ufsc-clubs'),'value'=>$c),
            array('label'=>__('Licences (SQL)','ufsc-clubs'),'value'=>$l),
        ));
        echo '</div>';
    }

    /* ---------------- Réglages ---------------- */
    public static function render_settings(){
        if ( isset($_POST['ufsc_sql_save']) && check_admin_referer('ufsc_sql_settings') ){
            $in = UFSC_CL_Utils::sanitize_text_arr( $_POST );
            $opts = UFSC_SQL::get_settings();
            $opts['table_clubs'] = $in['table_clubs'];
            $opts['table_licences'] = $in['table_licences'];
            update_option('ufsc_sql_settings',$opts);
            echo '<div class="updated"><p>'.esc_html__('Réglages enregistrés.','ufsc-clubs').'</p></div>';
        }
        $s = UFSC_SQL::get_settings();
        echo '<div class="wrap"><h1>'.esc_html__('Réglages (SQL)','ufsc-clubs').'</h1><form method="post">';
        wp_nonce_field('ufsc_sql_settings');
        echo '<table class="form-table">';
        echo '<tr><th>Table Clubs</th><td><input type="text" name="table_clubs" value="'.esc_attr($s['table_clubs']).'" /></td></tr>';
        echo '<tr><th>Table Licences</th><td><input type="text" name="table_licences" value="'.esc_attr($s['table_licences']).'" /></td></tr>';
        echo '</table>';
        echo '<p class="description">'.esc_html__('Booléens: 1 = Oui / 0 = Non.','ufsc-clubs').'</p>';
        submit_button('Enregistrer','primary','ufsc_sql_save');
        echo '</form></div>';
    }

    /* ---------------- Liste Clubs ---------------- */
    public static function render_clubs(){
        // Check if we should show edit/new form
        if ( isset($_GET['action']) && $_GET['action']==='edit' ){
            $id = (int) $_GET['id'];
            self::render_club_form($id);
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='view' ){
            $id = (int) $_GET['id'];
            self::render_club_form($id, true); // true = readonly mode
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='new' ){
            self::render_club_form(0);
            return;
        } elseif ( isset($_GET['export']) ){
            self::handle_clubs_export();
            return;
        }

        // Use enhanced list table
        UFSC_Clubs_List_Table::render();
    }

    /* ---------------- Handle clubs export ---------------- */
    private static function handle_clubs_export() {
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];

        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $where = $status ? $wpdb->prepare("WHERE statut=%s",$status) : '';

        $rows = $wpdb->get_results("SELECT $pk, nom, region, statut, quota_licences FROM `$t` $where ORDER BY $pk DESC");
        self::csv_clubs($rows);
    }

    private static function csv_clubs($rows){
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="clubs_sql.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, array('id','nom','region','statut','quota_licences'));
        if ( $rows ){
            foreach($rows as $r){
                fputcsv($out, array($r->id,$r->nom,$r->region,$r->statut,$r->quota_licences));
            }
        }
        fclose($out);
    }

    private static function render_club_form( $id, $readonly = false ){
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];
        $fields = UFSC_SQL::get_club_fields();
        $row = $id ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id) ) : null;

        if ( $readonly ) {
            echo '<h2>'.( $id ? esc_html__('Consulter le club','ufsc-clubs') : esc_html__('Nouveau club','ufsc-clubs') ).'</h2>';
        } else {
            echo '<h2>'.( $id ? esc_html__('Éditer le club','ufsc-clubs') : esc_html__('Nouveau club','ufsc-clubs') ).'</h2>';
        }
        
        // Affichage des messages
        if ( isset($_GET['updated']) && $_GET['updated'] == '1' ) {
            echo UFSC_CL_Utils::show_success(__('Club enregistré avec succès', 'ufsc-clubs'));
        }
        if ( isset($_GET['error']) ) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }
        
        if ( !$readonly ) {
            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field('ufsc_sql_save_club');
            echo '<input type="hidden" name="action" value="ufsc_sql_save_club" />';
            echo '<input type="hidden" name="id" value="'.(int)$id.'" />';
            echo '<input type="hidden" name="page" value="ufsc-sql-clubs"/>';
        }

        echo '<div class="ufsc-grid">';
        foreach ( $fields as $k=>$conf ){
            $val = $row ? ( isset($row->$k) ? $row->$k : '' ) : '';
            self::render_field_club($k,$conf,$val, $readonly);
        }
        echo '</div>';
        

        // Add Documents section for non-readonly mode
        if ( !$readonly ) {
            echo '<h3>' . esc_html__('Documents du club', 'ufsc-clubs') . '</h3>';
            echo '<div class="ufsc-documents-section">';
            
            // Logo du club
            echo '<div class="ufsc-document-upload">';
            echo '<h4>' . esc_html__('Logo du club', 'ufsc-clubs') . '</h4>';
            $logo_id = get_option( 'ufsc_club_logo_' . $id );
            if ( $logo_id ) {
                $logo_url = wp_get_attachment_url( $logo_id );
                $logo_title = get_the_title( $logo_id );
                echo '<div class="ufsc-current-file">';
                echo '<p><strong>' . esc_html__('Fichier actuel:', 'ufsc-clubs') . '</strong></p>';
                echo '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $logo_title ) . '" style="max-width: 200px; max-height: 150px;">';
                echo '<p>';
                echo '<a href="' . esc_url( $logo_url ) . '" target="_blank" rel="noopener">' . esc_html__('Voir', 'ufsc-clubs') . '</a> | ';
                echo '<a href="' . esc_url( $logo_url ) . '" download>' . esc_html__('Télécharger', 'ufsc-clubs') . '</a>';
                echo '</p>';
                echo '</div>';
            }
            echo '<input type="file" name="club_logo_upload" accept="image/*">';
            echo '<p class="description">' . esc_html__('Formats acceptés: JPG, PNG, SVG. Taille max: 2MB', 'ufsc-clubs') . '</p>';
            echo '</div>';
            
            // Attestation UFSC
            echo '<div class="ufsc-document-upload">';
            echo '<h4>' . esc_html__('Attestation UFSC', 'ufsc-clubs') . '</h4>';
            $attestation_id = get_option( 'ufsc_club_doc_attestation_affiliation_' . $id );
            if ( $attestation_id ) {
                $attestation_url = wp_get_attachment_url( $attestation_id );
                $attestation_title = get_the_title( $attestation_id );
                echo '<div class="ufsc-current-file">';
                echo '<p><strong>' . esc_html__('Fichier actuel:', 'ufsc-clubs') . '</strong> ' . esc_html( $attestation_title ) . '</p>';
                echo '<p>';
                echo '<a href="' . esc_url( $attestation_url ) . '" target="_blank" rel="noopener">' . esc_html__('Voir', 'ufsc-clubs') . '</a> | ';
                echo '<a href="' . esc_url( $attestation_url ) . '" download>' . esc_html__('Télécharger', 'ufsc-clubs') . '</a>';
                echo '</p>';
                echo '</div>';
            }
            echo '<input type="file" name="attestation_ufsc_upload" accept=".pdf,.jpg,.jpeg,.png">';
            echo '<p class="description">' . esc_html__('Formats acceptés: PDF, JPG, PNG. Taille max: 5MB', 'ufsc-clubs') . '</p>';
            echo '</div>';
            
            echo '</div>';
        }

        // Add Documents panel for club editing
        if ( $id && ! $readonly ) {
            self::render_club_documents_panel( $id );

        }
        
        if ( !$readonly ) {
            echo '<p><button class="button button-primary">'.esc_html__('Enregistrer','ufsc-clubs').'</button> <a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs') ).'">'.esc_html__('Annuler','ufsc-clubs').'</a></p>';
            echo '</form>';
        } else {
            echo '<p><a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs') ).'">'.esc_html__('Retour à la liste','ufsc-clubs').'</a>';
            if ( current_user_can('manage_options') ) {
                echo ' <a class="button button-primary" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id='.$id) ).'">'.esc_html__('Modifier','ufsc-clubs').'</a>';
            }
            echo '</p>';
        }
    }

    private static function render_field_club($k,$conf,$val, $readonly = false){
        $label = $conf[0];
        $type  = $conf[1];
        $readonly_attr = $readonly ? 'readonly disabled' : '';
        $disabled_attr = $readonly ? 'disabled' : '';
        
        echo '<div class="ufsc-field"><label>'.esc_html($label).'</label>';
        if ( $type === 'textarea' ){
            echo '<textarea name="'.esc_attr($k).'" rows="3" '.$readonly_attr.'>'.esc_textarea($val).'</textarea>';
        } elseif ( $type === 'number' ){
            echo '<input type="number" step="1" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        } elseif ( $type === 'region' ){
            echo '<select name="'.esc_attr($k).'" '.$disabled_attr.'>';
            foreach( UFSC_CL_Utils::regions() as $r ){
                echo '<option value="'.esc_attr($r).'" '.selected($val,$r,false).'>'.esc_html($r).'</option>';
            }
            echo '</select>';
        } elseif ( in_array( $type, array( 'licence_status', 'club_status' ), true ) ) {
            $st = UFSC_SQL::statuses();
            echo '<select name="' . esc_attr( $k ) . '" ' . $disabled_attr . '>';
            foreach ( $st as $sv => $sl ) {
                echo '<option value="' . esc_attr( $sv ) . '" ' . selected( $val, $sv, false ) . '>' . esc_html( $sl ) . '</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        }
        echo '</div>';
    }

    /**
     * Render club documents panel for logo and attestation uploads
     */
    private static function render_club_documents_panel( $club_id ) {
        echo '<div class="ufsc-documents-panel" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
        echo '<h3>' . esc_html__( 'Documents du club', 'ufsc-clubs' ) . '</h3>';
        
        // Logo section
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4>' . esc_html__( 'Logo du club', 'ufsc-clubs' ) . '</h4>';
        
        $logo_id = get_option( 'ufsc_club_logo_' . $club_id );
        if ( $logo_id ) {
            $logo_url = wp_get_attachment_url( $logo_id );
            if ( $logo_url ) {
                echo '<div style="margin-bottom: 10px;">';
                echo '<img src="' . esc_url( $logo_url ) . '" style="max-width: 200px; max-height: 150px;" alt="Logo actuel">';
                echo '</div>';
                echo '<p>';
                echo '<a href="' . esc_url( $logo_url ) . '" target="_blank" class="button">' . esc_html__( 'Voir', 'ufsc-clubs' ) . '</a> ';
                echo '<a href="' . esc_url( $logo_url ) . '" download class="button">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a> ';
                echo '<label><input type="checkbox" name="remove_logo" value="1"> ' . esc_html__( 'Supprimer le logo actuel', 'ufsc-clubs' ) . '</label>';
                echo '</p>';
            }
        }
        
        echo '<p>';
        echo '<label for="club_logo_upload">' . esc_html__( 'Nouveau logo (JPG, PNG, GIF, max 2MB):', 'ufsc-clubs' ) . '</label><br>';
        echo '<input type="file" id="club_logo_upload" name="club_logo_upload" accept=".jpg,.jpeg,.png,.gif">';
        echo '</p>';
        echo '</div>';
        
        // Attestation UFSC section
        echo '<div style="margin-bottom: 20px;">';
        echo '<h4>' . esc_html__( 'Attestation UFSC', 'ufsc-clubs' ) . '</h4>';

        $attestation_id = get_option( 'ufsc_club_doc_attestation_ufsc_' . $club_id );
        if ( $attestation_id ) {
            $attestation_url = wp_get_attachment_url( $attestation_id );
            if ( $attestation_url ) {
                $file_name = basename( get_attached_file( $attestation_id ) );
                echo '<div style="margin-bottom: 10px;">';
                echo '<strong>' . esc_html__( 'Fichier actuel:', 'ufsc-clubs' ) . '</strong> ' . esc_html( $file_name );
                echo '</div>';
                echo '<p>';
                echo '<a href="' . esc_url( $attestation_url ) . '" target="_blank" class="button">' . esc_html__( 'Voir', 'ufsc-clubs' ) . '</a> ';
                echo '<a href="' . esc_url( $attestation_url ) . '" download class="button">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a> ';
                echo '<label><input type="checkbox" name="remove_attestation_ufsc" value="1"> ' . esc_html__( 'Supprimer l\'attestation actuelle', 'ufsc-clubs' ) . '</label>';
                echo '</p>';
            }
        }

        echo '<p>';
        echo '<label for="attestation_ufsc_upload">' . esc_html__( 'Nouvelle attestation (PDF, JPG, PNG, max 5MB):', 'ufsc-clubs' ) . '</label><br>';
        echo '<input type="file" id="attestation_ufsc_upload" name="attestation_ufsc_upload" accept=".pdf,.jpg,.jpeg,.png">';
        echo '</p>';
        echo '</div>';

        // Other club documents
        $documents = array(
            'doc_statuts' => __( 'Statuts', 'ufsc-clubs' ),
            'doc_recepisse' => __( 'Récépissé', 'ufsc-clubs' ),
            'doc_jo' => __( 'Journal Officiel', 'ufsc-clubs' ),
            'doc_pv_ag' => __( 'PV AG', 'ufsc-clubs' ),
            'doc_cer' => __( 'CER', 'ufsc-clubs' ),
            'doc_attestation_cer' => __( 'Attestation CER', 'ufsc-clubs' ),
        );

        $status_options = array(
            'pending' => __( 'En attente', 'ufsc-clubs' ),
            'approved' => __( 'Approuvé', 'ufsc-clubs' ),
            'rejected' => __( 'Rejeté', 'ufsc-clubs' ),
        );

        foreach ( $documents as $doc_key => $label ) {
            $url = get_post_meta( $club_id, $doc_key, true );
            $status = get_post_meta( $club_id, $doc_key . '_status', true );
            echo '<div style="margin-bottom: 20px;">';
            echo '<h4>' . esc_html( $label ) . '</h4>';
            if ( $url ) {
                echo '<p><a href="' . esc_url( $url ) . '" target="_blank" class="button">' . esc_html__( 'Télécharger', 'ufsc-clubs' ) . '</a></p>';
            } else {
                echo '<p>' . esc_html__( 'Aucun fichier.', 'ufsc-clubs' ) . '</p>';
            }
            echo '<p><label>' . esc_html__( 'Statut:', 'ufsc-clubs' ) . ' <select name="' . esc_attr( $doc_key . '_status' ) . '">';
            foreach ( $status_options as $value => $label_option ) {
                echo '<option value="' . esc_attr( $value ) . '" ' . selected( $status, $value, false ) . '>' . esc_html( $label_option ) . '</option>';
            }
            echo '</select></label></p>';
            echo '</div>';
        }

        echo '</div>';
    }

    public static function handle_save_club(){
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer('ufsc_sql_save_club');

        $user_id = get_current_user_id();
        $id      = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        // Permission check to ensure user can manage this club
        if ( ! current_user_can( 'manage_options' ) && ufsc_get_user_club_id( $user_id ) !== $id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            wp_safe_redirect( wp_get_referer() );
            exit; // Abort processing if user lacks rights
        }

        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_clubs'];
        $pk     = $s['pk_club'];
        $fields = UFSC_SQL::get_club_fields();

        $data = array();
        foreach ( $fields as $k => $conf ) {
            if ( 'statut' === $k ) {
                continue;
            }
            $data[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( $_POST[ $k ] ) : null;
        }

        $status_column    = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'statut' ) : 'statut';
        $allowed_statuses = array_keys( UFSC_SQL::statuses() );
        $submitted_status = isset( $_POST['statut'] ) ? sanitize_text_field( $_POST['statut'] ) : '';
        if ( ! in_array( $submitted_status, $allowed_statuses, true ) ) {
            $submitted_status = 'en_attente';
        }
        $data[ $status_column ] = $submitted_status;

        // Handle file uploads before validation
        $upload_errors = array();
        
        // Handle logo upload
        if ( !empty($_FILES['club_logo_upload']['name']) ) {
            $upload_result = self::handle_document_upload( $_FILES['club_logo_upload'], array('image/jpeg', 'image/jpg', 'image/png', 'image/svg+xml'), 2 * 1024 * 1024 ); // 2MB max
            if ( is_wp_error( $upload_result ) ) {
                $upload_errors[] = __('Logo: ', 'ufsc-clubs') . $upload_result->get_error_message();
            } else {
                // Save logo attachment ID
                update_option( 'ufsc_club_logo_' . ($id ?: 'new'), $upload_result );
            }
        }
        
        // Handle Attestation UFSC upload
        if ( !empty($_FILES['attestation_ufsc_upload']['name']) ) {
            $upload_result = self::handle_document_upload( $_FILES['attestation_ufsc_upload'], array('application/pdf', 'image/jpeg', 'image/jpg', 'image/png'), 5 * 1024 * 1024 ); // 5MB max
            if ( is_wp_error( $upload_result ) ) {
                $upload_errors[] = __('Attestation UFSC: ', 'ufsc-clubs') . $upload_result->get_error_message();
            } else {
                // Save attachment ID
                update_option( 'ufsc_club_doc_attestation_affiliation_' . ($id ?: 'new'), $upload_result );
            }
        }

        // Check for upload errors
        if ( !empty($upload_errors) ) {
            $error_message = implode(', ', $upload_errors);
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($error_message)) );
            exit;
        }

        // Validation des données
        $validation_errors = UFSC_CL_Utils::validate_club_data($data, false);
        if ( !empty($validation_errors) ) {
            UFSC_CL_Utils::log('Erreurs de validation club: ' . implode(', ', $validation_errors), 'warning');
            $error_message = implode(', ', $validation_errors);
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($error_message)) );
            exit;
        }

        try {
            if ( $id ){
                $result = $wpdb->update( $t, $data, array( $pk=>$id ) );
                if ( $result === false ) {
                    throw new Exception('Erreur lors de la mise à jour du club');
                }
                UFSC_CL_Utils::log('Club mis à jour: ID ' . $id, 'info');
                
                // Update option keys with real ID if we had uploads
                if ( get_option( 'ufsc_club_logo_new' ) ) {
                    $logo_id = get_option( 'ufsc_club_logo_new' );
                    update_option( 'ufsc_club_logo_' . $id, $logo_id );
                    delete_option( 'ufsc_club_logo_new' );
                }
                if ( get_option( 'ufsc_club_doc_attestation_affiliation_new' ) ) {
                    $doc_id = get_option( 'ufsc_club_doc_attestation_affiliation_new' );
                    update_option( 'ufsc_club_doc_attestation_affiliation_' . $id, $doc_id );
                    delete_option( 'ufsc_club_doc_attestation_affiliation_new' );
                }
            } else {
                $result = $wpdb->insert( $t, $data );
                if ( $result === false ) {
                    throw new Exception('Erreur lors de la création du club');
                }
                $id = (int) $wpdb->insert_id;
                UFSC_CL_Utils::log('Nouveau club créé: ID ' . $id, 'info');
                
                // Update option keys with real ID
                if ( get_option( 'ufsc_club_logo_new' ) ) {
                    $logo_id = get_option( 'ufsc_club_logo_new' );
                    update_option( 'ufsc_club_logo_' . $id, $logo_id );
                    delete_option( 'ufsc_club_logo_new' );
                }
                if ( get_option( 'ufsc_club_doc_attestation_affiliation_new' ) ) {
                    $doc_id = get_option( 'ufsc_club_doc_attestation_affiliation_new' );
                    update_option( 'ufsc_club_doc_attestation_affiliation_' . $id, $doc_id );
                    delete_option( 'ufsc_club_doc_attestation_affiliation_new' );
                }
            }
            
            // Handle file uploads after club is saved/updated
            if ( $id ) {
                self::handle_club_document_uploads( $id );

                // Update document statuses
                $documents = array( 'doc_statuts', 'doc_recepisse', 'doc_jo', 'doc_pv_ag', 'doc_cer', 'doc_attestation_cer' );
                foreach ( $documents as $doc_key ) {
                    if ( isset( $_POST[ $doc_key . '_status' ] ) ) {
                        $status = sanitize_text_field( $_POST[ $doc_key . '_status' ] );
                        update_post_meta( $id, $doc_key . '_status', $status );
                    }
                }
            }

            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id='.$id.'&updated=1') );
            exit;
        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur sauvegarde club: ' . $e->getMessage(), 'error');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($e->getMessage())) );
            exit;
        }
    }

    /**

     * Handle document upload with validation
     * 
     * @param array $file $_FILES array element
     * @param array $allowed_mime_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return int|WP_Error Attachment ID on success, WP_Error on failure
     */
    private static function handle_document_upload( $file, $allowed_mime_types, $max_size ) {
        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload_error', __( 'Erreur lors du téléchargement du fichier.', 'ufsc-clubs' ) );
        }
        
        // Check file size
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 'file_too_large', __( 'Le fichier est trop volumineux.', 'ufsc-clubs' ) );
        }
        
        // Check MIME type
        $file_type = wp_check_filetype( $file['name'] );
        if ( ! in_array( $file_type['type'], $allowed_mime_types ) ) {
            return new WP_Error( 'invalid_file_type', __( 'Type de fichier non autorisé.', 'ufsc-clubs' ) );
        }
        
        // Handle the upload
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        
        $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
        
        if ( isset( $upload['error'] ) ) {
            return new WP_Error( 'upload_failed', $upload['error'] );
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => $upload['type'],
            'post_title'     => sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
        
        if ( is_wp_error( $attachment_id ) ) {
            return $attachment_id;
        }
        
        // Generate attachment metadata
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attachment_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
        wp_update_attachment_metadata( $attachment_id, $attachment_data );
        
        return $attachment_id;
    }

    /**
     * Handle club document uploads (logo and attestation)
     */
    private static function handle_club_document_uploads( $club_id ) {
        // Load required WordPress upload functions
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        
        // Handle logo upload
        if ( ! empty( $_FILES['club_logo_upload']['name'] ) ) {
            $logo_mimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'gif' => 'image/gif',
                'png' => 'image/png'
            );
            
            $logo_upload = wp_handle_upload( $_FILES['club_logo_upload'], array(
                'test_form' => false,
                'mimes' => $logo_mimes
            ) );
            
            if ( ! isset( $logo_upload['error'] ) ) {
                // Create attachment
                $logo_attachment = array(
                    'post_mime_type' => $logo_upload['type'],
                    'post_title' => sanitize_file_name( $_FILES['club_logo_upload']['name'] ),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                
                $logo_attachment_id = wp_insert_attachment( $logo_attachment, $logo_upload['file'] );
                
                if ( $logo_attachment_id ) {
                    // Generate metadata
                    $logo_metadata = wp_generate_attachment_metadata( $logo_attachment_id, $logo_upload['file'] );
                    wp_update_attachment_metadata( $logo_attachment_id, $logo_metadata );
                    
                    // Remove old logo if exists
                    $old_logo_id = get_option( 'ufsc_club_logo_' . $club_id );
                    if ( $old_logo_id ) {
                        wp_delete_attachment( $old_logo_id, true );
                    }
                    
                    // Save new logo
                    update_option( 'ufsc_club_logo_' . $club_id, $logo_attachment_id );
                    UFSC_CL_Utils::log('Logo uploaded for club ID ' . $club_id, 'info');
                }
            } else {
                UFSC_CL_Utils::log('Logo upload error: ' . $logo_upload['error'], 'warning');
            }
        }
        
        // Handle logo removal
        if ( isset( $_POST['remove_logo'] ) && $_POST['remove_logo'] == '1' ) {
            $old_logo_id = get_option( 'ufsc_club_logo_' . $club_id );
            if ( $old_logo_id ) {
                wp_delete_attachment( $old_logo_id, true );
                delete_option( 'ufsc_club_logo_' . $club_id );
                UFSC_CL_Utils::log('Logo removed for club ID ' . $club_id, 'info');
            }
        }
        
        // Handle attestation UFSC upload
        if ( ! empty( $_FILES['attestation_ufsc_upload']['name'] ) ) {
            $doc_mimes = array(
                'pdf' => 'application/pdf',
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png' => 'image/png'
            );
            
            $doc_upload = wp_handle_upload( $_FILES['attestation_ufsc_upload'], array(
                'test_form' => false,
                'mimes' => $doc_mimes
            ) );
            
            if ( ! isset( $doc_upload['error'] ) ) {
                // Create attachment
                $doc_attachment = array(
                    'post_mime_type' => $doc_upload['type'],
                    'post_title' => sanitize_file_name( $_FILES['attestation_ufsc_upload']['name'] ),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                
                $doc_attachment_id = wp_insert_attachment( $doc_attachment, $doc_upload['file'] );
                
                if ( $doc_attachment_id ) {
                    // Remove old attestation if exists
                    $old_doc_id = get_option( 'ufsc_club_doc_attestation_ufsc_' . $club_id );
                    if ( $old_doc_id ) {
                        wp_delete_attachment( $old_doc_id, true );
                    }
                    
                    // Save new attestation
                    update_option( 'ufsc_club_doc_attestation_ufsc_' . $club_id, $doc_attachment_id );
                    UFSC_CL_Utils::log('Attestation UFSC uploaded for club ID ' . $club_id, 'info');
                }
            } else {
                UFSC_CL_Utils::log('Attestation upload error: ' . $doc_upload['error'], 'warning');
            }
        }
        
        // Handle attestation removal
        if ( isset( $_POST['remove_attestation_ufsc'] ) && $_POST['remove_attestation_ufsc'] == '1' ) {
            $old_doc_id = get_option( 'ufsc_club_doc_attestation_ufsc_' . $club_id );
            if ( $old_doc_id ) {
                wp_delete_attachment( $old_doc_id, true );
                delete_option( 'ufsc_club_doc_attestation_ufsc_' . $club_id );
                UFSC_CL_Utils::log('Attestation UFSC removed for club ID ' . $club_id, 'info');
            }
        }

    }

    public static function handle_delete_club(){
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé');
        check_admin_referer('ufsc_sql_delete_club');

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ( $id ){
            $result = $wpdb->delete( $t, array( $pk=>$id ) );
            if ( $result !== false ) {
                UFSC_CL_Utils::log('Club supprimé: ID ' . $id, 'info');
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&deleted=1&deleted_id='.$id) );
            } else {
                UFSC_CL_Utils::log('Erreur suppression club: ID ' . $id, 'error');
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&error='.urlencode(__('Erreur lors de la suppression du club','ufsc-clubs'))) );
            }
        } else {
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&error='.urlencode(__('ID de club invalide','ufsc-clubs'))) );
        }
        exit;
    }

    /* ---------------- Licences ---------------- */
    public static function render_licences(){
        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $licences_table  = $s['table_licences'];
        $clubs_table = $s['table_clubs'];
        $pk = $s['pk_licence'];

        // Handle search and filters
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
        $filter_region = isset($_GET['filter_region']) ? sanitize_text_field($_GET['filter_region']) : '';
        $filter_club = isset($_GET['filter_club']) ? intval($_GET['filter_club']) : 0;
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        
        // Pagination
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;

        // Build WHERE conditions
        $where_conditions = array();
        
        if (!empty($search)) {
            $where_conditions[] = $wpdb->prepare(
                "(l.nom LIKE %s OR l.prenom LIKE %s OR l.email LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        if (!empty($filter_region)) {
            $where_conditions[] = $wpdb->prepare("l.region = %s", $filter_region);
        }
        
        if (!empty($filter_club)) {
            $where_conditions[] = $wpdb->prepare("l.club_id = %d", $filter_club);
        }
        
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("l.statut = %s", $filter_status);
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM `{$licences_table}` l LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id {$where_clause}";
        $total_items = $wpdb->get_var($total_query);
        $total_pages = ceil($total_items / $per_page);

        // Get data with JOIN to show club names
        $query = "SELECT l.{$pk}, l.prenom, l.nom, l.date_naissance, l.club_id, l.region, l.statut, l.date_inscription, 
                         c.nom AS club_nom
                  FROM `{$licences_table}` l 
                  LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id 
                  {$where_clause} 
                  ORDER BY l.{$pk} DESC 
                  LIMIT {$per_page} OFFSET {$offset}";
        
        $rows = $wpdb->get_results($query);

        echo '<div class="wrap"><h1>'.esc_html__('Licences (SQL)','ufsc-clubs').'</h1>';
        
        // Affichage des notices
        if ( isset($_GET['updated']) && $_GET['updated'] == '1' ) {
            echo UFSC_CL_Utils::show_success(__('Licence enregistrée avec succès', 'ufsc-clubs'));
        }
        if ( isset($_GET['deleted']) && $_GET['deleted'] == '1' ) {
            $deleted_id = isset($_GET['deleted_id']) ? (int) $_GET['deleted_id'] : '';
            echo UFSC_CL_Utils::show_success(__('La licence #'.$deleted_id.' a été supprimée.', 'ufsc-clubs'));
        }
        if ( isset($_GET['error']) ) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }
        
        // Add nonce for AJAX operations
        echo '<input type="hidden" id="ufsc-ajax-nonce" value="' . wp_create_nonce('ufsc_ajax_nonce') . '" />';
        
        echo '<p><a href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences&action=new') ).'" class="button button-primary">'.esc_html__('Ajouter une licence','ufsc-clubs').'</a> ';
        echo '<a href="'.esc_url( admin_url('admin.php?page=ufsc-exports') ).'" class="button">'.esc_html__('Exporter','ufsc-clubs').'</a></p>';

        if ( isset($_GET['action']) && $_GET['action']==='edit' ){
            $id = (int) $_GET['id'];
            self::render_licence_form($id);
            echo '</div>';
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='view' ){
            $id = (int) $_GET['id'];
            self::render_licence_form($id, true); // true = readonly mode
            echo '</div>';
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='new' ){
            self::render_licence_form(0);
            echo '</div>';
            return;
        }

        // Search and Filters
        echo '<div class="ufsc-list-filters" style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-radius: 5px;">';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-licences" />';
        
        echo '<div style="display: grid; grid-template-columns: 1fr 200px 200px 150px auto; gap: 10px; align-items: end;">';
        
        // Search
        echo '<div>';
        echo '<label for="search"><strong>'.esc_html__('Recherche', 'ufsc-clubs').'</strong></label>';
        echo '<input type="text" name="search" id="search" value="'.esc_attr($search).'" placeholder="'.esc_attr__('Nom, prénom, email...', 'ufsc-clubs').'" />';
        echo '</div>';
        
        // Region filter
        echo '<div>';
        echo '<label for="filter_region"><strong>'.esc_html__('Région', 'ufsc-clubs').'</strong></label>';
        echo '<select name="filter_region" id="filter_region">';
        echo '<option value="">'.esc_html__('Toutes', 'ufsc-clubs').'</option>';
        foreach (UFSC_CL_Utils::regions() as $region) {
            echo '<option value="'.esc_attr($region).'" '.selected($filter_region, $region, false).'>'.esc_html($region).'</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Club filter
        echo '<div>';
        echo '<label for="filter_club"><strong>'.esc_html__('Club', 'ufsc-clubs').'</strong></label>';
        echo '<select name="filter_club" id="filter_club">';
        echo '<option value="">'.esc_html__('Tous', 'ufsc-clubs').'</option>';
        $clubs = $wpdb->get_results("SELECT id, nom FROM `{$clubs_table}` ORDER BY nom");
        foreach ($clubs as $club) {
            echo '<option value="'.esc_attr($club->id).'" '.selected($filter_club, $club->id, false).'>'.esc_html($club->nom).'</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Status filter
        echo '<div>';
        echo '<label for="filter_status"><strong>'.esc_html__('Statut', 'ufsc-clubs').'</strong></label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="">'.esc_html__('Tous', 'ufsc-clubs').'</option>';
        foreach (UFSC_SQL::statuses() as $status_key => $status_label) {
            echo '<option value="'.esc_attr($status_key).'" '.selected($filter_status, $status_key, false).'>'.esc_html($status_label).'</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Filter button
        echo '<div>';
        echo '<button type="submit" class="button">'.esc_html__('Filtrer', 'ufsc-clubs').'</button>';
        if (!empty($search) || !empty($filter_region) || !empty($filter_club) || !empty($filter_status)) {
            echo ' <a href="'.admin_url('admin.php?page=ufsc-sql-licences').'" class="button">'.esc_html__('Effacer', 'ufsc-clubs').'</a>';
        }
        echo '</div>';
        
        echo '</div>';
        echo '</form>';
        echo '</div>';

        // Bulk actions
        echo '<div class="ufsc-bulk-actions" style="margin: 15px 0;">';
        echo '<form method="post" id="bulk-actions-form">';
        wp_nonce_field('ufsc_bulk_actions');
        echo '<select name="bulk_action" id="bulk-action-selector">';
        echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
        echo '<option value="validate">'.esc_html__('Valider', 'ufsc-clubs').'</option>';
        echo '<option value="reject">'.esc_html__('Refuser', 'ufsc-clubs').'</option>';
        echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
        echo '</select>';
        echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';
        echo ' <button type="button" class="button ufsc-send-to-payment">'.esc_html__('Envoyer au paiement', 'ufsc-clubs').'</button>';
        echo '</form>';
        echo '</div>';

        // Results info
        echo '<div class="ufsc-results-info" style="margin: 10px 0;">';
        echo '<p>'.sprintf(esc_html__('%d licence(s) trouvée(s)', 'ufsc-clubs'), $total_items);
        if (!empty($search) || !empty($filter_region) || !empty($filter_club) || !empty($filter_status)) {
            echo ' '.esc_html__('(filtré)', 'ufsc-clubs');
        }
        echo '</p>';
        echo '</div>';

        // Table
        echo '<table class="wp-list-table widefat fixed striped ufsc-enhanced">';
        echo '<thead><tr>';
        echo '<td class="check-column"><input type="checkbox" id="select-all-licences" /></td>';
        echo '<th class="column-id">'.esc_html__('ID','ufsc-clubs').'</th>';
        echo '<th>'.esc_html__('Licencié','ufsc-clubs').'</th>';
        echo '<th class="column-date">'.esc_html__('Naissance','ufsc-clubs').'</th>';
        echo '<th>'.esc_html__('Club','ufsc-clubs').'</th>';
        echo '<th class="column-region">'.esc_html__('Région','ufsc-clubs').'</th>';
        echo '<th class="column-statut">'.esc_html__('Statut','ufsc-clubs').'</th>';
        echo '<th class="column-date">'.esc_html__('Date création','ufsc-clubs').'</th>';
        echo '<th class="column-actions">'.esc_html__('Actions','ufsc-clubs').'</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        if ( $rows ){
            foreach($rows as $r){
                $map = array('valide'=>'success','a_regler'=>'info','desactive'=>'off','en_attente'=>'wait');
                $cls = isset($map[$r->statut]) ? $map[$r->statut] : 'info';
                $status_label = UFSC_SQL::statuses()[$r->statut] ?? $r->statut;
                $badge = UFSC_CL_Utils::esc_badge( $status_label, $cls );
                
                $view_url = admin_url('admin.php?page=ufsc-sql-licences&action=view&id='.$r->$pk);
                $edit_url = admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$r->$pk);
                $del_url  = wp_nonce_url( admin_url('admin-post.php?action=ufsc_sql_delete_licence&id='.$r->$pk), 'ufsc_sql_delete_licence' );
                $name = trim($r->prenom.' '.$r->nom);
                $club_display = $r->club_nom ? esc_html($r->club_nom) : esc_html__('Club #', 'ufsc-clubs') . $r->club_id;
                
                echo '<tr>';
                echo '<th class="check-column"><input type="checkbox" name="licence_ids[]" value="'.(int)$r->$pk.'" /></th>';
                echo '<td>'.(int)$r->$pk.'</td>';
                echo '<td><strong>'.esc_html($name).'</strong></td>';
                echo '<td>'.esc_html($r->date_naissance).'</td>';
                echo '<td>'.$club_display.'</td>';
                echo '<td>'.esc_html($r->region).'</td>';
                echo '<td>';
                
                // Display status badge with colored dot
                echo self::get_status_badge($r->statut);
                
                echo '</td>';
                echo '<td>'.esc_html($r->date_inscription ?: '').'</td>';
                echo '<td class="column-actions">';
                echo '<div class="ufsc-button-group">';
                echo '<a class="button button-small" href="'.esc_url($view_url).'" title="'.esc_attr__('Consulter la licence','ufsc-clubs').'" aria-label="'.esc_attr__('Consulter la licence','ufsc-clubs').'">'.esc_html__('Consulter','ufsc-clubs').'</a>';
                echo '<a class="button button-small" href="'.esc_url($edit_url).'" title="'.esc_attr__('Éditer la licence','ufsc-clubs').'" aria-label="'.esc_attr__('Éditer la licence','ufsc-clubs').'">'.esc_html__('Éditer','ufsc-clubs').'</a>';
                // Add payment button for valid licenses
                if (in_array($r->statut, array('valide', 'validee', 'active'))) {
                    $payment_url = wp_nonce_url( admin_url('admin-post.php?action=ufsc_send_license_payment&license_id='.$r->$pk), 'ufsc_send_license_payment_'.$r->$pk );
                    echo '<a class="button button-small" href="'.esc_url($payment_url).'" title="'.esc_attr__('Envoyer pour paiement','ufsc-clubs').'" aria-label="'.esc_attr__('Envoyer pour paiement','ufsc-clubs').'" style="background: #00a32a; border-color: #00a32a; color: white;">'.esc_html__('Paiement','ufsc-clubs').'</a>';
                }
                echo '<a class="button button-small button-link-delete" href="'.esc_url($del_url).'" title="'.esc_attr__('Supprimer la licence','ufsc-clubs').'" aria-label="'.esc_attr__('Supprimer la licence','ufsc-clubs').'" onclick="return confirm(\''.esc_js(__('Êtes-vous sûr de vouloir supprimer cette licence ?','ufsc-clubs')).'\')">'.esc_html__('Supprimer','ufsc-clubs').'</a>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9">'.esc_html__('Aucune licence trouvée','ufsc-clubs').'</td></tr>';
        }
        echo '</tbody></table>';

        // Pagination
        if ($total_pages > 1) {
            echo '<div class="ufsc-pagination" style="margin: 20px 0; text-align: center;">';
            
            $pagination_base = admin_url('admin.php?page=ufsc-sql-licences');
            if (!empty($search)) $pagination_base .= '&search=' . urlencode($search);
            if (!empty($filter_region)) $pagination_base .= '&filter_region=' . urlencode($filter_region);
            if (!empty($filter_club)) $pagination_base .= '&filter_club=' . $filter_club;
            if (!empty($filter_status)) $pagination_base .= '&filter_status=' . urlencode($filter_status);
            
            // Previous page
            if ($page > 1) {
                echo '<a href="'.esc_url($pagination_base . '&paged=' . ($page - 1)).'" class="button">« '.esc_html__('Précédent', 'ufsc-clubs').'</a> ';
            }
            
            // Page numbers
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $page) {
                    echo '<span class="button button-primary">'.$i.'</span> ';
                } else {
                    echo '<a href="'.esc_url($pagination_base . '&paged=' . $i).'" class="button">'.$i.'</a> ';
                }
            }
            
            // Next page
            if ($page < $total_pages) {
                echo '<a href="'.esc_url($pagination_base . '&paged=' . ($page + 1)).'" class="button">'.esc_html__('Suivant', 'ufsc-clubs').' »</a>';
            }
            
            echo '<p style="margin-top: 10px;">'.sprintf(esc_html__('Page %d sur %d', 'ufsc-clubs'), $page, $total_pages).'</p>';
            echo '</div>';
        }

        echo '</div>';
    }

    private static function csv_licences($rows){
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="licences_sql.csv"');
        $out = fopen('php://output','w');
        fputcsv($out, array('id','prenom','nom','date_naissance','club_id','region','statut'));
        if ( $rows ){
            forEach($rows as $r){
                fputcsv($out, array($r->id,$r->prenom,$r->nom,$r->date_naissance,$r->club_id,$r->region,$r->statut));
            }
        }
        fclose($out);
    }

    private static function render_licence_form( $id, $readonly = false ){
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_licences'];
        $pk = $s['pk_licence'];
        $fields = UFSC_SQL::get_licence_fields();
        $row = $id ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id) ) : null;
        $current_club_id = $row ? (int) $row->club_id : 0;

        if ( $readonly ) {
            echo '<h1>'.( $id ? esc_html__('Consulter la licence','ufsc-clubs') : esc_html__('Nouvelle licence','ufsc-clubs') ).'</h1>';
        } else {
            echo '<h1>'.( $id ? esc_html__('Éditer la licence','ufsc-clubs') : esc_html__('Ajouter une nouvelle licence','ufsc-clubs') ).'</h1>';
            if (!$id) {
                echo '<div class="ufsc-form-intro" style="background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1; border-radius: 4px;">';
                echo '<p><strong>'.esc_html__('Instructions pour l\'ajout d\'une licence','ufsc-clubs').'</strong></p>';
                echo '<p>'.esc_html__('Veuillez remplir tous les champs obligatoires marqués d\'un astérisque (*). Les informations saisies seront vérifiées avant validation.','ufsc-clubs').'</p>';
                echo '<ul style="margin: 10px 0 0 20px;">';
                echo '<li>'.esc_html__('Email: utilisé pour l\'envoi des notifications et du lien de paiement','ufsc-clubs').'</li>';
                echo '<li>'.esc_html__('Téléphone: format accepté avec ou sans espaces/tirets','ufsc-clubs').'</li>';
                echo '<li>'.esc_html__('Date de naissance: format JJ/MM/AAAA','ufsc-clubs').'</li>';
                echo '</ul>';
                echo '</div>';
            }
        }
        
        // Affichage des messages
        if ( isset($_GET['updated']) && $_GET['updated'] == '1' ) {
            echo UFSC_CL_Utils::show_success(__('Licence enregistrée avec succès', 'ufsc-clubs'));
        }
        if ( isset($_GET['payment_sent']) && $_GET['payment_sent'] == '1' ) {
            $order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : '';
            $message = __('La licence a été enregistrée et envoyée pour paiement.', 'ufsc-clubs');
            if ($order_id) {
                $message .= ' ' . sprintf(__('Commande #%d créée.', 'ufsc-clubs'), $order_id);
            }
            echo UFSC_CL_Utils::show_success($message);
        }
        if ( isset($_GET['error']) ) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }
        
        if ( !$readonly ) {
            echo '<form method="post" enctype="multipart/form-data">';
            wp_nonce_field('ufsc_sql_save_licence');
            echo '<input type="hidden" name="action" value="ufsc_sql_save_licence" />';
            echo '<input type="hidden" name="id" value="'.(int)$id.'" />';
            echo '<input type="hidden" name="page" value="ufsc-sql-licences"/>';
        }

        echo '<div class="ufsc-grid">';
        foreach ( $fields as $k=>$conf ){
            $val = $row ? ( isset($row->$k) ? $row->$k : '' ) : '';
            self::render_field_licence( $k, $conf, $val, $readonly, $current_club_id );
        }
        echo '</div>';
        
        if ( !$readonly ) {
            echo '<div class="ufsc-form-actions" style="background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 4px;">';
            echo '<div class="ufsc-button-group">';
            echo '<button type="submit" name="save_action" value="save" class="button button-primary">'.esc_html__('Enregistrer','ufsc-clubs').'</button>';
            if ($id) {
                // Only show payment button for existing licenses
                echo '<button type="submit" name="save_action" value="save_and_payment" class="button button-secondary" style="background: #00a32a; border-color: #00a32a; color: white;">'.esc_html__('Enregistrer et envoyer pour paiement','ufsc-clubs').'</button>';
            }
            echo '<a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences') ).'">'.esc_html__('Annuler','ufsc-clubs').'</a>';
            echo '</div>';
            if (!$id) {
                echo '<p class="description" style="margin-top: 10px;">'.esc_html__('Note: Le bouton "Envoyer pour paiement" sera disponible après le premier enregistrement.','ufsc-clubs').'</p>';
            }
            echo '</div>';
            echo '</form>';
        } else {
            echo '<p><a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences') ).'">'.esc_html__('Retour à la liste','ufsc-clubs').'</a>';
            if ( current_user_can('manage_options') ) {
                echo ' <a class="button button-primary" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$id) ).'">'.esc_html__('Modifier','ufsc-clubs').'</a>';
            }
            echo '</p>';
        }
    }

    private static function render_field_licence( $k, $conf, $val, $readonly = false, $club_id = 0 ){
        $label = $conf[0];
        $type  = $conf[1];
        $readonly_attr = $readonly ? 'readonly disabled' : '';
        $disabled_attr = $readonly ? 'disabled' : '';
        if ( $readonly && current_user_can( 'manage_options' ) ) {
            $readonly_attr = '';
            $disabled_attr = '';
        }
        
        echo '<div class="ufsc-field"><label>'.esc_html($label).'</label>';
        
        if ( $k === 'club_id' ){
            // Special handling for club selector
            global $wpdb;
            $s = UFSC_SQL::get_settings();
            $clubs_table = $s['table_clubs'];
            
            $selected_club = null;
            $selected_region = '';
            
            if ( $val ) {
                $selected_club = $wpdb->get_row( $wpdb->prepare("SELECT id, nom, region FROM `$clubs_table` WHERE id = %d", $val) );
                if ( $selected_club ) {
                    $selected_region = $selected_club->region;
                }
            }
            
            echo '<select name="'.esc_attr($k).'" id="ufsc-club-selector" class="ufsc-club-selector" data-current-region="'.esc_attr($selected_region).'" '.$disabled_attr.'>';
            echo '<option value="">'.esc_html__('Sélectionner un club...', 'ufsc-clubs').'</option>';
            
            // Get all clubs for the dropdown
            $clubs = $wpdb->get_results("SELECT id, nom, region FROM `$clubs_table` ORDER BY nom");
            foreach( $clubs as $club ){
                echo '<option value="'.esc_attr($club->id).'" '.selected($val,$club->id,false).' data-region="'.esc_attr($club->region).'">'.esc_html($club->nom.' — '.$club->region).'</option>';
            }
            echo '</select>';
            
            // Auto-populated region field (read-only)
            if ( !$readonly ) {
                echo '<p class="description">'.esc_html__('La région sera automatiquement remplie selon le club sélectionné.', 'ufsc-clubs').'</p>';
            }
            
        } elseif ( $k === 'region' ){
            // Make region read-only when displayed after club_id
            echo '<input type="text" name="'.esc_attr($k).'" id="ufsc-auto-region" value="'.esc_attr($val).'" readonly class="ufsc-readonly-field" '.$disabled_attr.' />';
            if ( !$readonly ) {
                echo '<p class="description">'.esc_html__('Ce champ est automatiquement rempli selon le club sélectionné.', 'ufsc-clubs').'</p>';
            }
            
        } elseif ( $type === 'textarea' ){
            echo '<textarea name="'.esc_attr($k).'" rows="3" '.$readonly_attr.'>'.esc_textarea($val).'</textarea>';
        } elseif ( $type === 'number' ){
            echo '<input type="number" step="1" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        } elseif ( $type === 'region' ){
            echo '<select name="'.esc_attr($k).'" '.$disabled_attr.'>';
            foreach( UFSC_CL_Utils::regions() as $r ){
                echo '<option value="'.esc_attr($r).'" '.selected($val,$r,false).'>'.esc_html($r).'</option>';
            }
            echo '</select>';
        } elseif ( $type === 'bool' ){
            echo '<select name="'.esc_attr($k).'" '.$disabled_attr.'><option value="0" '.selected($val,'0',false).'>Non</option><option value="1" '.selected($val,'1',false).'>Oui</option></select>';
            if ( 'is_included' === $k && $club_id ) {
                global $wpdb;
                $settings    = UFSC_SQL::get_settings();
                $clubs_table = $settings['table_clubs'];
                $quota_col   = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'quota_licences' ) : 'quota_licences';
                $included    = UFSC_SQL::count_included_licences( $club_id );
                $quota_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT {$quota_col} FROM `{$clubs_table}` WHERE id = %d", $club_id ) );
                echo '<span class="description"> ' . esc_html( $included . ' / ' . $quota_total ) . '</span>';
            }
        } elseif ( $type === 'sex' ){
            if ( $readonly ) {
                echo '<span>'.esc_html($val === 'M' ? 'M' : ($val === 'F' ? 'F' : '')).'</span>';
            } else {
                echo '<label><input type="radio" name="'.esc_attr($k).'" value="M" '.checked($val,'M',false).'/> M</label> <label style="margin-left:10px"><input type="radio" name="'.esc_attr($k).'" value="F" '.checked($val,'F',false).'/> F</label>';
            }
        } elseif ( $type === 'licence_status' ){
            $st = UFSC_SQL::statuses();
            echo '<select name="'.esc_attr($k).'" '.$disabled_attr.'>';
            foreach( $st as $sv=>$sl ){
                echo '<option value="'.esc_attr($sv).'" '.selected($val,$sv,false).'>'.esc_html($sl).'</option>';
            }
            echo '</select>';
        } elseif ( $k === 'certificat_url' ){
            echo '<input type="url" name="certificat_url" value="'.esc_attr($val).'" placeholder="https://..." '.$readonly_attr.'/>';
            if ( !$readonly ) {
                echo '<p class="description">Uploader un fichier ci-dessous alimentera ce champ.</p><input type="file" name="certificat_upload" accept=".jpg,.jpeg,.png,.pdf" />';
            } else if ( $val ) {
                echo '<p class="description"><a href="'.esc_url($val).'" target="_blank">'.esc_html__('Voir le certificat', 'ufsc-clubs').'</a></p>';
            }
        } elseif ( $k === 'email' ){
            echo '<input type="email" name="'.esc_attr($k).'" value="'.esc_attr($val).'" placeholder="'.esc_attr__('exemple@email.com','ufsc-clubs').'" required '.$readonly_attr.' />';
        } elseif ( $k === 'telephone' || $k === 'tel' ){
            echo '<input type="tel" name="'.esc_attr($k).'" value="'.esc_attr($val).'" placeholder="'.esc_attr__('01 23 45 67 89','ufsc-clubs').'" '.$readonly_attr.' />';
        } elseif ( $k === 'date_naissance' || strpos($k, 'date_') === 0 ){
            echo '<input type="date" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        } elseif ( $k === 'prenom' ){
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" placeholder="'.esc_attr__('Prénom','ufsc-clubs').'" required '.$readonly_attr.' />';
        } elseif ( $k === 'nom' ){
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" placeholder="'.esc_attr__('Nom de famille','ufsc-clubs').'" required '.$readonly_attr.' />';
        } else {
            // Default text input
            $placeholder = '';
            if ($k === 'adresse') $placeholder = __('Adresse complète','ufsc-clubs');
            elseif ($k === 'code_postal') $placeholder = __('12345','ufsc-clubs');
            elseif ($k === 'ville') $placeholder = __('Ville','ufsc-clubs');
            
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.($placeholder ? 'placeholder="'.esc_attr($placeholder).'"' : '').' '.$readonly_attr.' />';
        }
        echo '</div>';
    }

    public static function handle_save_licence(){
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer('ufsc_sql_save_licence');

        $user_id   = get_current_user_id();
        $club_id   = isset( $_POST['club_id'] ) ? (int) $_POST['club_id'] : 0;

        // Ensure the current user has rights to manage the targeted club
        if ( ! current_user_can( 'manage_options' ) && ufsc_get_user_club_id( $user_id ) !== $club_id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            wp_safe_redirect( wp_get_referer() );
            exit; // Abort processing on permission failure
        }

        global $wpdb;
        $s      = UFSC_SQL::get_settings();
        $t      = $s['table_licences'];
        $pk     = $s['pk_licence'];
        $fields = UFSC_SQL::get_licence_fields();
        $id     = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $data = array();
        foreach( $fields as $k=>$conf ){
            if ( $k === 'certificat_url' ) continue;
            $type = $conf[1];
            if ( $type === 'bool' ){
                $data[$k] = isset($_POST[$k]) ? ( $_POST[$k] == '1' ? 1 : 0 ) : 0;
            } elseif ( $type === 'sex' ){
                $data[$k] = in_array( $_POST[$k] ?? 'M', array('M','F'), true ) ? $_POST[$k] : 'M';
            } else {
                $data[$k] = isset($_POST[$k]) ? sanitize_text_field($_POST[$k]) : null;
            }
        }

        $valid_statuses = array_keys( UFSC_SQL::statuses() );
        if ( empty( $data['statut'] ) || ! in_array( $data['statut'], $valid_statuses, true ) ){
            $data['statut'] = 'en_attente';
        }

        // Validate included quota if checkbox is set
        if ( ! empty( $data['is_included'] ) ) {
            $current_included = UFSC_SQL::count_included_licences( $club_id );
            $clubs_table = $s['table_clubs'];
            $quota_col   = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'quota_licences' ) : 'quota_licences';
            $quota_total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT {$quota_col} FROM `{$clubs_table}` WHERE id = %d", $club_id ) );
            if ( $quota_total > 0 ) {
                // exclude current licence if already included
                if ( $id ) {
                    $was_included = (int) $wpdb->get_var( $wpdb->prepare( "SELECT is_included FROM `{$t}` WHERE `{$pk}` = %d", $id ) );
                    if ( $was_included ) {
                        $current_included--;
                    }
                }
                if ( $current_included >= $quota_total ) {
                    $error_message = __( 'Quota de licences incluses atteint', 'ufsc-clubs' );
                    wp_safe_redirect( admin_url( 'admin.php?page=ufsc-sql-licences&action=' . ( $id ? 'edit&id=' . $id : 'new' ) . '&error=' . urlencode( $error_message ) ) );
                    exit;
                }
            }
        }

        // Validation des données
        $validation_errors = UFSC_CL_Utils::validate_licence_data($data);
        if ( !empty($validation_errors) ) {
            UFSC_CL_Utils::log('Erreurs de validation licence: ' . implode(', ', $validation_errors), 'warning');
            $error_message = implode(', ', $validation_errors);
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($error_message)) );
            exit;
        }

        // Gestion upload certificat
        if ( ! empty($_FILES['certificat_upload']['name']) ){
            require_once ABSPATH.'wp-admin/includes/file.php';
            $upload = wp_handle_upload( $_FILES['certificat_upload'], array('test_form'=>false) );
            if ( ! empty($upload['url']) ){
                $data['certificat_url'] = esc_url_raw( $upload['url'] );
            } elseif ( ! empty($upload['error']) ) {
                UFSC_CL_Utils::log('Erreur upload certificat: ' . $upload['error'], 'warning');
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode('Erreur upload fichier: '.$upload['error'])) );
                exit;
            }
        } else {
            $data['certificat_url'] = isset($_POST['certificat_url']) ? esc_url_raw($_POST['certificat_url']) : '';
        }

        try {
            if ( $id ){
                $result = $wpdb->update( $t, $data, array( $pk=>$id ) );
                if ( $result === false ) {
                    throw new Exception('Erreur lors de la mise à jour de la licence');
                }
                UFSC_CL_Utils::log('Licence mise à jour: ID ' . $id, 'info');
            } else {
                $result = $wpdb->insert( $t, $data );
                if ( $result === false ) {
                    throw new Exception('Erreur lors de la création de la licence');
                }
                $id = (int) $wpdb->insert_id;
                UFSC_CL_Utils::log('Nouvelle licence créée: ID ' . $id, 'info');
            }

            // Check if we should also send to payment
            $save_action = isset($_POST['save_action']) ? sanitize_text_field($_POST['save_action']) : 'save';
            if ($save_action === 'save_and_payment' && $id) {
                // Redirect to payment handler
                wp_safe_redirect( admin_url('admin-post.php?action=ufsc_send_license_payment&license_id='.$id.'&_wpnonce='.wp_create_nonce('ufsc_send_license_payment_'.$id)) );
            } else {
                // Normal save redirect
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$id.'&updated=1') );
            }
            exit;
        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur sauvegarde licence: ' . $e->getMessage(), 'error');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($e->getMessage())) );
            exit;
        }
    }

    /**
     * Handle sending license to payment
     */
    public static function handle_send_license_payment(){
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé');

        $license_id = isset($_GET['license_id']) ? (int) $_GET['license_id'] : 0;
        check_admin_referer('ufsc_send_license_payment_'.$license_id);

        if (!$license_id) {
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&error='.urlencode(__('ID de licence invalide','ufsc-clubs'))) );
            exit;
        }

        // Create WooCommerce order for license payment
        $order_id = self::create_order_for_license($license_id);
        
        if ($order_id) {
            UFSC_CL_Utils::log('Commande créée pour licence ID ' . $license_id . ': Order ID ' . $order_id, 'info');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$license_id.'&payment_sent=1&order_id='.$order_id) );
        } else {
            UFSC_CL_Utils::log('Erreur création commande pour licence ID ' . $license_id, 'error');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$license_id.'&error='.urlencode(__('Erreur lors de la création de la commande de paiement','ufsc-clubs'))) );
        }
        exit;
    }

    /**
     * Create WooCommerce order for license payment
     */
    private static function create_order_for_license($license_id) {
        // Check if WooCommerce is active
        if (!function_exists('wc_create_order')) {
            return false;
        }

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_licences'];
        $pk = $s['pk_licence'];

        // Get license data
        $license = $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $license_id) );
        if (!$license || empty($license->email)) {
            return false;
        }

        try {
            // Calculate license price using configurable rules
            $price = self::calculate_license_price($license);
            
            // Find or create user by email
            $user = get_user_by('email', $license->email);
            if (!$user) {
                // Create user if not exists
                $user_id = wp_create_user($license->email, wp_generate_password(), $license->email);
                if (is_wp_error($user_id)) {
                    return false;
                }
                $user = get_user_by('id', $user_id);
            }

            // Find or create product
            $product_id = self::get_or_create_license_product();
            if (!$product_id) {
                return false;
            }

            // Create order
            $order = wc_create_order();
            $order->set_customer_id($user->ID);
            $order->set_billing_email($license->email);
            
            // Set billing info from license data
            if (!empty($license->prenom) && !empty($license->nom)) {
                $order->set_billing_first_name($license->prenom);
                $order->set_billing_last_name($license->nom);
            }

            // Add product to order with calculated price
            $product = wc_get_product($product_id);
            $product->set_price($price);
            $order->add_product($product, 1);

            // Add order meta
            $order->add_meta_data('_ufsc_license_id', $license_id);
            $order->add_meta_data('_ufsc_license_type', 'individual');

            // Calculate totals
            $order->calculate_totals();

            // Set status to pending payment
            $order->update_status('pending', __('Commande créée pour licence UFSC', 'ufsc-clubs'));

            // Save order
            $order->save();

            // Send invoice email
            if (class_exists('WC_Email_Customer_Invoice')) {
                $mailer = WC()->mailer();
                $emails = $mailer->get_emails();
                if (isset($emails['WC_Email_Customer_Invoice'])) {
                    $emails['WC_Email_Customer_Invoice']->trigger($order->get_id());
                }
            }

            return $order->get_id();

        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur création commande WooCommerce: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Calculate license price based on type, region, quota and discounts.
     *
     * Rules can be customized via the `ufsc_license_pricing_rules` filter or
     * by storing an array in the `ufsc_license_pricing_rules` option. The
     * computed price can be further adjusted with the `ufsc_license_price`
     * filter.
     *
     * @param object $license Licence data object.
     * @return float Calculated licence price.
     */
    private static function calculate_license_price( $license ) {
        // Default pricing configuration
        $default_rules = array(
            'base_price'        => 50.00,
            'type_prices'       => array(
                'standard'    => 50.00,
                'benevole'    => 40.00,
                'postier'     => 45.00,
                'competition' => 60.00,
            ),
            'region_adjustments' => array(),
            'quota_surcharge'  => 0.00,
            'discounts'        => array(),
        );

        // Allow configuration through options or filters
        $rules = get_option( 'ufsc_license_pricing_rules', array() );
        $rules = apply_filters( 'ufsc_license_pricing_rules', $rules, $license );
        $rules = wp_parse_args( $rules, $default_rules );

        // Determine license type
        $type = 'standard';
        if ( ! empty( $license->reduction_benevole ) ) {
            $type = 'benevole';
        } elseif ( ! empty( $license->reduction_postier ) ) {
            $type = 'postier';
        } elseif ( ! empty( $license->competition ) ) {
            $type = 'competition';
        }

        // Base price according to type
        $price = isset( $rules['type_prices'][ $type ] )
            ? floatval( $rules['type_prices'][ $type ] )
            : floatval( $rules['base_price'] );

        // Regional adjustments
        if ( ! empty( $license->region ) && ! empty( $rules['region_adjustments'][ $license->region ] ) ) {
            $price += floatval( $rules['region_adjustments'][ $license->region ] );
        }

        // Quota surcharge when licence not included
        if ( isset( $license->is_included ) && ! $license->is_included && ! empty( $rules['quota_surcharge'] ) ) {
            $price += floatval( $rules['quota_surcharge'] );
        }

        // Additional discounts (percentage or flat amount)
        if ( ! empty( $license->discount_code ) && ! empty( $rules['discounts'][ $license->discount_code ] ) ) {
            $discount = floatval( $rules['discounts'][ $license->discount_code ] );
            if ( $discount >= 1 ) {
                $price -= $discount;
            } else {
                $price -= ( $price * $discount );
            }
        }

        $price = max( 0, $price );

        return apply_filters( 'ufsc_license_price', $price, $license, $rules );
    }

    /**
     * Get or create license product in WooCommerce
     */
    private static function get_or_create_license_product() {
        $current_year = date('Y');
        $product_name = 'Licence UFSC ' . $current_year;
        
        // Check if product already exists
        $existing_products = get_posts(array(
            'post_type' => 'product',
            'meta_query' => array(
                array(
                    'key' => '_ufsc_license_product',
                    'value' => $current_year,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        ));

        if (!empty($existing_products)) {
            return $existing_products[0]->ID;
        }

        // Create new product
        $product = new WC_Product_Simple();
        $product->set_name($product_name);
        $product->set_description(__('Licence UFSC pour l\'année en cours', 'ufsc-clubs'));
        $product->set_short_description(__('Licence UFSC', 'ufsc-clubs'));
        $product->set_price(50.00); // Default price, will be overridden per order
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden'); // Hide from catalog

        $product_id = $product->save();
        
        if ($product_id) {
            // Add meta to identify this as UFSC license product
            update_post_meta($product_id, '_ufsc_license_product', $current_year);
        }

        return $product_id;
    }

    public static function handle_delete_licence(){
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer('ufsc_sql_delete_licence');

        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_licences'];
        $pk = $s['pk_licence'];
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        // Fetch the club ID for the licence to validate permissions
        $club_id = $id ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT club_id FROM {$t} WHERE {$pk} = %d", $id ) ) : 0;
        $user_id = get_current_user_id();

        // Verify capability and club ownership before proceeding
        if ( ! current_user_can( 'manage_options' ) && ufsc_get_user_club_id( $user_id ) !== $club_id ) {
            set_transient( 'ufsc_error_' . $user_id, __( 'Permissions insuffisantes', 'ufsc-clubs' ), 30 );
            wp_safe_redirect( wp_get_referer() );
            exit; // Abort if user lacks rights on this club
        }

        if ( $id ){
            $result = $wpdb->delete( $t, array( $pk=>$id ) );
            if ( $result !== false ) {
                UFSC_CL_Utils::log('Licence supprimée: ID ' . $id, 'info');
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&deleted=1&deleted_id='.$id) );
            } else {
                UFSC_CL_Utils::log('Erreur suppression licence: ID ' . $id, 'error');
                wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&error='.urlencode(__('Erreur lors de la suppression de la licence','ufsc-clubs'))) );
            }
        } else {
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&error='.urlencode(__('ID de licence invalide','ufsc-clubs'))) );
        }
        exit;
    }

    /**
     * Render WooCommerce settings page
     */
    public static function render_woocommerce_settings() {
        ufsc_render_woocommerce_settings_page();
    }

    /**
     * Handle AJAX request to update licence status
     */
    public static function handle_ajax_update_licence_status() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'ufsc_ajax_nonce') || !current_user_can('manage_options')) {
            wp_die();
        }

        $licence_id = intval($_POST['licence_id']);
        $new_status = sanitize_text_field($_POST['status']);

        if (!$licence_id || empty($new_status)) {
            wp_send_json_error(array('message' => 'Invalid parameters'));
        }

        // Validate status
        $valid_statuses = array_keys(UFSC_SQL::statuses());
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(array('message' => 'Invalid status'));
        }

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $table = $s['table_licences'];
        $pk = $s['pk_licence'];

        // Update the status
        $result = $wpdb->update(
            $table,
            array('statut' => $new_status),
            array($pk => $licence_id),
            array('%s'),
            array('%d')
        );

        if ($result !== false) {
            // Get badge class for the new status
            $status_map = array('valide'=>'success','a_regler'=>'info','desactive'=>'off','en_attente'=>'wait');
            $badge_class = isset($status_map[$new_status]) ? $status_map[$new_status] : 'info';
            $status_label = UFSC_SQL::statuses()[$new_status];

            wp_send_json_success(array(
                'message' => 'Status updated',
                'badge_class' => $badge_class,
                'status_label' => $status_label
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to update status'));
        }
    }

    /**
     * Handle AJAX request to send licences to payment
     */
    public static function handle_ajax_send_to_payment() {
        // Check nonce and permissions
        if (!wp_verify_nonce($_POST['nonce'], 'ufsc_ajax_nonce') || !current_user_can('manage_options')) {
            wp_die();
        }

        $licence_ids = isset($_POST['licence_ids']) ? array_map('intval', $_POST['licence_ids']) : array();

        if (empty($licence_ids)) {
            wp_send_json_error(array('message' => 'No licences selected'));
        }

        // Check if WooCommerce is active
        if (!function_exists('WC')) {
            wp_send_json_error(array('message' => 'WooCommerce is not active'));
        }

        try {
            // Get licence details
            global $wpdb;
            $s = UFSC_SQL::get_settings();
            $licences_table = $s['table_licences'];
            $clubs_table = $s['table_clubs'];
            
            $licence_ids_placeholder = implode(',', array_fill(0, count($licence_ids), '%d'));
            $query = "SELECT l.*, c.nom as club_nom, c.email as club_email 
                     FROM `{$licences_table}` l 
                     LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id 
                     WHERE l.id IN ({$licence_ids_placeholder})";
            
            $licences = $wpdb->get_results($wpdb->prepare($query, $licence_ids));

            if (empty($licences)) {
                wp_send_json_error(array('message' => 'No valid licences found'));
            }

            // Create WooCommerce order
            $order = wc_create_order();
            
            // Get or create a product for licence fees (you might want to create this in WooCommerce admin)
            $product_id = self::get_or_create_licence_product();
            
            // Add licences to order
            foreach ($licences as $licence) {
                $product = wc_get_product($product_id);
                $order->add_product($product, 1, array(
                    'ufsc_licence_id' => $licence->id,
                    'ufsc_licence_name' => $licence->prenom . ' ' . $licence->nom,
                    'ufsc_club_name' => $licence->club_nom
                ));
            }

            // Set billing information (use first licence's club info)
            $first_licence = $licences[0];
            $order->set_billing_email($first_licence->club_email ?: get_option('admin_email'));
            $order->set_billing_first_name($first_licence->club_nom ?: 'Club');
            
            // Calculate totals and save
            $order->calculate_totals();
            
            // Get payment URL
            $payment_url = $order->get_checkout_payment_url();

            wp_send_json_success(array(
                'message' => 'Order created successfully',
                'order_id' => $order->get_id(),
                'payment_url' => $payment_url
            ));

        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error creating order: ' . $e->getMessage()));
        }
    }

    /**
     * Get or create licence product for WooCommerce
     */
    private static function get_or_create_licence_product() {
        // Check if product already exists
        $existing_product = get_posts(array(
            'post_type' => 'product',
            'meta_key' => '_ufsc_licence_product',
            'meta_value' => '1',
            'numberposts' => 1
        ));

        if (!empty($existing_product)) {
            return $existing_product[0]->ID;
        }

        // Create product
        $product = new WC_Product_Simple();
        $product->set_name('Licence UFSC');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(50); // Default price - should be configurable
        $product->set_regular_price(50);
        $product->set_virtual(true);
        $product->set_sold_individually(true);
        
        $product_id = $product->save();
        
        // Mark as UFSC licence product
        update_post_meta($product_id, '_ufsc_licence_product', '1');
        
        return $product_id;
    }

    /**
     * Handle export data request
     */
    public static function handle_export_data() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }
        if (!current_user_can('manage_options')) {
            wp_die('Accès refusé');
        }
        check_admin_referer('ufsc_export_data');

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $licences_table = $s['table_licences'];
        $clubs_table = $s['table_clubs'];

        // Get filters
        $filter_club = isset($_POST['filter_club']) ? sanitize_text_field($_POST['filter_club']) : '';
        $filter_region = isset($_POST['filter_region']) ? sanitize_text_field($_POST['filter_region']) : '';
        $filter_status = isset($_POST['filter_status']) ? sanitize_text_field($_POST['filter_status']) : '';
        $export_format = isset($_POST['export_format']) ? sanitize_text_field($_POST['export_format']) : 'csv';
        $export_columns = isset($_POST['export_columns']) ? $_POST['export_columns'] : array();

        // Build query with filters
        $where_conditions = array();
        if (!empty($filter_club)) {
            $where_conditions[] = $wpdb->prepare("l.club_id = %d", intval($filter_club));
        }
        if (!empty($filter_region)) {
            $where_conditions[] = $wpdb->prepare("l.region = %s", $filter_region);
        }
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("l.statut = %s", $filter_status);
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Build select clause based on selected columns
        $column_mapping = array(
            'id' => 'l.id',
            'nom' => 'l.nom',
            'prenom' => 'l.prenom',
            'email' => 'l.email',
            'telephone' => 'l.telephone',
            'date_naissance' => 'l.date_naissance',
            'sexe' => 'l.sexe',
            'adresse' => 'l.adresse',
            'ville' => 'l.ville',
            'code_postal' => 'l.code_postal',
            'statut' => 'l.statut',
            'date_creation' => 'l.date_creation',
            'club_nom' => 'c.nom AS club_nom',
            'region' => 'l.region'
        );

        $select_fields = array();
        foreach ($export_columns as $col) {
            if (isset($column_mapping[$col])) {
                $select_fields[] = $column_mapping[$col];
            }
        }

        if (empty($select_fields)) {
            $select_fields = array_values($column_mapping); // Default to all columns
        }

        $query = "SELECT " . implode(', ', $select_fields) . " 
                  FROM `{$licences_table}` l 
                  LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id 
                  {$where_clause} 
                  ORDER BY l.id DESC";

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            wp_safe_redirect(admin_url('admin.php?page=ufsc-exports&error=' . urlencode('Aucune donnée à exporter')));
            exit;
        }

        // Generate filename
        $filename = 'ufsc_licences_' . date('Y-m-d_H-i-s');

        if ($export_format === 'xlsx') {
            // XLSX export
            self::export_xlsx($results, $filename);
        } else {
            // CSV export
            self::export_csv($results, $filename);
        }
    }

    /**
     * Export data as CSV
     */
    private static function export_csv($data, $filename) {
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");

        // Headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]), ';');
        }

        // Data rows
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }

    /**
     * Export data as XLSX (basic implementation)
     */
    private static function export_xlsx($data, $filename) {
        // For now, fallback to CSV - in production, you'd use a library like PhpSpreadsheet
        self::export_csv($data, $filename);
    }

    /**
     * Render Exports page
     */
    public static function render_exports() {
        require_once UFSC_CL_DIR . 'includes/admin/page-ufsc-exports.php';
        ufsc_render_exports_page();
    }
} /* end class */
