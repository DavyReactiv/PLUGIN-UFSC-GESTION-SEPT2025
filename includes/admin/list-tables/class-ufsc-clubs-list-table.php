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

        // Handle filters and search
        $filters = self::get_filters();
        $search = self::get_search_query();
        $pagination = self::get_pagination_params();
        $sorting = self::get_sorting_params();

        // Build WHERE conditions
        $where_conditions = self::build_where_conditions( $filters, $search );
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Build ORDER BY clause
        $order_clause = self::build_order_clause( $sorting );

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

        // Action buttons
        self::render_action_buttons();

        // Filters
        self::render_filters( $filters );

        // Search
        self::render_search( $search );

        // Results info
        self::render_results_info( $total_items, $pagination );

        // Main table
        self::render_clubs_table( $clubs, $sorting );

        // Pagination
        self::render_pagination( $pagination['paged'], $total_pages );

        echo '</div>';
    }

    /**
     * Get current filters
     */
    private static function get_filters() {
        return array(
            'region' => isset( $_GET['region'] ) ? sanitize_text_field( $_GET['region'] ) : '',
            'statut' => isset( $_GET['statut'] ) ? sanitize_text_field( $_GET['statut'] ) : '',
            'created_from' => isset( $_GET['created_from'] ) ? sanitize_text_field( $_GET['created_from'] ) : '',
            'created_to' => isset( $_GET['created_to'] ) ? sanitize_text_field( $_GET['created_to'] ) : '',
            'quota_min' => isset( $_GET['quota_min'] ) ? (int) $_GET['quota_min'] : 0,
            'quota_max' => isset( $_GET['quota_max'] ) ? (int) $_GET['quota_max'] : 0
        );
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
    private static function build_where_conditions( $filters, $search ) {
        global $wpdb;
        $conditions = array();

        // Search query
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $conditions[] = $wpdb->prepare(
                "(nom LIKE %s OR email LIKE %s)",
                $search_like, $search_like
            );
        }

        // Region filter
        if ( ! empty( $filters['region'] ) ) {
            $conditions[] = $wpdb->prepare( "region = %s", $filters['region'] );
        }

        // Status filter
        if ( ! empty( $filters['statut'] ) ) {
            $conditions[] = $wpdb->prepare( "statut = %s", $filters['statut'] );
        }

        // Date range filters
        if ( ! empty( $filters['created_from'] ) && self::is_valid_date( $filters['created_from'] ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) >= %s", $filters['created_from'] );
        }

        if ( ! empty( $filters['created_to'] ) && self::is_valid_date( $filters['created_to'] ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) <= %s", $filters['created_to'] );
        }

        // Quota range filters
        if ( $filters['quota_min'] > 0 ) {
            $conditions[] = $wpdb->prepare( "quota_licences >= %d", $filters['quota_min'] );
        }

        if ( $filters['quota_max'] > 0 ) {
            $conditions[] = $wpdb->prepare( "quota_licences <= %d", $filters['quota_max'] );
        }

        return $conditions;
    }

    /**
     * Build ORDER BY clause
     */
    private static function build_order_clause( $sorting ) {
        $orderby_map = array(
            'nom' => 'nom',
            'date_creation' => 'date_creation',
            'region' => 'region'
        );

        $orderby = isset( $orderby_map[ $sorting['orderby'] ] ) ? $orderby_map[ $sorting['orderby'] ] : 'date_creation';
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
        echo '<a href="' . esc_url( add_query_arg( 'export', '1' ) ) . '" class="button">';
        echo esc_html__( 'Exporter CSV', 'ufsc-clubs' );
        echo '</a>';
        echo '</p>';
    }

    /**
     * Render filters
     */
    private static function render_filters( $filters ) {
        echo '<div class="ufsc-filters-panel">';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs">';

        echo '<div class="ufsc-filters-row">';

        // Region filter
        self::render_region_filter( $filters['region'] );

        // Status filter
        self::render_status_filter( $filters['statut'] );

        // Date range filters
        self::render_date_filters( $filters['created_from'], $filters['created_to'] );

        echo '</div>';

        echo '<div class="ufsc-filters-row">';

        // Quota range filters
        self::render_quota_filters( $filters['quota_min'], $filters['quota_max'] );

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
    private static function render_clubs_table( $clubs, $sorting ) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>' . self::get_sortable_header( 'nom', __( 'Nom du club', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'region', __( 'Région', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'N° Affiliation', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Quota', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'date_creation', __( 'Créé le', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if ( $clubs ) {
            foreach ( $clubs as $club ) {
                self::render_club_row( $club );
            }
        } else {
            echo '<tr><td colspan="9">' . esc_html__( 'Aucun club trouvé.', 'ufsc-clubs' ) . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Render individual club row
     */
    private static function render_club_row( $club ) {
        echo '<tr>';
        
        // ID
        echo '<td>' . (int) $club->id . '</td>';
        
        // Name
        echo '<td><strong>' . esc_html( $club->nom ) . '</strong>';
        if ( ! empty( $club->email ) ) {
            echo '<br><small>' . esc_html( $club->email ) . '</small>';
        }
        echo '</td>';
        
        // Region
        echo '<td>' . esc_html( $club->region ) . '</td>';
        
        // Affiliation number
        echo '<td>';
        echo $club->num_affiliation ? esc_html( $club->num_affiliation ) : '<em>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</em>';
        echo '</td>';
        
        // Status
        echo '<td>' . self::render_status_badge( $club->statut ) . '</td>';
        
        // Quota
        echo '<td>';
        echo isset( $club->quota_licences ) ? (int) $club->quota_licences : '<em>' . esc_html__( 'Non défini', 'ufsc-clubs' ) . '</em>';
        echo '</td>';
        
        // Documents badge
        echo '<td>' . self::render_documents_badge( $club ) . '</td>';
        
        // Creation date
        echo '<td>' . esc_html( mysql2date( 'd/m/Y', $club->date_creation ) ) . '</td>';
        
        // Actions
        echo '<td>';
        $view_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=view&id=' . $club->id );
        $edit_url = admin_url( 'admin.php?page=ufsc-sql-clubs&action=edit&id=' . $club->id );
        $delete_url = wp_nonce_url( 
            admin_url( 'admin-post.php?action=ufsc_sql_delete_club&id=' . $club->id ), 
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
    private static function render_region_filter( $selected ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $regions = $wpdb->get_col( "SELECT DISTINCT region FROM `{$settings['table_clubs']}` WHERE region IS NOT NULL AND region != '' ORDER BY region" );

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

    private static function is_valid_date( $date ) {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }
}