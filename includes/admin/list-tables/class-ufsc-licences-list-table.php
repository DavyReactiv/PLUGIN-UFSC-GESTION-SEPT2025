<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Licences List Table
 * Enhanced admin list with filters, search, and pagination
 */
class UFSC_Licences_List_Table {

    /**
     * Render enhanced licences list
     */
    public static function render() {
        global $wpdb;
        
        $settings = UFSC_SQL::get_settings();
        $licences_table = $settings['table_licences'];
        $clubs_table = $settings['table_clubs'];

        // Handle filters and search
        $filters = self::get_filters();
        $search = self::get_search_query();
        $pagination = self::get_pagination_params();
        $sorting = self::get_sorting_params();

        $licence_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $licences_table ) : array();
        $club_columns    = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : array();

        // Build WHERE conditions
        $where_conditions = self::build_where_conditions(
            $filters,
            $search,
            $licence_columns,
            $licences_table,
            $club_columns,
            $clubs_table
        );
        $where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

        // Build ORDER BY clause
        $order_clause = self::build_order_clause( $sorting, $licence_columns, $licences_table );

        // Get total count for pagination
        $total_query = "SELECT COUNT(*) FROM `{$licences_table}` l LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id {$where_clause}";
        $total_items = (int) $wpdb->get_var( $total_query );

        // Get licences with pagination
        $offset = ( $pagination['paged'] - 1 ) * $pagination['per_page'];
        $licences_query = "
            SELECT l.*, 
                   c.nom as club_nom, 
                   c.region as club_region,
                   CONCAT(l.prenom, ' ', l.nom_licence) as full_name
            FROM `{$licences_table}` l 
            LEFT JOIN `{$clubs_table}` c ON l.club_id = c.id 
            {$where_clause} 
            {$order_clause} 
            LIMIT {$pagination['per_page']} OFFSET {$offset}
        ";
        
        $licences = $wpdb->get_results( $licences_query );

        // Calculate pagination
        $total_pages = ceil( $total_items / $pagination['per_page'] );

        // Render the page
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Licences (SQL)', 'ufsc-clubs' ) . '</h1>';

        // Action buttons
        self::render_action_buttons();

        // Filters
        self::render_filters( $filters );

        // Search
        self::render_search( $search );

        // Results info
        self::render_results_info( $total_items, $pagination );

        // Main table
        self::render_licences_table( $licences, $sorting );

        // Pagination
        self::render_pagination( $pagination['paged'], $total_pages );

