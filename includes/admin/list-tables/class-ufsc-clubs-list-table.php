<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Clubs List Table
 * Enhanced admin list with filters, search, and pagination
 */
class UFSC_Clubs_List_Table {

    /**
     * Render enhanced clubs list
     */
    public static function render() {
        global $wpdb;

        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        $club_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : array();

        // Handle filters and search
        $filters = self::get_filters();
        $search = self::get_search_query();
        $pagination = self::get_pagination_params();
        $sorting = self::get_sorting_params();

        // Build WHERE conditions
        $where_conditions = self::build_where_conditions( $filters, $search, $club_columns, $clubs_table );
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Build ORDER BY clause
        $order_clause = self::build_order_clause( $sorting, $club_columns, $clubs_table );

        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM `{$clubs_table}` {$where_clause}";
        $total_items = (int) $wpdb->get_var( $total_query );

        // Get clubs with pagination
        $offset = ( $pagination['paged'] - 1 ) * $pagination['per_page'];
        $clubs_query = "
            SELECT *
            FROM `{$clubs_table}`
            {$where_clause}
            {$order_clause}
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ";

        $clubs = $wpdb->get_results( $clubs_query );

        // Calculate pagination
        $total_pages = ceil( $total_items / $pagination['per_page'] );

        // Render the page
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Clubs (SQL)', 'ufsc-clubs' ) . '</h1>';

        // Affichage des notices
        if ( isset($_GET['updated']) && $_GET['updated'] == '1' ) {
            echo UFSC_CL_Utils::show_success(__('Club enregistré avec succès', 'ufsc-clubs'));
        }
        if ( isset($_GET['deleted']) && $_GET['deleted'] == '1' ) {
            $deleted_id = isset($_GET['deleted_id']) ? (int) $_GET['deleted_id'] : '';
            echo UFSC_CL_Utils::show_success(__('Le club #'.$deleted_id.' a été supprimé.', 'ufsc-clubs'));
        }
        if ( isset($_GET['error']) ) {
            echo UFSC_CL_Utils::show_error(sanitize_text_field($_GET['error']));
        }

        // Action buttons
        self::render_action_buttons();

        // Filters
        self::render_filters( $filters, $club_columns, $clubs_table );

        // Search
        self::render_search( $search );

        //Action Grop
        //self::bulck_action_grop_by_club();

        // Results info
        self::render_results_info( $total_items, $pagination );

        // Main table
        $licence_counts = UFSC_CL_Utils::get_valid_licence_counts_by_club();
        self::render_clubs_table( $clubs, $sorting, $licence_counts );

        // Pagination
        self::render_pagination( $pagination['paged'], $total_pages );

        echo '</div>';
    }

    /**
     * Get current filters
     */
    private static function get_filters() {
        $filters = array(
            'region' => isset( $_GET['region'] ) ? sanitize_text_field( $_GET['region'] ) : '',
            'statut' => isset( $_GET['statut'] ) ? sanitize_text_field( $_GET['statut'] ) : '',
            'created_from' => isset( $_GET['created_from'] ) ? sanitize_text_field( $_GET['created_from'] ) : '',
            'created_to' => isset( $_GET['created_to'] ) ? sanitize_text_field( $_GET['created_to'] ) : ''
        );

        return $filters;
    }

    /**
     * Get search query
     */
    private static function get_search_query() {
        return isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';
    }

    /**
     * Get pagination parameters
     */
    private static function get_pagination_params() {
        $per_page_options = array( 20, 50, 100 );
        $per_page = isset( $_GET['per_page'] ) && in_array( (int) $_GET['per_page'], $per_page_options ) ? (int) $_GET['per_page'] : 20;

        return array(
            'paged' => isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1,
            'per_page' => $per_page
        );
    }

