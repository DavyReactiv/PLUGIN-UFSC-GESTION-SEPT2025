<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_SQL_Admin {

    /* ---------------- Menus ---------------- */
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
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];

        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $where = $status ? $wpdb->prepare("WHERE statut=%s",$status) : '';

        $rows = $wpdb->get_results("SELECT $pk, nom, region, statut, quota_licences FROM `$t` $where ORDER BY $pk DESC");

        echo '<div class="wrap"><h1>'.esc_html__('Clubs (SQL)','ufsc-clubs').'</h1>';
        echo '<p><a href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs&action=new') ).'" class="button button-primary">'.esc_html__('Ajouter un club','ufsc-clubs').'</a> ';
        echo '<a href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs&export=1') ).'" class="button">'.esc_html__('Exporter CSV','ufsc-clubs').'</a></p>';

        echo '<form method="get" style="margin:10px 0">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs"/>';
        echo '<select name="status"><option value="">— Statut —</option>';
        foreach( UFSC_SQL::statuses() as $k=>$v ){
            echo '<option value="'.esc_attr($k).'" '.selected($status,$k,false).'>'.esc_html($v).'</option>';
        }
        echo '</select> ';
        submit_button(__('Filtrer','ufsc-clubs'),'secondary',null,false);
        echo '</form>';

        if ( isset($_GET['action']) && $_GET['action']==='edit' ){
            $id = (int) $_GET['id'];
            self::render_club_form($id);
            echo '</div>';
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='new' ){
            self::render_club_form(0);
            echo '</div>';
            return;
        } elseif ( isset($_GET['export']) ){
            self::csv_clubs($rows);
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>ID</th><th>'.esc_html__('Nom du club','ufsc-clubs').'</th><th>'.esc_html__('Région','ufsc-clubs').'</th><th>'.esc_html__('Statut','ufsc-clubs').'</th><th>'.esc_html__('Quota','ufsc-clubs').'</th><th>'.esc_html__('Actions','ufsc-clubs').'</th>';
        echo '</tr></thead><tbody>';
        if ( $rows ){
            foreach($rows as $r){
                $map = array('valide'=>'success','a_regler'=>'info','desactive'=>'off','en_attente'=>'wait');
                $cls = isset($map[$r->statut]) ? $map[$r->statut] : 'info';
                $badge = UFSC_CL_Utils::esc_badge( UFSC_SQL::statuses()[$r->statut] ?? $r->statut, $cls );
                $view = admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id='.$r->$pk);
                $del  = wp_nonce_url( admin_url('admin-post.php?action=ufsc_sql_delete_club&id='.$r->$pk), 'ufsc_sql_delete_club' );
                echo '<tr><td>'.(int)$r->$pk.'</td><td>'.esc_html($r->nom).'</td><td>'.esc_html($r->region).'</td><td>'.$badge.'</td><td>'.(int)$r->quota_licences.'</td><td><a class="button" href="'.$view.'">'.esc_html__('Consulter','ufsc-clubs').'</a> <a class="button button-link-delete" href="'.$del.'">'.esc_html__('Supprimer','ufsc-clubs').'</a></td></tr>';
            }
        } else {
            echo '<tr><td colspan="6">'.esc_html__('Aucun club','ufsc-clubs').'</td></tr>';
        }
        echo '</tbody></table></div>';
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

    private static function render_club_form( $id ){
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_clubs'];
        $pk = $s['pk_club'];
        $fields = $s['club_fields'];
        $row = $id ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id) ) : null;

        echo '<h2>'.( $id ? esc_html__('Éditer le club','ufsc-clubs') : esc_html__('Nouveau club','ufsc-clubs') ).'</h2>';
        echo '<form method="post">';
        wp_nonce_field('ufsc_sql_save_club');
        echo '<input type="hidden" name="action" value="ufsc_sql_save_club" />';
        echo '<input type="hidden" name="id" value="'.(int)$id.'" />';

        echo '<div class="ufsc-grid">';
        foreach ( $fields as $k=>$conf ){
            $val = $row ? ( isset($row->$k) ? $row->$k : '' ) : '';
            self::render_field_club($k,$conf,$val);
        }
        echo '</div>';
        echo '<p><button class="button button-primary">'.esc_html__('Enregistrer','ufsc-clubs').'</button> <a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-clubs') ).'">'.esc_html__('Annuler','ufsc-clubs').'</a></p>';
        echo '</form>';
    }

    private static function render_field_club($k,$conf,$val){
        $label = $conf[0];
        $type  = $conf[1];
        echo '<div class="ufsc-field"><label>'.esc_html($label).'</label>';
        if ( $type === 'textarea' ){
            echo '<textarea name="'.esc_attr($k).'" rows="3">'.esc_textarea($val).'</textarea>';
        } elseif ( $type === 'number' ){
            echo '<input type="number" step="1" name="'.esc_attr($k).'" value="'.esc_attr($val).'" />';
        } elseif ( $type === 'region' ){
            echo '<select name="'.esc_attr($k).'">';
            foreach( UFSC_CL_Utils::regions() as $r ){
                echo '<option value="'.esc_attr($r).'" '.selected($val,$r,false).'>'.esc_html($r).'</option>';
            }
            echo '</select>';
        } elseif ( $type === 'licence_status' ){
            $st = UFSC_SQL::statuses();
            echo '<select name="'.esc_attr($k).'">';
            foreach( $st as $sv=>$sl ){
                echo '<option value="'.esc_attr($sv).'" '.selected($val,$sv,false).'>'.esc_html($sl).'</option>';
            }
            echo '</select>';
        } else {
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" />';
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
        $fields = $s['club_fields'];
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        $data = array();
        foreach( $fields as $k=>$conf ){
            $data[$k] = isset($_POST[$k]) ? sanitize_text_field($_POST[$k]) : null;
        }
        if ( empty($data['statut']) ){
            $data['statut'] = 'en_attente';
        }

        if ( $id ){
            $wpdb->update( $t, $data, array( $pk=>$id ) );
        } else {
            $wpdb->insert( $t, $data );
            $id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs&action=edit&id='.$id.'&updated=1') );
        exit;
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
            $wpdb->delete( $t, array( $pk=>$id ) );
        }
        wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-clubs') );
        exit;
    }

    /* ---------------- Licences ---------------- */
    public static function render_licences(){
        global $wpdb;
        $s  = UFSC_SQL::get_settings();
        $t  = $s['table_licences'];
        $pk = $s['pk_licence'];

        $rows = $wpdb->get_results("SELECT $pk, prenom, nom, date_naissance, club_id, region, statut FROM `$t` ORDER BY $pk DESC");

        echo '<div class="wrap"><h1>'.esc_html__('Licences (SQL)','ufsc-clubs').'</h1>';
        echo '<p><a href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences&action=new') ).'" class="button button-primary">'.esc_html__('Ajouter une licence','ufsc-clubs').'</a> ';
        echo '<a href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences&export=1') ).'" class="button">'.esc_html__('Exporter CSV','ufsc-clubs').'</a></p>';

        if ( isset($_GET['action']) && $_GET['action']==='edit' ){
            $id = (int) $_GET['id'];
            self::render_licence_form($id);
            echo '</div>';
            return;
        } elseif ( isset($_GET['action']) && $_GET['action']==='new' ){
            self::render_licence_form(0);
            echo '</div>';
            return;
        } elseif ( isset($_GET['export']) ){
            self::csv_licences($rows);
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>ID</th><th>'.esc_html__('Licencié','ufsc-clubs').'</th><th>'.esc_html__('Naissance','ufsc-clubs').'</th><th>'.esc_html__('Club ID','ufsc-clubs').'</th><th>'.esc_html__('Région','ufsc-clubs').'</th><th>'.esc_html__('Statut','ufsc-clubs').'</th><th>'.esc_html__('Actions','ufsc-clubs').'</th>';
        echo '</tr></thead><tbody>';
        if ( $rows ){
            foreach($rows as $r){
                $map = array('valide'=>'success','a_regler'=>'info','desactive'=>'off','en_attente'=>'wait');
                $cls = isset($map[$r->statut]) ? $map[$r->statut] : 'info';
                $badge = UFSC_CL_Utils::esc_badge( UFSC_SQL::statuses()[$r->statut] ?? $r->statut, $cls );
                $view = admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$r->$pk);
                $del  = wp_nonce_url( admin_url('admin-post.php?action=ufsc_sql_delete_licence&id='.$r->$pk), 'ufsc_sql_delete_licence' );
                $name = trim($r->prenom.' '.$r->nom);
                echo '<tr><td>'.(int)$r->$pk.'</td><td>'.esc_html($name).'</td><td>'.esc_html($r->date_naissance).'</td><td>'.(int)$r->club_id.'</td><td>'.esc_html($r->region).'</td><td>'.$badge.'</td><td><a class="button" href="'.$view.'">'.esc_html__('Consulter','ufsc-clubs').'</a> <a class="button button-link-delete" href="'.$del.'">'.esc_html__('Supprimer','ufsc-clubs').'</a></td></tr>';
            }
        } else {
            echo '<tr><td colspan="7">'.esc_html__('Aucune licence','ufsc-clubs').'</td></tr>';
        }
        echo '</tbody></table></div>';
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

    private static function render_licence_form( $id ){
        global $wpdb;
        $s = UFSC_SQL::get_settings();
        $t = $s['table_licences'];
        $pk = $s['pk_licence'];
        $fields = $s['licence_fields'];
        $row = $id ? $wpdb->get_row( $wpdb->prepare("SELECT * FROM `$t` WHERE `$pk`=%d", $id) ) : null;

        echo '<h2>'.( $id ? esc_html__('Éditer la licence','ufsc-clubs') : esc_html__('Nouvelle licence','ufsc-clubs') ).'</h2>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('ufsc_sql_save_licence');
        echo '<input type="hidden" name="action" value="ufsc_sql_save_licence" />';
        echo '<input type="hidden" name="id" value="'.(int)$id.'" />';

        echo '<div class="ufsc-grid">';
        foreach ( $fields as $k=>$conf ){
            $val = $row ? ( isset($row->$k) ? $row->$k : '' ) : '';
            self::render_field_licence($k,$conf,$val);
        }
        echo '</div>';
        echo '<p><button class="button button-primary">'.esc_html__('Enregistrer','ufsc-clubs').'</button> <a class="button" href="'.esc_url( admin_url('admin.php?page=ufsc-sql-licences') ).'">'.esc_html__('Annuler','ufsc-clubs').'</a></p>';
        echo '</form>';
    }

    private static function render_field_licence($k,$conf,$val){
        $label = $conf[0];
        $type  = $conf[1];
        echo '<div class="ufsc-field"><label>'.esc_html($label).'</label>';
        if ( $type === 'textarea' ){
            echo '<textarea name="'.esc_attr($k).'" rows="3">'.esc_textarea($val).'</textarea>';
        } elseif ( $type === 'number' ){
            echo '<input type="number" step="1" name="'.esc_attr($k).'" value="'.esc_attr($val).'" />';
        } elseif ( $type === 'region' ){
            echo '<select name="'.esc_attr($k).'">';
            foreach( UFSC_CL_Utils::regions() as $r ){
                echo '<option value="'.esc_attr($r).'" '.selected($val,$r,false).'>'.esc_html($r).'</option>';
            }
            echo '</select>';
        } elseif ( $type === 'bool' ){
            echo '<select name="'.esc_attr($k).'"><option value="0" '.selected($val,'0',false).'>Non</option><option value="1" '.selected($val,'1',false).'>Oui</option></select>';
        } elseif ( $type === 'sex' ){
            echo '<label><input type="radio" name="'.esc_attr($k).'" value="M" '.checked($val,'M',false).'/> M</label> <label style="margin-left:10px"><input type="radio" name="'.esc_attr($k).'" value="F" '.checked($val,'F',false).'/> F</label>';
        } elseif ( $type === 'licence_status' ){
            $st = UFSC_SQL::statuses();
            echo '<select name="'.esc_attr($k).'">';
            foreach( $st as $sv=>$sl ){
                echo '<option value="'.esc_attr($sv).'" '.selected($val,$sv,false).'>'.esc_html($sl).'</option>';
            }
            echo '</select>';
        } elseif ( $k === 'certificat_url' ){
            echo '<input type="text" name="certificat_url" value="'.esc_attr($val).'" placeholder="https://..."/><p class="description">Uploader un fichier ci-dessous alimentera ce champ.</p><input type="file" name="certificat_upload" />';
        } else {
            echo '<input type="text" name="'.esc_attr($k).'" value="'.esc_attr($val).'" />';
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
        $fields = $s['licence_fields'];
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

        if ( ! empty($_FILES['certificat_upload']['name']) ){
            require_once ABSPATH.'wp-admin/includes/file.php';
            $upload = wp_handle_upload( $_FILES['certificat_upload'], array('test_form'=>false) );
            if ( ! empty($upload['url']) ){
                $data['certificat_url'] = esc_url_raw( $upload['url'] );
            }
        } else {
            $data['certificat_url'] = isset($_POST['certificat_url']) ? esc_url_raw($_POST['certificat_url']) : '';
        }

        if ( $id ){
            $wpdb->update( $t, $data, array( $pk=>$id ) );
        } else {
            $wpdb->insert( $t, $data );
            $id = (int) $wpdb->insert_id;
        }

        wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences&action=edit&id='.$id.'&updated=1') );
        exit;
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
            $wpdb->delete( $t, array( $pk=>$id ) );
        }
        wp_safe_redirect( admin_url('admin.php?page=ufsc-sql-licences') );
        exit;
    }
} /* end class */
