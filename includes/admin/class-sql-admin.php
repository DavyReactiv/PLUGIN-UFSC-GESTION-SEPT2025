<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

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
            'refusee' => 'rejected',
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
            echo '<form method="post">';
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
        } elseif ( $type === 'licence_status' ){
            $st = UFSC_SQL::statuses();
            echo '<select name="'.esc_attr($k).'" '.$disabled_attr.'>';
            foreach( $st as $sv=>$sl ){
                echo '<option value="'.esc_attr($sv).'" '.selected($val,$sv,false).'>'.esc_html($sl).'</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        }
        echo '</div>';
    }

    public static function handle_save_club(){
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé');
        check_admin_referer('ufsc_sql_save_club');

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];
        $fields = UFSC_SQL::get_club_fields();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $data = array();
        foreach( $fields as $k=>$conf ){
            $data[$k] = isset($_POST[$k]) ? sanitize_text_field($_POST[$k]) : null;
        }
        if ( empty($data['statut']) ){
            $data['statut'] = 'en_attente';
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
            } else {
                $result = $wpdb->insert( $t, $data );
                if ( $result === false ) {
                    throw new Exception('Erreur lors de la création du club');
                }
                $id = (int) $wpdb->insert_id;
                UFSC_CL_Utils::log('Nouveau club créé: ID ' . $id, 'info');
            }

            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id='.$id.'&updated=1') );
            exit;
        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur sauvegarde club: ' . $e->getMessage(), 'error');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($e->getMessage())) );
            exit;
        }
    }

    public static function handle_delete_club(){
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

        if ( $readonly ) {
            echo '<h2>'.( $id ? esc_html__('Consulter la licence','ufsc-clubs') : esc_html__('Nouvelle licence','ufsc-clubs') ).'</h2>';
        } else {
            echo '<h2>'.( $id ? esc_html__('Éditer la licence','ufsc-clubs') : esc_html__('Nouvelle licence','ufsc-clubs') ).'</h2>';
        }
        
        // Affichage des messages
        if ( isset($_GET['updated']) && $_GET['updated'] == '1' ) {
            echo UFSC_CL_Utils::show_success(__('Licence enregistrée avec succès', 'ufsc-clubs'));
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
            self::render_field_licence($k,$conf,$val, $readonly);
        }
        echo '</div>';
        
        if ( !$readonly ) {
            echo '<p><button class="button button-primary">'.esc_html__('Enregistrer','ufsc-clubs').'</button> <a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences') ).'">'.esc_html__('Annuler','ufsc-clubs').'</a></p>';
            echo '</form>';
        } else {
            echo '<p><a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences') ).'">'.esc_html__('Retour à la liste','ufsc-clubs').'</a>';
            if ( current_user_can('manage_options') ) {
                echo ' <a class="button button-primary" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$id) ).'">'.esc_html__('Modifier','ufsc-clubs').'</a>';
            }
            echo '</p>';
        }
    }

    private static function render_field_licence($k,$conf,$val, $readonly = false){
        $label = $conf[0];
        $type  = $conf[1];
        $readonly_attr = $readonly ? 'readonly disabled' : '';
        $disabled_attr = $readonly ? 'disabled' : '';
        
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
            echo '<input type="text" name="certificat_url" value="'.esc_attr($val).'" placeholder="https://..." '.$readonly_attr.'/>';
            if ( !$readonly ) {
                echo '<p class="description">Uploader un fichier ci-dessous alimentera ce champ.</p><input type="file" name="certificat_upload" />';
            } else if ( $val ) {
                echo '<p class="description"><a href="'.esc_url($val).'" target="_blank">'.esc_html__('Voir le certificat', 'ufsc-clubs').'</a></p>';
            }
        } else {
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" '.$readonly_attr.' />';
        }
        echo '</div>';
    }

    public static function handle_save_licence(){
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé');
        check_admin_referer('ufsc_sql_save_licence');

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_licences'];
        $pk = $s['pk_licence'];
        $fields = UFSC_SQL::get_licence_fields();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

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
        if ( empty($data['statut']) ){
            $data['statut'] = 'en_attente';
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

            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$id.'&updated=1') );
            exit;
        } catch (Exception $e) {
            UFSC_CL_Utils::log('Erreur sauvegarde licence: ' . $e->getMessage(), 'error');
            wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action='.($id ? 'edit&id='.$id : 'new').'&error='.urlencode($e->getMessage())) );
            exit;
        }
    }

    public static function handle_delete_licence(){
        if ( ! current_user_can('manage_options') ) wp_die('Accès refusé');
        check_admin_referer('ufsc_sql_delete_licence');

        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_licences'];
        $pk = $s['pk_licence'];
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

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
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Exports', 'ufsc-clubs') . '</h1>';
        echo '<p>' . esc_html__('Exportez vos données de clubs et licences avec des filtres personnalisés.', 'ufsc-clubs') . '</p>';
        
        // Filters form
        echo '<div class="ufsc-export-filters" style="background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">';
        echo '<h3>' . esc_html__('Filtres d\'export', 'ufsc-clubs') . '</h3>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
        wp_nonce_field('ufsc_export_data');
        echo '<input type="hidden" name="action" value="ufsc_export_data" />';
        
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;">';
        
        // Club filter
        echo '<div>';
        echo '<label for="filter_club"><strong>' . esc_html__('Club', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_club" id="filter_club">';
        echo '<option value="">' . esc_html__('Tous les clubs', 'ufsc-clubs') . '</option>';
        
        // Get clubs from database
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $clubs_table = $s['table_clubs'];
        $clubs = $wpdb->get_results("SELECT id, nom FROM `{$clubs_table}` ORDER BY nom");
        foreach ($clubs as $club) {
            echo '<option value="' . esc_attr($club->id) . '">' . esc_html($club->nom) . '</option>';
        }
        
        echo '</select>';
        echo '</div>';
        
        // Region filter
        echo '<div>';
        echo '<label for="filter_region"><strong>' . esc_html__('Région', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_region" id="filter_region">';
        echo '<option value="">' . esc_html__('Toutes les régions', 'ufsc-clubs') . '</option>';
        foreach (UFSC_CL_Utils::regions() as $region) {
            echo '<option value="' . esc_attr($region) . '">' . esc_html($region) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        // Status filter
        echo '<div>';
        echo '<label for="filter_status"><strong>' . esc_html__('Statut', 'ufsc-clubs') . '</strong></label>';
        echo '<select name="filter_status" id="filter_status">';
        echo '<option value="">' . esc_html__('Tous les statuts', 'ufsc-clubs') . '</option>';
        foreach (UFSC_SQL::statuses() as $status_key => $status_label) {
            echo '<option value="' . esc_attr($status_key) . '">' . esc_html($status_label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        
        echo '</div>';
        
        // Column selection
        echo '<div style="margin: 20px 0;">';
        echo '<h4>' . esc_html__('Colonnes à exporter', 'ufsc-clubs') . '</h4>';
        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px;">';
        
        $export_columns = array(
            'id' => __('ID', 'ufsc-clubs'),
            'nom' => __('Nom', 'ufsc-clubs'),
            'prenom' => __('Prénom', 'ufsc-clubs'),
            'email' => __('Email', 'ufsc-clubs'),
            'telephone' => __('Téléphone', 'ufsc-clubs'),
            'date_naissance' => __('Date de naissance', 'ufsc-clubs'),
            'sexe' => __('Sexe', 'ufsc-clubs'),
            'adresse' => __('Adresse', 'ufsc-clubs'),
            'ville' => __('Ville', 'ufsc-clubs'),
            'code_postal' => __('Code postal', 'ufsc-clubs'),
            'statut' => __('Statut', 'ufsc-clubs'),
            'date_creation' => __('Date de création', 'ufsc-clubs'),
            'club_nom' => __('Nom du club', 'ufsc-clubs'),
            'region' => __('Région', 'ufsc-clubs')
        );
        
        foreach ($export_columns as $col_key => $col_label) {
            echo '<label style="display: flex; align-items: center; gap: 5px;">';
            echo '<input type="checkbox" name="export_columns[]" value="' . esc_attr($col_key) . '" checked />';
            echo esc_html($col_label);
            echo '</label>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // Export buttons
        echo '<div style="margin: 20px 0;">';
        echo '<button type="submit" name="export_format" value="csv" class="button button-primary">';
        echo esc_html__('Exporter CSV', 'ufsc-clubs');
        echo '</button>';
        echo ' ';
        echo '<button type="submit" name="export_format" value="xlsx" class="button button-secondary">';
        echo esc_html__('Exporter XLSX', 'ufsc-clubs');
        echo '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        
        echo '</div>';
    }
} /* end class */