    /**
     * Get sorting parameters
     */
    private static function get_sorting_params() {
        $allowed_orderby = array( 'nom', 'date_creation', 'region' );
        $allowed_order = array( 'asc', 'desc' );

        return array(
            'orderby' => isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby ) ? $_GET['orderby'] : 'date_creation',
            'order' => isset( $_GET['order'] ) && in_array( $_GET['order'], $allowed_order ) ? $_GET['order'] : 'desc'
        );
    }

    /**
     * Build WHERE conditions
     */
    private static function build_where_conditions( $filters, $search, $columns, $clubs_table ) {
        global $wpdb;
        $conditions = array();

        // Search query
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_parts = array();
            $search_values = array();

            foreach ( array( 'nom', 'email' ) as $column ) {
                if ( self::has_column( $columns, $clubs_table, $column ) ) {
                    $search_parts[]  = "{$column} LIKE %s";
                    $search_values[] = $search_like;
                }
            }

            if ( ! empty( $search_parts ) ) {
                $conditions[] = $wpdb->prepare(
                    '(' . implode( ' OR ', $search_parts ) . ')',
                    $search_values
                );
            }
        }

        // Region filter
        if ( ! empty( $filters['region'] ) && self::has_column( $columns, $clubs_table, 'region' ) ) {
            $conditions[] = $wpdb->prepare( "region = %s", $filters['region'] );
        }

        // Status filter
        if ( ! empty( $filters['statut'] ) && self::has_column( $columns, $clubs_table, 'statut' ) ) {
            $conditions[] = $wpdb->prepare( "statut = %s", $filters['statut'] );
        }

        // Date range filters
        if ( ! empty( $filters['created_from'] ) && self::is_valid_date( $filters['created_from'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) >= %s", $filters['created_from'] );
        }

        if ( ! empty( $filters['created_to'] ) && self::is_valid_date( $filters['created_to'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) <= %s", $filters['created_to'] );
        }

        return $conditions;
    }

    /**
     * Build ORDER BY clause
     */
    private static function build_order_clause( $sorting, $columns, $clubs_table ) {
        $orderby_map = array(
            'nom' => 'nom',
            'date_creation' => 'date_creation',
            'region' => 'region'
        );

        $requested = isset( $orderby_map[ $sorting['orderby'] ] ) ? $orderby_map[ $sorting['orderby'] ] : 'date_creation';
        if ( ! self::has_column( $columns, $clubs_table, $requested ) ) {
            $requested = self::has_column( $columns, $clubs_table, 'id' ) ? 'id' : 'date_creation';
        }
        if ( ! self::has_column( $columns, $clubs_table, $requested ) ) {
            $requested = '1';
        }

        $orderby = isset( $orderby_map[ $requested ] ) ? $orderby_map[ $requested ] : $requested;
        $order = strtoupper( $sorting['order'] );

        return "ORDER BY {$orderby} {$order}";
    }

    /**
     * Render action buttons
     */
    private static function render_action_buttons() {
        echo '<p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs&action=new' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Ajouter un club', 'ufsc-clubs' );
        echo '</a> ';
        echo '<a href="' . esc_url( add_query_arg( 'export', 'csv' ) ) . '" class="button">';
        echo esc_html__( 'Exporter CSV', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( add_query_arg( 'export', 'xlsx' ) ) . '" class="button">';
        echo esc_html__( 'Exporter XLSX', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( admin_url('admin.php?page=ufsc-import') ) . '" class="button">';
        echo esc_html__( 'Importer', 'ufsc-clubs' );
        echo '</a>';

        echo '</p>';
    }

    /**
     * Render filters
     */
    private static function render_filters( $filters, $columns, $clubs_table ) {
        echo '<div class="ufsc-filters-panel">';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs">';

        echo '<div class="ufsc-filters-row">';

        // Region filter
        self::render_region_filter( $filters['region'], $columns, $clubs_table );

        // Status filter
        self::render_status_filter( $filters['statut'] );

        // Date range filters
        self::render_date_filters( $filters['created_from'], $filters['created_to'] );

        echo '</div>';

        echo '<div class="ufsc-filters-actions">';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'secondary', null, false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '" class="button">' . esc_html__( 'Reset', 'ufsc-clubs' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    /**
     * Render search form
     */
    private static function render_search( $search ) {
        echo '<div class="ufsc-search-panel">';
        echo '<form method="get" class="ufsc-search-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs">';

        // Preserve current filters
        foreach ( self::get_filters() as $key => $value ) {
            if ( ! empty( $value ) ) {
                echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
            }
        }

        echo '<input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Rechercher par nom ou email...', 'ufsc-clubs' ) . '">';
        submit_button( __( 'Rechercher', 'ufsc-clubs' ), 'secondary', null, false );
        echo '</form>';
        echo '</div>';
        
    }

    //add action groppe
    // private static function bulck_action_grop_by_club(){
    //     echo '<form method="post" id="bulk-actions-form">';
    //     // Bulk actions
    //     echo '<div class="ufsc-bulk-actions" style="margin: 15px 0;">';

    //     wp_nonce_field('ufsc_bulk_actions');
    //     echo '<select name="bulk_action" id="bulk-action-selector">';
    //     echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
    //     echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
    //     echo '</select>';
    //     echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';

    //     echo '</div>';

    // }
    

    /**
     * Render results info
     */
    private static function render_results_info( $total_items, $pagination ) {
        $start = ( ( $pagination['paged'] - 1 ) * $pagination['per_page'] ) + 1;
        $end = min( $pagination['paged'] * $pagination['per_page'], $total_items );

        echo '<div class="ufsc-results-info">';
        echo sprintf(
            esc_html__( 'Affichage de %d à %d sur %d clubs', 'ufsc-clubs' ),
            $start,
            $end,
            $total_items
        );

        // Per page selector
        echo ' | ';
        echo '<select onchange="window.location.href=this.value">';
        foreach ( array( 20, 50, 100 ) as $per_page ) {
            $url = add_query_arg( 'per_page', $per_page );
            echo '<option value="' . esc_url( $url ) . '"' . selected( $pagination['per_page'], $per_page, false ) . '>';
            echo sprintf( esc_html__( '%d par page', 'ufsc-clubs' ), $per_page );
            echo '</option>';
        }
        echo '</select>';
        echo '</div>';
    }

    /**
     * Render main clubs table
     */
    private static function render_clubs_table( $clubs, $sorting, $licence_counts ) {
        // Affichage des notices
        if ( isset($_GET['processed']) ) {
            if ( $_GET['processed'] == '1' ) {
                echo UFSC_CL_Utils::show_success(sprintf( __( '%d élément(s) traité(s)', 'ufsc-clubs' ), $_GET['processed']));
            } elseif ( $_GET['processed'] == '0' ) {
                echo UFSC_CL_Utils::show_error(sprintf( __( 'Impossible de supprimer les clubs - présence probable de licences liées.', 'ufsc-clubs' ), $_GET['processed']));
            }
        }

        echo '<form method="post" id="bulk-actions-form">';
        // Bulk actions
        echo '<div class="ufsc-bulk-actions" style="margin: 15px 0;">';

        wp_nonce_field('ufsc_bulk_clubs_actions');
        echo '<select name="bulk_action" id="bulk-action-selector">';
        echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
        echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
        echo '<option value="actif">'.esc_html__('Actif', 'ufsc-clubs').'</option>';
        echo '<option value="en_attente">'.esc_html__('En attente', 'ufsc-clubs').'</option>';
        echo '<option value="creating">'.esc_html__('En cours de création', 'ufsc-clubs').'</option>';
        echo '</select>';
        echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';
        echo '</div>';

        //table
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="check-column"><input type="checkbox" id="select-all-club" /></td>';
        echo '<th>ID</th>';
        echo '<th>' . self::get_sortable_header( 'nom', __( 'Nom du club', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'region', __( 'Région', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'N° Affiliation', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'date_creation', __( 'Créé le', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';



        if ( $clubs ) {
            foreach ( $clubs as $club ) {
                self::render_club_row( $club, $licence_counts );
            }
        } else {
            echo '<tr><td colspan="10">' . esc_html__( 'Aucun club trouvé.', 'ufsc-clubs' ) . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</form>';

    }

    /**
     * Render individual club row
     */
    private static function render_club_row( $club, $licence_counts ) {
    echo '<tr>';

    // Checkbox
    echo '<th class="check-column"><input type="checkbox" name="club_ids[]" value="' . (int) ( $club->id ?? 0 ) . '" /></th>';

    // ID
    echo '<td>' . (int) ( $club->id ?? 0 ) . '</td>';

    // Nom + Email
    $club_name = isset( $club->nom ) ? $club->nom : '';
    $club_email = isset( $club->email ) ? $club->email : '';
    echo '<td><strong>' . esc_html( $club_name ) . '</strong>';
    if ( ! empty( $club_email ) ) {
        echo '<br><small>' . esc_html( $club_email ) . '</small>';
    }
    echo '</td>';

    // Région
    echo '<td>' . esc_html( isset( $club->region ) ? $club->region : '' ) . '</td>';

    // Numéro d’affiliation
    echo '<td>';
    echo ! empty( $club->num_affiliation ) ? esc_html( $club->num_affiliation ) : '<em>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</em>';
    echo '</td>';

    // Statut
    $status_value = isset( $club->statut ) ? $club->statut : '';
    echo '<td>' . self::render_status_badge( $status_value ) . '</td>';

    // Licences validées
    $club_id = (int) ( $club->id ?? 0 );
    $licence_count = isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
    $licence_url = add_query_arg(
        array(
            'page' => 'ufsc-sql-licences',
            'filter_club' => $club_id,
            'filter_status' => 'valide',
            'filter_active' => 1
        ),
        admin_url( 'admin.php' )
    );
    echo '<td><a href="' . esc_url( $licence_url ) . '">' . esc_html( $licence_count ) . '</a></td>';

    // Documents
    echo '<td>' . self::render_documents_badge( $club ) . '</td>';

    // Date de création
    $date_creation = isset( $club->date_creation ) ? $club->date_creation : '';
    echo '<td>' . ( $date_creation ? esc_html( mysql2date( 'd/m/Y', $date_creation ) ) : '<em>' . esc_html__( 'Non défini', 'ufsc-clubs' ) . '</em>' ) . '</td>';

    // Actions
    echo '<td>';
    $club_id = (int) ( $club->id ?? 0 );
    $view_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=view&id=' . $club_id );
    $edit_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=edit&id=' . $club_id );
    $delete_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=ufsc_sql_delete_club&id=' . $club_id ),
        'ufsc_sql_delete_club'
    );
    echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a> ';
    echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a> ';
    echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Êtes-vous sûr de vouloir supprimer ce club ?', 'ufsc-clubs' ) ) . '\')">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</a>';
    echo '</td>';

    echo '</tr>';
}


    /**
     * Render pagination
     */
    private static function render_pagination( $current_page, $total_pages ) {
        if ( $total_pages <= 1 ) {
            return;
        }

        echo '<div class="tablenav bottom">';
        echo '<div class="tablenav-pages">';

        $base_url = remove_query_arg( 'paged' );

        // Previous page
        if ( $current_page > 1 ) {
            $prev_url = add_query_arg( 'paged', $current_page - 1, $base_url );
            echo '<a href="' . esc_url( $prev_url ) . '" class="button">&laquo; ' . esc_html__( 'Précédent', 'ufsc-clubs' ) . '</a> ';
        }

        // Page numbers
        $start = max( 1, $current_page - 2 );
        $end = min( $total_pages, $current_page + 2 );

        for ( $i = $start; $i <= $end; $i++ ) {
            if ( $i == $current_page ) {
                echo '<strong>' . $i . '</strong> ';
            } else {
                $page_url = add_query_arg( 'paged', $i, $base_url );
                echo '<a href="' . esc_url( $page_url ) . '">' . $i . '</a> ';
            }
        }

        // Next page
        if ( $current_page < $total_pages ) {
            $next_url = add_query_arg( 'paged', $current_page + 1, $base_url );
            echo '<a href="' . esc_url( $next_url ) . '" class="button">' . esc_html__( 'Suivant', 'ufsc-clubs' ) . ' &raquo;</a>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Helper methods for rendering filters
     */
    private static function render_region_filter( $selected, $columns, $clubs_table ) {
        global $wpdb;
        if ( ! self::has_column( $columns, $clubs_table, 'region' ) ) {
            echo '<select name="region" disabled="disabled">';
            echo '<option value="">' . esc_html__( '— Régions indisponibles —', 'ufsc-clubs' ) . '</option>';
            echo '</select>';
            return;
        }

        $regions = $wpdb->get_col( "SELECT DISTINCT region FROM `{$clubs_table}` WHERE region IS NOT NULL AND region != '' ORDER BY region" );

        echo '<select name="region">';
        echo '<option value="">' . esc_html__( '— Toutes les régions —', 'ufsc-clubs' ) . '</option>';
        foreach ( $regions as $region ) {
            echo '<option value="' . esc_attr( $region ) . '"' . selected( $selected, $region, false ) . '>';
            echo esc_html( $region );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_status_filter( $selected ) {
        $statuses = UFSC_SQL::statuses();

        echo '<select name="statut">';
        echo '<option value="">' . esc_html__( '— Tous les statuts —', 'ufsc-clubs' ) . '</option>';
        foreach ( $statuses as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>';
            echo esc_html( $label );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_date_filters( $from, $to ) {
        echo '<label>' . esc_html__( 'Créé du', 'ufsc-clubs' ) . '</label>';
        echo '<input type="date" name="created_from" value="' . esc_attr( $from ) . '">';
        echo '<label>' . esc_html__( 'au', 'ufsc-clubs' ) . '</label>';
        echo '<input type="date" name="created_to" value="' . esc_attr( $to ) . '">';
    }

    private static function render_quota_filters( $min, $max ) {
        echo '<label>' . esc_html__( 'Quota min', 'ufsc-clubs' ) . '</label>';
        echo '<input type="number" name="quota_min" value="' . esc_attr( $min ) . '" min="0" max="999" style="width: 80px;">';
        echo '<label>' . esc_html__( 'Quota max', 'ufsc-clubs' ) . '</label>';
        echo '<input type="number" name="quota_max" value="' . esc_attr( $max ) . '" min="0" max="999" style="width: 80px;">';
    }

    /**
     * Helper methods
     */
    private static function get_sortable_header( $column, $title, $sorting ) {
        $order = ( $sorting['orderby'] === $column && $sorting['order'] === 'asc' ) ? 'desc' : 'asc';
        $url = add_query_arg( array( 'orderby' => $column, 'order' => $order ) );

        $arrow = '';
        if ( $sorting['orderby'] === $column ) {
            $arrow = $sorting['order'] === 'asc' ? ' ↑' : ' ↓';
        }

        return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . $arrow . '</a>';
    }

    private static function render_status_badge( $status ) {
        return UFSC_Badges::render_club_badge( $status );
    }

    private static function render_documents_badge( $club ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );

        $complete_count = 0;
        $total_count = count( $doc_fields );

        foreach ( $doc_fields as $field ) {
            if ( isset( $club->$field ) && ! empty( $club->$field ) ) {
                $complete_count++;
            }
        }

        if ( $complete_count === $total_count ) {
            return '<span class="ufsc-badge badge-success" title="' . esc_attr__( 'Documents complets', 'ufsc-clubs' ) . '">' .
                   esc_html__( 'Complet', 'ufsc-clubs' ) . '</span>';
        } else {
            return '<span class="ufsc-badge badge-warning" title="' . esc_attr( sprintf( __( '%d/%d documents', 'ufsc-clubs' ), $complete_count, $total_count ) ) . '">' .
                   esc_html__( 'Incomplet', 'ufsc-clubs' ) . '</span>';
        }
    }

    /**
     * Check if a column exists.
     */
    private static function has_column( $columns, $table, $column ) {
        if ( is_array( $columns ) && ! empty( $columns ) ) {
            return in_array( $column, $columns, true );
        }

        if ( function_exists( 'ufsc_table_has_column' ) ) {
            return ufsc_table_has_column( $table, $column );
        }

        if ( function_exists( 'ufsc_table_columns' ) ) {
            $fetched = ufsc_table_columns( $table );
            return is_array( $fetched ) && in_array( $column, $fetched, true );
        }

        return true;
    }

    private static function is_valid_date( $date ) {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }

    public static function handle_bulk_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'ufsc-sql-clubs') {
            return;
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ufsc_bulk_clubs_actions')) {
            return;
        }

        if (!isset($_POST['bulk_action']) || empty($_POST['bulk_action'])) {
            return;
        }

        if (!isset($_POST['club_ids']) || empty($_POST['club_ids'])) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Aucun élément sélectionné', 'ufsc-clubs') . '</p></div>';
            });
            return; 
        }
        $settings  = UFSC_SQL::get_settings();
        $table     = $settings['table_clubs'];
        $action    = sanitize_text_field($_POST['bulk_action']);
        $item_ids  = array_map('intval', $_POST['club_ids']);
        switch ($action) {
            case 'actif':
                self::bulk_actif_items($item_ids, $table);
                break;
            case 'en_attente':
                self::bulk_pending_items($item_ids, $table);
                break;
            case 'creating':
                self::bulk_creating_items($item_ids, $table);
                break;
            case 'delete':
                $item_ids = self::bulk_delete_items($item_ids, $settings);
                break;
        }

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return;
        }
        wp_redirect(add_query_arg('processed', count($item_ids), $_POST['_wp_http_referer']));
        exit;
    }

    private static function bulk_delete_items($item_ids, $settings) {
        global $wpdb;
        $deleteds = [];
        foreach ($item_ids as $item_id) {
            $row = $wpdb->get_row( "SELECT count(*) as nb FROM `{$settings['table_licences']}` WHERE club_id = ". (int) $item_id );
            if($row->nb <= 0){
                $deleteds[] = $item_id;
                $wpdb->delete(
                    $settings['table_clubs'],
                    array('id' => $item_id),
                    array('%d')
                );
            }
            
        }
        return $deleteds;
        
    }

    private static function bulk_actif_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            $wpdb->update(
                $table,
                array('statut' => 'actif'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément actif.', '%d éléments actif.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_pending_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            $result = $wpdb->update(
                $table,
                array('statut' => 'en_attente'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément En attente.', '%d éléments En attente.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }

    private static function bulk_creating_items($item_ids, $table) {
        global $wpdb;

        foreach ($item_ids as $item_id) {
            $result = $wpdb->update(
                $table,
                array('statut' => 'en_cours_de_creation'),
                array('id' => $item_id),
                array('%s'),
                array('%d')
            );
        }

        add_action('admin_notices', function() use ($item_ids) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(_n('%d élément en cours de creation.', '%d éléments en cours de creation.', count($item_ids)), count($item_ids));
            echo '</p></div>';
        });
    }


}
