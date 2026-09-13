<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Dossiers FFST v2.
 *
 * Objectifs :
 * - n'afficher par défaut que les clubs réellement présents sur la saison choisie ;
 * - proposer des filtres utiles (recherche, saison, région, état affiliation) ;
 * - éviter le N+1 des licences sur la liste en chargeant les licences de la page en une fois.
 *
 * Lecture seule : aucune donnée club/licence/commande n'est modifiée ici.
 */
final class UFSC_FFST_Documents_Admin_V2 {
    const DEFAULT_PER_PAGE = 20;

    public static function render() {
        if ( ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder aux dossiers FFST.', 'ufsc-clubs' ) );
        }

        $current_season = self::current_season();
        $season = self::normalize_season(
            isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : $current_season,
            $current_season
        ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = isset( $_GET['s'] ) && ! is_array( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $region = isset( $_GET['region'] ) && ! is_array( $_GET['region'] ) ? sanitize_text_field( wp_unslash( $_GET['region'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $aff_status = isset( $_GET['aff_status'] ) && ! is_array( $_GET['aff_status'] ) ? sanitize_key( wp_unslash( $_GET['aff_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $aff_status, array( '', 'active', 'pending', 'other' ), true ) ) { $aff_status = ''; }
        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : self::DEFAULT_PER_PAGE; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $per_page, array( 10, 20, 50 ), true ) ) { $per_page = self::DEFAULT_PER_PAGE; }

        $page_data = self::get_clubs_page( $search, $season, $region, $aff_status, $paged, $per_page );
        $seasons = self::get_seasons( $season );
        $regions = self::get_regions( $season );
        $club = $club_id ? self::get_club( $club_id ) : null;

        echo '<div class="wrap ufsc-ffst-admin ufsc-ffst-admin-v2">';
        self::render_styles();
        if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); }
        echo '<div class="ufsc-ffst-shell">';
        echo '<div class="ufsc-ffst-heading"><div><h1>' . esc_html__( 'Dossiers FFST', 'ufsc-clubs' ) . '</h1><p>' . esc_html__( 'Recherche, contrôle et préparation des dossiers FFST par club et par saison.', 'ufsc-clubs' ) . '</p></div>';
        echo '<span class="ufsc-ffst-season-badge">' . esc_html( sprintf( __( 'Saison affichée : %s', 'ufsc-clubs' ), $season ) ) . '</span></div>';

        self::render_filters( $search, $season, $seasons, $region, $regions, $aff_status, $per_page );
        self::render_clubs_table( $page_data, $search, $season, $region, $aff_status, $paged, $per_page, $club_id );
        self::render_pagination( $page_data['total'], $paged, $per_page );

        if ( $club ) {
            echo '<hr class="ufsc-ffst-divider">';
            echo '<h2 class="ufsc-ffst-selected-title">' . esc_html__( 'Dossier sélectionné', 'ufsc-clubs' ) . '</h2>';
            $licences = self::get_licences( $club_id, $season );
            $readiness = self::build_readiness_from_licences( $club, $licences );
            self::render_summary( $club, $season, $readiness );
            self::render_affiliation_section( $readiness );
            self::render_licences_section( $readiness );
            self::render_documents_section( $club_id, $season, $readiness );
        }
        echo '</div></div>';
    }

    private static function render_styles() {
        echo '<style>
        .ufsc-ffst-admin-v2{max-width:none}.ufsc-ffst-shell{max-width:1480px}.ufsc-ffst-heading{display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}.ufsc-ffst-heading h1{margin-bottom:4px}.ufsc-ffst-season-badge{display:inline-flex;padding:7px 11px;border-radius:999px;background:#e8f0fe;color:#174ea6;font-weight:700}
        .ufsc-ffst-filters{display:grid;grid-template-columns:minmax(260px,2fr) minmax(150px,.8fr) minmax(170px,1fr) minmax(170px,1fr) 110px auto;gap:12px;align-items:end;margin:18px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px;box-sizing:border-box}.ufsc-ffst-filters label{display:grid;gap:5px}.ufsc-ffst-filters input,.ufsc-ffst-filters select{width:100%;max-width:none}.ufsc-ffst-filter-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .ufsc-ffst-table-wrap{overflow:auto;background:#fff;border:1px solid #dcdcde;border-radius:10px}.ufsc-ffst-table-wrap table{border:0;min-width:900px}.ufsc-ffst-table-wrap th,.ufsc-ffst-table-wrap td{vertical-align:middle}.ufsc-ffst-progress{display:flex;align-items:center;gap:8px;min-width:150px}.ufsc-ffst-progress__bar{width:96px;height:8px;background:#dcdcde;border-radius:999px;overflow:hidden}.ufsc-ffst-progress__value{height:100%;background:#2271b1;border-radius:999px}.ufsc-ffst-pagination{margin:16px 0;display:flex;justify-content:flex-end}.ufsc-ffst-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;margin-left:4px;border:1px solid #c3c4c7;background:#fff;text-decoration:none;border-radius:4px}.ufsc-ffst-pagination .page-numbers.current{background:#2271b1;color:#fff;border-color:#2271b1}.ufsc-ffst-divider{margin:28px 0 18px}.ufsc-ffst-selected-title{font-size:20px}.ufsc-ffst-empty{padding:22px;text-align:center;color:#646970}
        @media(max-width:1200px){.ufsc-ffst-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.ufsc-ffst-filter-actions{grid-column:1/-1}}
        @media(max-width:782px){.ufsc-ffst-shell{max-width:100%}.ufsc-ffst-filters{grid-template-columns:1fr}.ufsc-ffst-filter-actions{grid-column:auto}.ufsc-ffst-pagination{justify-content:flex-start}}
        </style>';
    }

    private static function render_filters( $search, $season, $seasons, $region, $regions, $aff_status, $per_page ) {
        echo '<form method="get" class="ufsc-ffst-filters">';
        echo '<input type="hidden" name="page" value="ufsc-ffst-documents">';
        echo '<label><strong>' . esc_html__( 'Rechercher un club', 'ufsc-clubs' ) . '</strong><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nom, ID, n° affiliation, email, ville…', 'ufsc-clubs' ) . '"></label>';
        echo '<label><strong>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</strong><select name="season">';
        foreach ( $seasons as $row_season ) { echo '<option value="' . esc_attr( $row_season ) . '"' . selected( $season, $row_season, false ) . '>' . esc_html( $row_season ) . '</option>'; }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__( 'Région', 'ufsc-clubs' ) . '</strong><select name="region"><option value="">' . esc_html__( 'Toutes les régions', 'ufsc-clubs' ) . '</option>';
        foreach ( $regions as $row_region ) { echo '<option value="' . esc_attr( $row_region ) . '"' . selected( $region, $row_region, false ) . '>' . esc_html( $row_region ) . '</option>'; }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__( 'État affiliation', 'ufsc-clubs' ) . '</strong><select name="aff_status">';
        foreach ( array( '' => __( 'Tous les états', 'ufsc-clubs' ), 'active' => __( 'Active / validée', 'ufsc-clubs' ), 'pending' => __( 'En attente', 'ufsc-clubs' ), 'other' => __( 'Autre état', 'ufsc-clubs' ) ) as $key => $label ) { echo '<option value="' . esc_attr( $key ) . '"' . selected( $aff_status, $key, false ) . '>' . esc_html( $label ) . '</option>'; }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__( 'Par page', 'ufsc-clubs' ) . '</strong><select name="per_page">';
        foreach ( array( 10, 20, 50 ) as $size ) { echo '<option value="' . esc_attr( $size ) . '"' . selected( $per_page, $size, false ) . '>' . esc_html( $size ) . '</option>'; }
        echo '</select></label>';
        echo '<div class="ufsc-ffst-filter-actions"><button class="button button-primary">' . esc_html__( 'Filtrer', 'ufsc-clubs' ) . '</button>';
        if ( '' !== $search || '' !== $region || '' !== $aff_status || $season !== self::current_season() || self::DEFAULT_PER_PAGE !== $per_page ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( 'page', 'ufsc-ffst-documents', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Réinitialiser', 'ufsc-clubs' ) . '</a>';
        }
        echo '</div></form>';
    }

    private static function render_clubs_table( $page_data, $search, $season, $region, $aff_status, $paged, $per_page, $selected_club_id ) {
        $rows = (array) ( $page_data['items'] ?? array() );
        $total = absint( $page_data['total'] ?? 0 );
        echo '<p><strong>' . esc_html( sprintf( _n( '%d club de la saison trouvé', '%d clubs de la saison trouvés', $total, 'ufsc-clubs' ), $total ) ) . '</strong></p>';
        echo '<div class="ufsc-ffst-table-wrap"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Identifiant', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Région', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Complétude', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Action', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        if ( ! $rows ) {
            echo '<tr><td colspan="7" class="ufsc-ffst-empty">' . esc_html__( 'Aucun club de cette saison ne correspond aux filtres.', 'ufsc-clubs' ) . '</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $id = absint( self::value( $row, array( 'id', 'club_id', 'ID' ) ) );
                $name = self::value( $row, array( 'nom', 'name', 'club_name' ) );
                $identifier = self::value( $row, array( 'numero_affiliation_ffst', 'numero_affiliation_ufsc', 'num_affiliation' ) );
                $row_region = self::value( $row, array( 'region' ) );
                $readiness = isset( $page_data['readiness'][ $id ] ) ? $page_data['readiness'][ $id ] : self::build_readiness_from_licences( $row, array() );
                $url = add_query_arg( array( 'page'=>'ufsc-ffst-documents','club_id'=>$id,'season'=>$season,'s'=>$search,'region'=>$region,'aff_status'=>$aff_status,'paged'=>$paged,'per_page'=>$per_page ), admin_url( 'admin.php' ) );
                echo '<tr' . ( $selected_club_id === $id ? ' class="is-selected"' : '' ) . '><td><strong>' . esc_html( $name ?: sprintf( __( 'Club #%d', 'ufsc-clubs' ), $id ) ) . '</strong></td><td>' . esc_html( $identifier ?: '#' . $id ) . '</td><td>' . esc_html( $row_region ?: '—' ) . '</td><td>' . esc_html( $season ) . '</td><td>' . esc_html( $readiness['licence_count'] ) . '/10</td><td><div class="ufsc-ffst-progress"><div class="ufsc-ffst-progress__bar"><div class="ufsc-ffst-progress__value" style="width:' . esc_attr( $readiness['percent'] ) . '%"></div></div><strong>' . esc_html( $readiness['percent'] ) . '%</strong></div></td><td><a class="button' . ( $selected_club_id === $id ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html__( 'Ouvrir le dossier', 'ufsc-clubs' ) . '</a></td></tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    private static function render_pagination( $total, $paged, $per_page ) {
        $pages = max( 1, (int) ceil( absint( $total ) / max( 1, absint( $per_page ) ) ) );
        if ( $pages <= 1 ) { return; }
        $base_url = remove_query_arg( array( 'paged', 'club_id' ) );
        $links = paginate_links( array( 'base'=>add_query_arg( 'paged', '%#%', $base_url ),'format'=>'','current'=>min($paged,$pages),'total'=>$pages,'type'=>'array','prev_text'=>'‹','next_text'=>'›' ) );
        if ( ! $links ) { return; }
        echo '<nav class="ufsc-ffst-pagination" aria-label="' . esc_attr__( 'Pagination des clubs', 'ufsc-clubs' ) . '">'; foreach ( $links as $link ) { echo wp_kses_post( $link ); } echo '</nav>';
    }

    private static function get_clubs_page( $search, $season, $region, $aff_status, $paged, $per_page ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id','club_id','ID' ) ) : 'id';
        $name_col = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'name', $columns, true ) ? 'name' : $pk );
        $where = array(); $params = array();

        $season_scope = self::season_membership_condition( $season, $aff_status, 'c', $pk );
        if ( $season_scope['sql'] ) { $where[] = $season_scope['sql']; $params = array_merge( $params, $season_scope['params'] ); }
        else { $where[] = '0=1'; }

        if ( '' !== $region && in_array( 'region', $columns, true ) ) { $where[] = 'c.`region`=%s'; $params[] = $region; }
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%'; $parts = array(); $search_params = array();
            foreach ( array( 'nom','name','club_name','numero_affiliation_ffst','numero_affiliation_ufsc','num_affiliation','email','ville' ) as $column ) {
                if ( in_array( $column, $columns, true ) ) { $parts[] = 'c.`' . $column . '` LIKE %s'; $search_params[] = $like; }
            }
            if ( ctype_digit( $search ) ) { $parts[] = 'c.`' . $pk . '`=%d'; $search_params[] = absint( $search ); }
            if ( $parts ) { $where[] = '(' . implode( ' OR ', $parts ) . ')'; $params = array_merge( $params, $search_params ); }
        }
        $where_sql = $where ? implode( ' AND ', $where ) : '1=1';
        $count_sql = "SELECT COUNT(*) FROM `{$table}` c WHERE {$where_sql}";
        $prepared_count = $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $prepared_count ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $offset = ( max( 1, $paged ) - 1 ) * $per_page;
        $query = "SELECT c.* FROM `{$table}` c WHERE {$where_sql} ORDER BY c.`{$name_col}` ASC LIMIT %d OFFSET %d";
        $prepared = $wpdb->prepare( $query, array_merge( $params, array( $per_page, $offset ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = (array) $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $ids = array(); foreach ( $items as $row ) { $id = absint( self::value( $row, array( 'id','club_id','ID' ) ) ); if ( $id ) { $ids[] = $id; } }
        $licence_map = self::get_licences_for_clubs( $ids, $season );
        $readiness = array();
        foreach ( $items as $row ) { $id = absint( self::value( $row, array( 'id','club_id','ID' ) ) ); $readiness[ $id ] = self::build_readiness_from_licences( $row, $licence_map[ $id ] ?? array() ); }
        return array( 'items'=>$items,'total'=>$total,'readiness'=>$readiness );
    }

    private static function season_membership_condition( $season, $aff_status = '', $club_alias = 'c', $club_pk = 'id' ) {
        global $wpdb;
        if ( class_exists( 'UFSC_Season_Archive_Manager' ) ) {
            $aff_table = UFSC_Season_Archive_Manager::get_affiliations_table();
            if ( $aff_table && ( ! function_exists( 'ufsc_table_exists' ) || ufsc_table_exists( $aff_table ) ) ) {
                $cols = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $aff_table ) : array();
                $club_col = self::first_existing( $cols, array( 'club_id','id_club' ) );
                $season_col = self::first_existing( $cols, array( 'season','saison','paid_season','season_end_year' ) );
                $status_col = self::first_existing( $cols, array( 'status','statut' ) );
                if ( $club_col && $season_col ) {
                    $season_value = self::season_db_value( $season, $season_col );
                    $sql = "EXISTS (SELECT 1 FROM `{$aff_table}` a WHERE a.`{$club_col}`={$club_alias}.`{$club_pk}` AND a.`{$season_col}`=%s";
                    $params = array( $season_value );
                    if ( $aff_status && $status_col ) {
                        $active = array( 'active','actif','validated','valide','validee','paid','completed' );
                        $pending = array( 'pending','pending_payment','pending_validation','en_attente','attente','a_regler','processing' );
                        if ( 'active' === $aff_status || 'pending' === $aff_status ) {
                            $values = 'active' === $aff_status ? $active : $pending; $placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
                            $sql .= " AND LOWER(a.`{$status_col}`) IN ({$placeholders})"; $params = array_merge( $params, $values );
                        } elseif ( 'other' === $aff_status ) {
                            $values = array_merge( $active, $pending ); $placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
                            $sql .= " AND (a.`{$status_col}` IS NULL OR a.`{$status_col}`='' OR LOWER(a.`{$status_col}`) NOT IN ({$placeholders}))"; $params = array_merge( $params, $values );
                        }
                    }
                    return array( 'sql'=>$sql . ')','params'=>$params );
                }
            }
        }
        $lic_table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $cols = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $lic_table ) : array();
        $club_col = self::first_existing( $cols, array( 'club_id','id_club' ) );
        $season_col = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $lic_table ) : self::first_existing( $cols, array( 'paid_season','season','saison','season_end_year' ) );
        if ( $club_col && $season_col ) {
            return array( 'sql'=>"EXISTS (SELECT 1 FROM `{$lic_table}` l WHERE l.`{$club_col}`={$club_alias}.`{$club_pk}` AND l.`{$season_col}`=%s)", 'params'=>array( self::season_db_value( $season, $season_col ) ) );
        }
        return array( 'sql'=>'','params'=>array() );
    }

    private static function get_regions( $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $cols = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array(); if ( ! in_array( 'region', $cols, true ) ) { return array(); }
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id','club_id','ID' ) ) : 'id';
        $scope = self::season_membership_condition( $season, '', 'c', $pk ); if ( ! $scope['sql'] ) { return array(); }
        $sql = "SELECT DISTINCT c.`region` FROM `{$table}` c WHERE c.`region` IS NOT NULL AND c.`region`<>'' AND {$scope['sql']} ORDER BY c.`region` ASC";
        $prepared = $wpdb->prepare( $sql, $scope['params'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return array_values( array_filter( array_map( 'strval', (array) $wpdb->get_col( $prepared ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    private static function get_licences_for_clubs( $club_ids, $season ) {
        global $wpdb; $map = array(); $club_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $club_ids ) ) ) ); if ( ! $club_ids ) { return $map; }
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $cols = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array(); $club_col = self::first_existing( $cols, array( 'club_id','id_club' ) );
        $season_col = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : self::first_existing( $cols, array( 'paid_season','season','saison','season_end_year' ) );
        if ( ! $club_col ) { return $map; }
        $id_placeholders = implode( ',', array_fill( 0, count( $club_ids ), '%d' ) ); $params = $club_ids; $sql = "SELECT * FROM `{$table}` WHERE `{$club_col}` IN ({$id_placeholders})";
        if ( $season_col ) { $sql .= " AND `{$season_col}`=%s"; $params[] = self::season_db_value( $season, $season_col ); }
        $prepared = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        foreach ( (array) $wpdb->get_results( $prepared ) as $licence ) { $cid = absint( $licence->{$club_col} ?? 0 ); if ( $cid ) { $map[ $cid ][] = $licence; } } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $map;
    }

    private static function get_licences( $club_id, $season ) { $map = self::get_licences_for_clubs( array( $club_id ), $season ); return $map[ absint( $club_id ) ] ?? array(); }

    private static function build_readiness_from_licences( $club, $licences ) {
        $fields = array(
            'Nom du club'=>self::value($club,array('nom','name','club_name')),
            'Adresse du siège'=>self::value($club,array('adresse','adresse_siege','adresse_complete','address')),
            'Téléphone'=>self::value($club,array('telephone','tel','phone')),
            'Mail club'=>self::value($club,array('email','mail','club_email')),
            'Site Internet'=>self::value($club,array('site','site_web','website','url_site','url')),
            'Déclaration préfecture'=>self::value($club,array('numero_recepisse','recepisse','declaration_prefecture','num_declaration','numero_declaration','rna_number','rna')),
            'Adresse salle d’entraînement'=>self::value($club,array('adresse_salle','salle_adresse','training_address')),
        );
        $filled = count( array_filter( $fields, static function($v){ return ''!==trim((string)$v); } ) );
        $roles = array(
            'Président'=>self::find_role_licence($licences,array('president','président')),
            'Secrétaire'=>self::find_role_licence($licences,array('secretaire','secrétaire')),
            'Trésorier'=>self::find_role_licence($licences,array('tresorier','trésorier')),
            'Entraîneur / instructeur'=>self::find_role_licence($licences,array('entraineur','entraîneur','instructeur','coach')),
        );
        $role_count = count( array_filter( $roles ) ); $total = count( $licences );
        $percent = (int) round( (($filled+$role_count+min(10,$total))/(count($fields)+count($roles)+10))*100 );
        return array( 'fields'=>$fields,'roles'=>$roles,'licences'=>$licences,'licence_count'=>$total,'minimum_licences_ok'=>$total>=10,'percent'=>max(0,min(100,$percent)) );
    }

    private static function get_club( $club_id ) {
        global $wpdb; $table = class_exists('UFSC_Storage_Resolver') ? UFSC_Storage_Resolver::get_clubs_table() : (function_exists('ufsc_get_clubs_table')?ufsc_get_clubs_table():$wpdb->prefix.'ufsc_clubs'); $pk = class_exists('UFSC_Storage_Resolver') ? UFSC_Storage_Resolver::first_existing_column($table,array('id','club_id','ID')) : 'id';
        return $wpdb->get_row( $wpdb->prepare("SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1",absint($club_id)) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_seasons( $selected='' ) {
        $current=self::current_season(); $seasons=array($current,$selected); if(class_exists('UFSC_Season_Service')){$seasons=array_merge($seasons,(array)UFSC_Season_Service::get_available_seasons(),array(UFSC_Season_Service::get_previous_season()));}
        $seasons=array_values(array_unique(array_filter(array_map(array(__CLASS__,'normalize_season_value'),$seasons)))); rsort($seasons,SORT_STRING); return $seasons?:array($current);
    }
    private static function current_season(){ return class_exists('UFSC_Season_Service')?(string)UFSC_Season_Service::get_current_season():(function_exists('ufsc_get_current_season')?(string)ufsc_get_current_season():''); }
    private static function normalize_season($season,$fallback=''){ $v=self::normalize_season_value($season); return $v?:self::normalize_season_value($fallback); }
    private static function normalize_season_value($season){ if(class_exists('UFSC_Season_Service')){return (string)UFSC_Season_Service::normalize_season($season);} $season=trim(str_replace('/','-',(string)$season)); return preg_match('/^\d{4}-\d{4}$/',$season)?$season:''; }
    private static function season_db_value($season,$column){ if('season_end_year'===$column){ if(function_exists('ufsc_get_season_end_year_from_label')){return ufsc_get_season_end_year_from_label($season);} $parts=explode('-',self::normalize_season_value($season)); return isset($parts[1])?absint($parts[1]):0;} return self::normalize_season_value($season); }
    private static function first_existing($columns,$candidates){foreach((array)$candidates as $c){if(in_array($c,(array)$columns,true)){return $c;}}return '';}
    private static function find_role_licence($licences,$aliases){foreach((array)$licences as $licence){$role=strtolower(remove_accents(trim((string)self::value($licence,array('role','fonction','poste','position')))));foreach($aliases as $alias){if(false!==strpos($role,strtolower(remove_accents($alias)))){return $licence;}}}return null;}
    private static function person_name($licence){return trim(self::value($licence,array('prenom','first_name')).' '.self::value($licence,array('nom','last_name')));}
    private static function value($object,$keys){foreach((array)$keys as $key){if(is_object($object)&&isset($object->{$key})&&''!==trim((string)$object->{$key})){return(string)$object->{$key};}if(is_array($object)&&isset($object[$key])&&''!==trim((string)$object[$key])){return(string)$object[$key];}}return '';}

    private static function render_summary($club,$season,$readiness){$name=self::value($club,array('nom','name','club_name'));echo '<div class="ufsc-dashboard-cards" style="margin:20px 0;"><div class="ufsc-dashboard-card"><div class="card-label">'.esc_html__('Club','ufsc-clubs').'</div><div class="card-value" style="font-size:20px;">'.esc_html($name).'</div></div><div class="ufsc-dashboard-card"><div class="card-label">'.esc_html__('Saison FFST','ufsc-clubs').'</div><div class="card-value" style="font-size:20px;">'.esc_html($season).'</div></div><div class="ufsc-dashboard-card"><div class="card-label">'.esc_html__('Dossier prêt','ufsc-clubs').'</div><div class="card-value">'.esc_html($readiness['percent']).'%</div></div><div class="ufsc-dashboard-card"><div class="card-label">'.esc_html__('Licences saison','ufsc-clubs').'</div><div class="card-value">'.esc_html($readiness['licence_count']).'/10</div></div></div>';}
    private static function render_affiliation_section($readiness){echo '<div class="postbox" style="padding:18px;margin-top:20px;"><h2 style="margin-top:0;">'.esc_html__('1. Dossier affiliation / réaffiliation FFST','ufsc-clubs').'</h2><table class="widefat striped"><thead><tr><th>'.esc_html__('Information officielle FFST','ufsc-clubs').'</th><th>'.esc_html__('Valeur UFSC','ufsc-clubs').'</th><th>'.esc_html__('État','ufsc-clubs').'</th></tr></thead><tbody>';foreach($readiness['fields'] as $label=>$value){$ok='!==';$ok=''!==trim((string)$value);echo '<tr><td><strong>'.esc_html($label).'</strong></td><td>'.esc_html($value?:'—').'</td><td>'.($ok?'<span style="color:#008a20;font-weight:700;">✓ Complet</span>':'<span style="color:#b32d2e;font-weight:700;">À compléter</span>').'</td></tr>';}foreach($readiness['roles'] as $label=>$licence){echo '<tr><td><strong>'.esc_html($label).'</strong></td><td>'.esc_html($licence?self::person_name($licence):'—').'</td><td>'.($licence?'<span style="color:#008a20;font-weight:700;">✓ Identifié</span>':'<span style="color:#b32d2e;font-weight:700;">Licence dirigeant manquante</span>').'</td></tr>';}echo '</tbody></table></div>';}
    private static function render_licences_section($readiness){echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">'.esc_html__('2. Bordereau licences dirigeants FFST','ufsc-clubs').'</h2>';if($readiness['minimum_licences_ok']){echo '<div class="notice notice-success inline"><p><strong>'.esc_html__('Minimum FFST atteint : au moins 10 licences pour la saison.','ufsc-clubs').'</strong></p></div>';}else{echo '<div class="notice notice-warning inline"><p><strong>'.esc_html(sprintf(__('Minimum FFST non atteint : %1$d licence(s) enregistrée(s), 10 requises.','ufsc-clubs'),$readiness['licence_count'])).'</strong></p></div>';}echo '<p>'.esc_html__('Le président, le secrétaire, le trésorier et le ou les entraîneurs doivent être identifiés parmi les licences de la saison.','ufsc-clubs').'</p></div>';}
    private static function render_documents_section($club_id,$season,$readiness){if(class_exists('UFSC_FFST_Export_Admin')){UFSC_FFST_Export_Admin::render_actions($club_id,$season,$readiness);return;}echo '<div class="notice notice-error inline"><p>'.esc_html__('Le module de génération FFST n’est pas disponible.','ufsc-clubs').'</p></div>';}
}