        echo '</div>';
    }

    /**
     * Get current filters
     */
    private static function get_filters() {
        return array(
            'club_id' => isset( $_GET['club_id'] ) ? (int) $_GET['club_id'] : 0,
            'club_region' => isset( $_GET['club_region'] ) ? sanitize_text_field( $_GET['club_region'] ) : '',
            'statut' => isset( $_GET['statut'] ) ? sanitize_text_field( $_GET['statut'] ) : '',
            'payment_status' => isset( $_GET['payment_status'] ) ? sanitize_text_field( $_GET['payment_status'] ) : '',
            'categorie' => isset( $_GET['categorie'] ) ? sanitize_text_field( $_GET['categorie'] ) : '',
            'sexe' => isset( $_GET['sexe'] ) ? sanitize_text_field( $_GET['sexe'] ) : '',
            'medical' => isset( $_GET['medical'] ) ? (int) $_GET['medical'] : 0,
            'created_from' => isset( $_GET['created_from'] ) ? sanitize_text_field( $_GET['created_from'] ) : '',
            'created_to' => isset( $_GET['created_to'] ) ? sanitize_text_field( $_GET['created_to'] ) : ''
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
        $allowed_orderby = array( 'last_name', 'date_creation', 'date_achat', 'date_modification', 'numero_licence_delegataire' );
        $allowed_order = array( 'asc', 'desc' );
        
        return array(
            'orderby' => isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], $allowed_orderby ) ? $_GET['orderby'] : 'date_creation',
            'order' => isset( $_GET['order'] ) && in_array( $_GET['order'], $allowed_order ) ? $_GET['order'] : 'desc'
        );
    }

    /**
     * Build WHERE conditions
     */
    private static function build_where_conditions( $filters, $search, $columns, $licences_table, $club_columns, $clubs_table ) {
        global $wpdb;
        $conditions = array();

        // Search query
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_columns = array( 'prenom', 'nom_licence', 'email', 'numero_licence_delegataire' );
            $search_parts   = array();
            $search_values  = array();

            foreach ( $search_columns as $column ) {
                if ( self::has_column( $columns, $licences_table, $column ) ) {
                    $search_parts[]  = "l.{$column} LIKE %s";
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

        // Club filter
        if ( $filters['club_id'] > 0 ) {
            $conditions[] = $wpdb->prepare( "l.club_id = %d", $filters['club_id'] );
        }

        // Region filter
        if ( ! empty( $filters['club_region'] ) && self::has_column( $club_columns, $clubs_table, 'region' ) ) {
            $conditions[] = $wpdb->prepare( "c.region = %s", $filters['club_region'] );
        }

        // Status filter
        if ( ! empty( $filters['statut'] ) && self::has_column( $columns, $licences_table, 'statut' ) ) {
            $normalized_status = function_exists( 'ufsc_normalize_license_status' )
                ? ufsc_normalize_license_status( $filters['statut'] )
                : $filters['statut'];

            if ( 'brouillon' === $normalized_status ) {
                $conditions[] = "(l.statut IS NULL OR l.statut = '' OR l.statut IN ('brouillon','draft'))";
            } else {
                $conditions[] = $wpdb->prepare( "l.statut = %s", $normalized_status );
            }
        }

        // Payment status filter
        if ( ! empty( $filters['payment_status'] ) && self::has_column( $columns, $licences_table, 'payment_status' ) ) {
            $conditions[] = $wpdb->prepare( "l.payment_status = %s", $filters['payment_status'] );
        }

        // Category filter
        if ( ! empty( $filters['categorie'] ) && self::has_column( $columns, $licences_table, 'categorie' ) ) {
            $conditions[] = $wpdb->prepare( "l.categorie = %s", $filters['categorie'] );
        }

        // Gender filter
        if ( ! empty( $filters['sexe'] ) && self::has_column( $columns, $licences_table, 'sexe' ) ) {
            $conditions[] = $wpdb->prepare( "l.sexe = %s", $filters['sexe'] );
        }

        // Medical certificate filter
        if ( $filters['medical'] == 1 ) {
            if ( self::has_column( $columns, $licences_table, 'attestation_url' ) ) {
                $conditions[] = "l.attestation_url IS NOT NULL AND l.attestation_url != ''";
            }
        }

        // Date range filters
        if ( ! empty( $filters['created_from'] ) && self::is_valid_date( $filters['created_from'] )
            && self::has_column( $columns, $licences_table, 'date_creation' )
        ) {
            $conditions[] = $wpdb->prepare( "DATE(l.date_creation) >= %s", $filters['created_from'] );
        }

        if ( ! empty( $filters['created_to'] ) && self::is_valid_date( $filters['created_to'] )
            && self::has_column( $columns, $licences_table, 'date_creation' )
        ) {
            $conditions[] = $wpdb->prepare( "DATE(l.date_creation) <= %s", $filters['created_to'] );
        }

        $scope_condition = '';
        if ( self::has_column( $club_columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'c' );
        } elseif ( self::has_column( $columns, $licences_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region', 'l' );
        }
        if ( $scope_condition ) {
            $conditions[] = $scope_condition;
        }

        return $conditions;
    }

    /**
     * Build ORDER BY clause
     */
    private static function build_order_clause( $sorting, $columns, $licences_table ) {
        $orderby_map = array(
            'last_name' => 'l.nom_licence',
            'date_creation' => 'l.date_creation',
            'date_achat' => 'l.date_achat',
            'date_modification' => 'l.date_modification',
            'numero_licence_delegataire' => 'l.numero_licence_delegataire'
        );

        $requested = isset( $orderby_map[ $sorting['orderby'] ] ) ? $sorting['orderby'] : 'date_creation';
        $fallback  = self::has_column( $columns, $licences_table, 'date_creation' ) ? 'date_creation' : 'id';

        if ( ! self::has_column( $columns, $licences_table, $requested === 'last_name' ? 'nom_licence' : $requested ) ) {
            $requested = $fallback;
        }

        $orderby = isset( $orderby_map[ $requested ] ) ? $orderby_map[ $requested ] : 'l.id';
        $order = strtoupper( $sorting['order'] );

        return "ORDER BY {$orderby} {$order}";
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

    /**
     * Render action buttons
     */
    private static function render_action_buttons() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<p>';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-licences&action=new' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Ajouter une licence', 'ufsc-clubs' );
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
        echo '<input type="hidden" name="page" value="ufsc-sql-licences">';

        echo '<div class="ufsc-filters-row">';

        // Club filter
        self::render_club_filter( $filters['club_id'] );

        // Region filter
        self::render_region_filter( $filters['club_region'] );

        // Status filter
        self::render_status_filter( $filters['statut'] );

        // Payment status filter
        self::render_payment_status_filter( $filters['payment_status'] );

        echo '</div>';

        echo '<div class="ufsc-filters-row">';

        // Category filter
        self::render_category_filter( $filters['categorie'] );

        // Gender filter
        self::render_gender_filter( $filters['sexe'] );

        // Medical filter
        self::render_medical_filter( $filters['medical'] );

        echo '</div>';

        echo '<div class="ufsc-filters-row">';

        // Date range filters
        self::render_date_filters( $filters['created_from'], $filters['created_to'] );

        echo '</div>';

        echo '<div class="ufsc-filters-actions">';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'secondary', null, false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-licences' ) ) . '" class="button">' . esc_html__( 'Reset', 'ufsc-clubs' ) . '</a>';
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
        echo '<input type="hidden" name="page" value="ufsc-sql-licences">';
        
        // Preserve current filters
        foreach ( self::get_filters() as $key => $value ) {
            if ( ! empty( $value ) ) {
                echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
            }
        }

        echo '<input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Rechercher par nom, prénom, email, numéro...', 'ufsc-clubs' ) . '">';
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
            esc_html__( 'Affichage de %d à %d sur %d licences', 'ufsc-clubs' ),
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
     * Render main licences table
     */
    private static function render_licences_table( $licences, $sorting ) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . self::get_sortable_header( 'last_name', __( 'Nom', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Email', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'numero_licence_delegataire', __( 'N° Licence', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Paiement', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Médical', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . self::get_sortable_header( 'date_creation', __( 'Créé le', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th class="column-actions">' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if ( $licences ) {
            foreach ( $licences as $licence ) {
                self::render_licence_row( $licence );
            }
        } else {
            echo '<tr><td colspan="10">' . esc_html__( 'Aucune licence trouvée.', 'ufsc-clubs' ) . '</td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
    }

    /**
     * Render individual licence row
     */
    private static function render_licence_row( $licence ) {
        echo '<tr>';
        
        // Name
        echo '<td><strong>' . esc_html( $licence->full_name ) . '</strong></td>';
        
        // Club
        echo '<td>';
        if ( $licence->club_nom ) {
            echo esc_html( $licence->club_nom );
            if ( $licence->club_region ) {
                echo '<br><small>' . esc_html( $licence->club_region ) . '</small>';
            }
        } else {
            echo '<em>' . esc_html__( 'Aucun club', 'ufsc-clubs' ) . '</em>';
        }
        echo '</td>';
        
        // Email
        echo '<td>' . esc_html( $licence->email ) . '</td>';
        
        // License number
        echo '<td>';
        echo $licence->numero_licence_delegataire ? esc_html( $licence->numero_licence_delegataire ) : '<em>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</em>';
        echo '</td>';
        
        // Status
        echo '<td>' . self::render_status_badge( $licence->statut ) . '</td>';
        
        // Season
        $season = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : '';
        echo '<td>' . esc_html( $season ? $season : '—' ) . '</td>';

        // Payment status
        echo '<td>' . self::render_payment_status_badge( $licence->payment_status ) . '</td>';
        
        // Medical
        echo '<td>';
        if ( ! empty( $licence->attestation_url ) ) {
            echo '<span class="dashicons dashicons-yes-alt" style="color: green;" title="' . esc_attr__( 'Certificat médical fourni', 'ufsc-clubs' ) . '"></span>';
        } else {
            echo '<span class="dashicons dashicons-minus" style="color: #ccc;" title="' . esc_attr__( 'Aucun certificat', 'ufsc-clubs' ) . '"></span>';
        }
        echo '</td>';
        
        // Creation date
        echo '<td>' . esc_html( mysql2date( 'd/m/Y', $licence->date_creation ) ) . '</td>';
        
        // Actions
        echo '<td class="column-actions">';
        $view_url = admin_url( 'admin.php?page=ufsc-sql-licences&action=view&id=' . $licence->id );
        $edit_url = admin_url( 'admin.php?page=ufsc-sql-licences&action=edit&id=' . $licence->id );
        echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a> ';
        if ( current_user_can( 'manage_options' ) ) {
            echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a>';
        }
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
    private static function render_club_filter( $selected ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $club_scope_condition = UFSC_Scope::build_scope_condition( 'region' );
        $club_where = $club_scope_condition ? 'WHERE ' . $club_scope_condition : '';
        $clubs = $wpdb->get_results( "SELECT id, nom FROM `{$settings['table_clubs']}` {$club_where} ORDER BY nom" );

        echo '<select name="club_id">';
        echo '<option value="">' . esc_html__( '— Tous les clubs —', 'ufsc-clubs' ) . '</option>';
        foreach ( $clubs as $club ) {
            echo '<option value="' . esc_attr( $club->id ) . '"' . selected( $selected, $club->id, false ) . '>';
            echo esc_html( $club->nom );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_region_filter( $selected ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $scope_slug  = UFSC_Scope::get_user_scope_region();
        $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( $scope_label ) {
            $regions = array( $scope_label );
        } else {
            $regions = $wpdb->get_col( "SELECT DISTINCT region FROM `{$settings['table_clubs']}` WHERE region IS NOT NULL AND region != '' ORDER BY region" );
        }

        echo '<select name="club_region">';
        if ( ! $scope_label ) {
            echo '<option value="">' . esc_html__( '— Toutes les régions —', 'ufsc-clubs' ) . '</option>';
        }
        foreach ( $regions as $region ) {
            echo '<option value="' . esc_attr( $region ) . '"' . selected( $selected, $region, false ) . '>';
            echo esc_html( $region );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_status_filter( $selected ) {
        $statuses = function_exists( 'ufsc_get_license_statuses' )
            ? ufsc_get_license_statuses()
            : UFSC_SQL::statuses();

        $selected = function_exists( 'ufsc_normalize_license_status' )
            ? ufsc_normalize_license_status( $selected )
            : $selected;

        echo '<select name="statut">';
        echo '<option value="">' . esc_html__( '— Tous les statuts —', 'ufsc-clubs' ) . '</option>';
        foreach ( $statuses as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>';
            echo esc_html( $label );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_payment_status_filter( $selected ) {
        $statuses = array(
            'pending' => __( 'En attente', 'ufsc-clubs' ),
            'awaiting_transfer' => __( 'En attente de virement', 'ufsc-clubs' ),
            'paid' => __( 'Payé', 'ufsc-clubs' ),
            'failed' => __( 'Échec', 'ufsc-clubs' ),
            'refunded' => __( 'Remboursé', 'ufsc-clubs' )
        );

        echo '<select name="payment_status">';
        echo '<option value="">' . esc_html__( '— Tous les paiements —', 'ufsc-clubs' ) . '</option>';
        foreach ( $statuses as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>';
            echo esc_html( $label );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_category_filter( $selected ) {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $categories = $wpdb->get_col( "SELECT DISTINCT categorie FROM `{$settings['table_licences']}` WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie" );

        echo '<select name="categorie">';
        echo '<option value="">' . esc_html__( '— Toutes les catégories —', 'ufsc-clubs' ) . '</option>';
        foreach ( $categories as $category ) {
            echo '<option value="' . esc_attr( $category ) . '"' . selected( $selected, $category, false ) . '>';
            echo esc_html( $category );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_gender_filter( $selected ) {
        $genders = array(
            'M' => __( 'Masculin', 'ufsc-clubs' ),
            'F' => __( 'Féminin', 'ufsc-clubs' )
        );

        echo '<select name="sexe">';
        echo '<option value="">' . esc_html__( '— Tous les genres —', 'ufsc-clubs' ) . '</option>';
        foreach ( $genders as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>';
            echo esc_html( $label );
            echo '</option>';
        }
        echo '</select>';
    }

    private static function render_medical_filter( $selected ) {
        echo '<select name="medical">';
        echo '<option value="">' . esc_html__( '— Certificat médical —', 'ufsc-clubs' ) . '</option>';
        echo '<option value="1"' . selected( $selected, 1, false ) . '>' . esc_html__( 'Avec certificat', 'ufsc-clubs' ) . '</option>';
        echo '</select>';
    }

    private static function render_date_filters( $from, $to ) {
        echo '<input type="date" name="created_from" value="' . esc_attr( $from ) . '" placeholder="' . esc_attr__( 'Du', 'ufsc-clubs' ) . '">';
        echo '<input type="date" name="created_to" value="' . esc_attr( $to ) . '" placeholder="' . esc_attr__( 'Au', 'ufsc-clubs' ) . '">';
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
        $status = function_exists( 'ufsc_normalize_license_status' ) ? ufsc_normalize_license_status( $status ) : $status;
        return UFSC_Badges::render_licence_badge( $status );
    }

    private static function render_payment_status_badge( $status ) {
        $badge_classes = array(
            'paid' => 'badge-success',
            'pending' => 'badge-warning',
            'awaiting_transfer' => 'badge-info',
            'failed' => 'badge-danger',
            'refunded' => 'badge-secondary'
        );

        $class = isset( $badge_classes[ $status ] ) ? $badge_classes[ $status ] : 'badge-secondary';
        return '<span class="ufsc-badge ' . esc_attr( $class ) . '">' . esc_html( $status ) . '</span>';
    }

    private static function is_valid_date( $date ) {
        $d = DateTime::createFromFormat( 'Y-m-d', $date );
        return $d && $d->format( 'Y-m-d' ) === $date;
    }
}
