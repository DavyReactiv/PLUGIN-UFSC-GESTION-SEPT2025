<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Clubs List Table
 * Enhanced admin list with filters, search, and pagination
 */
class UFSC_Clubs_List_Table {
	private static function get_selected_season_filter() {
		$value = self::get_query_value( 'season' );
		if ( in_array( $value, array( 'all', 'permanent', '__archives' ), true ) ) { return $value; }
		$value = function_exists( 'ufsc_normalize_season_reference' ) ? ufsc_normalize_season_reference( $value ) : $value;
		return preg_match( '/^\d{4}-\d{4}$/', $value ) ? $value : '';
	}

	private static function get_season_context_label() {
		$selected = self::get_selected_season_filter();
		if ( 'all' === $selected ) { return __( 'Toutes les saisons (statuts annuels séparés)', 'ufsc-clubs' ); }
		if ( '__archives' === $selected ) { return __( 'Archives uniquement (statuts annuels séparés)', 'ufsc-clubs' ); }
		return self::get_admin_season_label();
	}
    private static function get_current_request_url() {
        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        if ( ! is_string( $request_uri ) || '' === $request_uri ) {
            return admin_url( 'admin.php?page=ufsc-sql-clubs' );
        }

        return $request_uri;
    }

    private static function get_admin_season_label() {
		$requested = self::get_query_value( 'season' );
		if ( preg_match( '/^\d{4}-\d{4}$/', $requested ) ) {
			return $requested;
		}
		if ( class_exists( 'UFSC_Season_Service' ) ) {
			return (string) UFSC_Season_Service::get_current_season();
		}
        if ( function_exists( 'ufsc_get_admin_current_season_label' ) ) {
            return (string) ufsc_get_admin_current_season_label();
        }

        return __( 'saison en cours', 'ufsc-clubs' );
    }

	private static function get_season_options() {
		$current  = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : self::get_admin_season_label();
		$previous = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_previous_season() : '';
		$available = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_available_seasons() : array();
		$options = array( '' => sprintf( __( 'Saison actuelle : %s', 'ufsc-clubs' ), $current ) );
		if ( $previous ) { $options[ $previous ] = sprintf( __( 'Saison précédente : %s', 'ufsc-clubs' ), $previous ); }
		$options['all'] = __( 'Toutes les saisons', 'ufsc-clubs' );
		$options['permanent'] = __( 'Tous les clubs enregistrés', 'ufsc-clubs' );
		$options['__archives'] = __( 'Archives uniquement', 'ufsc-clubs' );
		foreach ( array_unique( array_filter( (array) $available ) ) as $season ) {
			if ( $season !== $current && $season !== $previous ) { $options[ $season ] = $season; }
		}
		return $options;
	}

    /**
     * Render enhanced clubs list
     */
    public static function render() {
        global $wpdb;

        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];
        if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
            $clubs_table = ufsc_sanitize_table_name( $clubs_table );
        }
        $club_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : array();
        $licence_counts = UFSC_CL_Utils::get_valid_licence_counts_by_club();

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
        echo '<div class="wrap ufsc-clubs-admin-page">';
        if ( class_exists( 'UFSC_SQL_Admin' ) ) {
            UFSC_SQL_Admin::render_admin_quick_nav();
        }
        echo '<div class="ufsc-clubs-shell">';
        echo '<div class="ufsc-clubs-hero">';
        echo '<div>';
        echo '<span class="ufsc-clubs-kicker">' . esc_html__( 'Administration UFSC', 'ufsc-clubs' ) . '</span>';
        echo '<h1 class="ufsc-admin-title">' . esc_html__( 'Clubs UFSC — Affiliations et suivi administratif', 'ufsc-clubs' ) . '</h1>';
        echo '<p class="ufsc-admin-subtitle">' . esc_html__( 'Retrouvez ici l’ensemble des clubs enregistrés, leur région, leur statut d’affiliation, le nombre de licences associées et l’état des documents administratifs. Cette page permet de suivre les clubs actifs, en attente ou à renouveler.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
        echo '<div class="ufsc-season-pill"><span>' . esc_html__( 'Saison affichée', 'ufsc-clubs' ) . '</span><strong>' . esc_html( self::get_season_context_label() ) . '</strong></div>';
        echo '</div>';
        echo '<div class="ufsc-renewal-notice"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Renouvellement des affiliations : à chaque nouvelle saison, les clubs devront confirmer ou renouveler leur affiliation afin de maintenir leurs licences actives.', 'ufsc-clubs' ) . '</p></div>';
        if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
            $allowed_regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
            if ( empty( $allowed_regions ) ) {
                echo '<div class="notice notice-warning"><p>' . esc_html__( 'Aucune région n’est associée à votre compte. Contactez un administrateur UFSC.', 'ufsc-clubs' ) . '</p></div>';
            } else {
                echo '<div class="notice notice-info"><p>' . esc_html__( 'Résultats filtrés selon vos régions UFSC autorisées.', 'ufsc-clubs' ) . '</p></div>';
            }
        }

        // Affichage des notices
        if ( '1' === self::get_query_value( 'updated', 'key' ) ) {
            echo UFSC_CL_Utils::show_success(__('Club enregistré avec succès', 'ufsc-clubs'));
        }
        if ( '1' === self::get_query_value( 'deleted', 'key' ) ) {
            $deleted_id = absint( self::get_query_value( 'deleted_id' ) );
            echo UFSC_CL_Utils::show_success(__('Le club #'.$deleted_id.' a été supprimé.', 'ufsc-clubs'));
        }
        if ( '' !== self::get_query_value( 'error' ) ) {
            echo UFSC_CL_Utils::show_error( self::get_query_value( 'error' ) );
        }

        // Action buttons
        self::render_action_buttons();

        self::render_statistics_cards( $club_columns, $clubs_table, $licence_counts );

        self::render_historical_archive_notice( $filters, $club_columns, $clubs_table );

        // Filters and search
        self::render_filters( $filters, $club_columns, $clubs_table, $search );

        self::render_quick_filters( $filters );

        self::render_active_filters_summary( $filters, $search );
        self::render_debug_season_query_diagnostic( $filters, $clubs_table, $total_query, $clubs_query, $where_clause, $total_items );

        //Action Grop
        //self::bulck_action_grop_by_club();

        // Results info
        self::render_results_info( $total_items, $pagination );

        // Main table
        self::render_clubs_table( $clubs, $sorting, $licence_counts );

        // Pagination
        self::render_pagination( $pagination['paged'], $total_pages );

        echo '</div>';
        echo '</div>';
    }



    private static function get_request_value( $source, $key, $type = 'text' ) {
        if ( ! is_array( $source ) || ! isset( $source[ $key ] ) ) {
            return '';
        }
        $value = wp_unslash( $source[ $key ] );
        if ( is_array( $value ) || null === $value ) {
            return '';
        }
        $value = (string) $value;
        return 'key' === $type ? sanitize_key( $value ) : sanitize_text_field( $value );
    }

    private static function get_query_value( $key, $type = 'text' ) {
        return self::get_request_value( $_GET, $key, $type );
    }

    /**
     * Get current filters
     */
    private static function get_filters() {
        $filters = array(
            'region' => self::get_query_value( 'region' ),
            'statut' => self::get_query_value( 'statut' ),
            'created_from' => self::get_query_value( 'created_from' ),
            'created_to' => self::get_query_value( 'created_to' ),
            'doc_status' => self::get_query_value( 'doc_status', 'key' ),
            'affiliation_status' => self::get_query_value( 'affiliation_status', 'key' ),
            'licence_range' => self::get_query_value( 'licence_range', 'key' ),
            'season' => self::get_query_value( 'season' ),
            'archive_scope' => self::get_query_value( 'archive_scope', 'key' ),
            'club_view' => self::get_query_value( 'club_view', 'key' ),
            'kpi_filter' => self::get_query_value( 'kpi_filter', 'key' )
        );

        return $filters;
    }

    /**
     * Get search query
     */
    private static function get_search_query() {
        return self::get_query_value( 'q' );
    }

    /**
     * Get pagination parameters
     */
    private static function get_pagination_params() {
        $per_page_options = array( 20, 50, 100 );
        $requested_per_page = absint( self::get_query_value( 'per_page' ) );
        $per_page = in_array( $requested_per_page, $per_page_options, true ) ? $requested_per_page : 20;

        return array(
            'paged' => max( 1, absint( self::get_query_value( 'paged' ) ) ),
            'per_page' => $per_page
        );
    }

    /**
     * Get sorting parameters
     */
    private static function get_sorting_params() {
        $allowed_orderby = array( 'nom', 'date_creation', 'region' );
        $allowed_order = array( 'asc', 'desc' );
        $requested_orderby = self::get_query_value( 'orderby', 'key' );
        $requested_order = self::get_query_value( 'order', 'key' );

        return array(
            'orderby' => in_array( $requested_orderby, $allowed_orderby, true ) ? $requested_orderby : 'date_creation',
            'order' => in_array( $requested_order, $allowed_order, true ) ? $requested_order : 'desc'
        );
    }

    /**
     * Expand common legacy club status aliases while preserving exact filtering for unknown values.
     */
    private static function get_status_filter_values( $status ) {
        $normalized = sanitize_key( (string) $status );
        if ( in_array( $normalized, array( 'actif', 'active', 'valide', 'validated' ), true ) ) {
            return array( 'actif', 'active', 'valide', 'validated' );
        }
        if ( in_array( $normalized, array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' ), true ) ) {
            return array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' );
        }
        if ( in_array( $normalized, array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' ), true ) ) {
            return array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' );
        }
        return array( (string) $status );
    }



	/** Build exact annual-affiliation EXISTS/NOT EXISTS conditions shared by KPI cards and list filters. */
	private static function get_annual_affiliation_condition( $clubs_table, $season, $statuses, $mode = 'exists', $require_missing_number = false ) {
		global $wpdb;
		$affiliations_table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_annual_affiliations_table() : ( class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliations_table() : $wpdb->prefix . 'ufsc_affiliations_seasons' );
		if ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $affiliations_table ) ) {
			return 'not_exists' === $mode ? '1=1' : '0=1';
		}
		$statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $statuses ) ) );
		if ( empty( $statuses ) ) { return '0=1'; }
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params = array_merge( array( $season ), $statuses );
		$number_sql = $require_missing_number ? " AND (a.num_affiliation IS NULL OR a.num_affiliation = '')" : '';
		$sql = $wpdb->prepare( "SELECT 1 FROM `{$affiliations_table}` a WHERE a.club_id = `{$clubs_table}`.id AND a.season = %s AND LOWER(a.status) IN ({$placeholders}){$number_sql}", $params );
		return ( 'not_exists' === $mode ? 'NOT EXISTS' : 'EXISTS' ) . " ({$sql})";
	}

	/** Historical activity condition: annual row, licence evidence or legacy club season evidence. */
	private static function get_historical_activity_condition( $clubs_table, $season ) {
		$evidence = self::get_season_evidence_conditions( $clubs_table, $season );
		return ! empty( $evidence ) ? '(' . implode( ' OR ', $evidence ) . ')' : '1=1';
	}

	private static function get_admin_kpi_filter_condition( $filter, $columns, $clubs_table, $season ) {
		$active_statuses = array( 'active', 'validated', 'actif', 'valide' );
		$pending_statuses = array( 'pending_payment', 'pending_validation', 'correction_required', 'pending', 'en_attente' );
		if ( 'affiliations_active' === $filter ) {
			return self::get_annual_affiliation_condition( $clubs_table, $season, $active_statuses );
		}
		if ( 'affiliations_pending' === $filter ) {
			return self::get_annual_affiliation_condition( $clubs_table, $season, $pending_statuses );
		}
		if ( 'renewals' === $filter ) {
			// A renewal is actionable only when no validated affiliation and no
			// already-open request exists for the selected season.
			return self::get_annual_affiliation_condition( $clubs_table, $season, array_merge( $active_statuses, $pending_statuses ), 'not_exists' ) . ' AND ' . self::get_historical_activity_condition( $clubs_table, $season );
		}
		if ( 'documents_incomplete' === $filter ) {
			$doc_fields = self::get_available_document_fields( $columns, $clubs_table );
			if ( empty( $doc_fields ) ) { return '0=1'; }
			$doc_conditions = array();
			foreach ( $doc_fields as $field ) { $doc_conditions[] = "(`{$field}` IS NOT NULL AND `{$field}` != '')"; }
			return 'NOT (' . implode( ' AND ', $doc_conditions ) . ')';
		}
		if ( 'annual_numbers_missing' === $filter ) {
			return self::get_annual_affiliation_condition( $clubs_table, $season, $active_statuses, 'exists', true );
		}
		return '';
	}

    /**
     * Build WHERE conditions
     */
    private static function build_where_conditions( $filters, $search, $columns, $clubs_table ) {
        global $wpdb;
        $conditions = array();

        if ( class_exists( 'UFSC_Storage_Resolver' ) ) {
            $conditions[] = UFSC_Storage_Resolver::not_deleted_sql( $clubs_table );
        }

        if ( ! empty( $filters['kpi_filter'] ) ) {
            $kpi_condition = self::get_admin_kpi_filter_condition( $filters['kpi_filter'], $columns, $clubs_table, self::get_admin_season_label() );
            if ( '' !== $kpi_condition ) {
                $conditions[] = $kpi_condition;
            }
        }

        // Search query
        if ( ! empty( $search ) ) {
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $search_parts = array();
            $search_values = array();

            foreach ( array( 'nom', 'email', 'num_affiliation', 'ville' ) as $column ) {
                if ( self::has_column( $columns, $clubs_table, $column ) ) {
                    $search_parts[]  = "{$column} LIKE %s";
                    $search_values[] = $search_like;
                }
            }

			// Annual affiliation numbers live in the annual table, not on every
			// legacy clubs table. Keep this search read-only and schema-safe.
			$affiliations_table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_annual_affiliations_table() : '';
			if ( $affiliations_table && ( ! function_exists( 'ufsc_table_exists' ) || ufsc_table_exists( $affiliations_table ) ) ) {
				$affiliation_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $affiliations_table ) : array();
				if ( in_array( 'club_id', $affiliation_columns, true ) && in_array( 'num_affiliation', $affiliation_columns, true ) ) {
					$search_parts[]  = "EXISTS (SELECT 1 FROM `{$affiliations_table}` aq WHERE aq.club_id = `{$clubs_table}`.id AND aq.num_affiliation LIKE %s)";
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
            $status_values = self::get_status_filter_values( $filters['statut'] );
            if ( count( $status_values ) > 1 ) {
                $placeholders = implode( ',', array_fill( 0, count( $status_values ), '%s' ) );
                $conditions[] = $wpdb->prepare( "statut IN ({$placeholders})", $status_values );
            } else {
                $conditions[] = $wpdb->prepare( "statut = %s", $filters['statut'] );
            }
        }

        // Date range filters
        if ( ! empty( $filters['created_from'] ) && self::is_valid_date( $filters['created_from'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) >= %s", $filters['created_from'] );
        }

        if ( ! empty( $filters['created_to'] ) && self::is_valid_date( $filters['created_to'] ) && self::has_column( $columns, $clubs_table, 'date_creation' ) ) {
            $conditions[] = $wpdb->prepare( "DATE(date_creation) <= %s", $filters['created_to'] );
        }

        $doc_fields = self::get_available_document_fields( $columns, $clubs_table );
        if ( ! empty( $doc_fields ) && ! empty( $filters['doc_status'] ) ) {
            $doc_conditions = array();
            foreach ( $doc_fields as $field ) {
                $doc_conditions[] = "(`{$field}` IS NOT NULL AND `{$field}` != '')";
            }
            if ( 'complete' === $filters['doc_status'] ) {
                $conditions[] = '(' . implode( ' AND ', $doc_conditions ) . ')';
            } elseif ( 'incomplete' === $filters['doc_status'] ) {
                $conditions[] = 'NOT (' . implode( ' AND ', $doc_conditions ) . ')';
            }
        }

        if ( ! empty( $filters['affiliation_status'] ) && self::has_verified_column( $columns, $clubs_table, 'num_affiliation' ) ) {
            if ( 'assigned' === $filters['affiliation_status'] ) {
                $conditions[] = "(num_affiliation IS NOT NULL AND num_affiliation != '')";
            } elseif ( 'missing' === $filters['affiliation_status'] ) {
                $conditions[] = "(num_affiliation IS NULL OR num_affiliation = '')";
            }
        }

        $licence_expression = self::get_licence_count_expression( $clubs_table );
        if ( '' !== $licence_expression && ! empty( $filters['licence_range'] ) ) {
            if ( 'zero' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' = 0';
            } elseif ( 'one_to_nine' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' BETWEEN 1 AND 9';
            } elseif ( 'ten_plus' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' >= 10';
            } elseif ( 'under_ten' === $filters['licence_range'] ) {
                $conditions[] = $licence_expression . ' < 10';
            }
        }

        // A season view contains annual records only. Permanent historical
        // clubs without a record are available explicitly in the renewal view.
		$selected_filter = self::get_selected_season_filter();
		$selected_season = '' !== $selected_filter ? $selected_filter : self::get_admin_season_label();
		// Business KPI views already contain their complete season condition.
		// Adding the implicit default season-evidence condition here used to
		// intersect them with stale state and was the KPI/list mismatch cause.
		if ( empty( $filters['kpi_filter'] ) && ! in_array( $selected_filter, array( 'all', 'permanent', '__archives' ), true ) ) {
			$season_evidence = self::get_season_evidence_conditions( $clubs_table, $selected_season );
			$archive_scope = sanitize_key( (string) ( $filters['archive_scope'] ?? '' ) );
			if ( 'renewals' === ( $filters['club_view'] ?? '' ) ) {
				$affiliations_table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_annual_affiliations_table() : ( class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliations_table() : '' );
				$current_for_renewal = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : self::get_admin_season_label();
				$active_current = self::get_active_affiliation_not_exists_sql( $clubs_table, $affiliations_table, $current_for_renewal );
				$conditions[] = ! empty( $season_evidence ) ? $active_current . ' AND (' . implode( ' OR ', $season_evidence ) . ')' : $active_current;
			} elseif ( 'permanent' !== ( $filters['club_view'] ?? '' ) && 'all_historical' !== $archive_scope ) {
				$conditions[] = ! empty( $season_evidence ) ? '(' . implode( ' OR ', $season_evidence ) . ')' : '0=1';
			}
		}

        if ( self::has_column( $columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) {
                $conditions[] = $scope_condition;
            }

            if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
                $allowed_regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
                if ( empty( $allowed_regions ) ) {
                    $conditions[] = '1 = 0';
                } else {
                    $placeholders = implode( ',', array_fill( 0, count( $allowed_regions ), '%s' ) );
                    $conditions[] = $wpdb->prepare( "region IN ({$placeholders})", $allowed_regions );
                }
            }
        }

        return $conditions;
    }


    /**
     * Check a SQL column only when its presence can be verified.
     *
     * The generic has_column() keeps a permissive fallback for legacy filters.
     * New optional SQL filters/statistics must be stricter so they are skipped
     * instead of referencing a missing column on older installations.
     */
    private static function has_verified_column( $columns, $table, $column ) {
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

        return false;
    }

    /**
     * Return document fields that are physically available on the clubs table.
     */
    private static function get_available_document_fields( $columns, $clubs_table ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );
        $available = array();
        foreach ( $doc_fields as $field ) {
            if ( self::has_verified_column( $columns, $clubs_table, $field ) ) {
                $available[] = $field;
            }
        }
        return $available;
    }

    /**
     * Find the safest season column to use for optional filtering.
     */
    private static function get_season_column( $columns, $clubs_table ) {
        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $column ) {
            if ( self::has_verified_column( $columns, $clubs_table, $column ) ) {
                return $column;
            }
        }
        return '';
    }

    /**
     * Build a correlated licence count expression only if the licence table supports it.
     */
    private static function get_licence_count_expression( $clubs_table ) {
        global $wpdb;
        $settings       = UFSC_SQL::get_settings();
        $licences_table = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( function_exists( 'ufsc_sanitize_table_name' ) ) {
            $licences_table = ufsc_sanitize_table_name( $licences_table );
            $clubs_table = ufsc_sanitize_table_name( $clubs_table );
        }
        if ( '' === $licences_table ) {
            return '';
        }
        if ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $licences_table ) ) {
            return '';
        }
        $licence_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $licences_table ) : array();
        if ( ! self::has_verified_column( $licence_columns, $licences_table, 'club_id' ) || ! self::has_verified_column( array(), $clubs_table, 'id' ) ) {
            return '';
        }

        $parts = array( "l.club_id = `{$clubs_table}`.id" );
        if ( self::has_verified_column( $licence_columns, $licences_table, 'statut' ) ) {
            $parts[] = $wpdb->prepare( 'l.statut = %s', 'valide' );
        }
        if ( self::has_verified_column( $licence_columns, $licences_table, 'deleted_at' ) ) {
            $parts[] = "(l.deleted_at IS NULL OR l.deleted_at = '0000-00-00 00:00:00')";
        }

        return "(SELECT COUNT(*) FROM `{$licences_table}` l WHERE " . implode( ' AND ', $parts ) . ')';
    }


    /** Build all read-only evidence clauses for a selected historical season. */
    private static function get_season_evidence_conditions( $clubs_table, $selected_season ) {
        global $wpdb;
        $season_evidence = array();
        $affiliations_table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_annual_affiliations_table() : ( class_exists( 'UFSC_Season_Archive_Manager' ) ? UFSC_Season_Archive_Manager::get_affiliations_table() : '' );
        if ( '' !== $affiliations_table && function_exists( 'ufsc_table_exists' ) && ufsc_table_exists( $affiliations_table ) ) {
            $aff_columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $affiliations_table ) : array();
            $club_column = in_array( 'club_id', $aff_columns, true ) ? 'club_id' : ( in_array( 'id_club', $aff_columns, true ) ? 'id_club' : '' );
            $season_column = in_array( 'season', $aff_columns, true ) ? 'season' : ( in_array( 'saison', $aff_columns, true ) ? 'saison' : '' );
            if ( '' !== $club_column && '' !== $season_column ) {
                $season_evidence[] = $wpdb->prepare( "EXISTS (SELECT 1 FROM `{$affiliations_table}` sa WHERE sa.`{$club_column}` = `{$clubs_table}`.id AND REPLACE(sa.`{$season_column}`, '/', '-') = %s)", $selected_season );
            }
        }
        $licence_season_exists = self::get_licence_season_exists_sql( $clubs_table, $selected_season );
        if ( '' !== $licence_season_exists ) { $season_evidence[] = $licence_season_exists; }
        $legacy_evidence = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_club_season_evidence_sql( $clubs_table, $clubs_table, $selected_season ) : array();
        if ( ! empty( $legacy_evidence['supported'] ) && ! empty( $legacy_evidence['sql'] ) ) { $season_evidence[] = $legacy_evidence['sql']; }
        return $season_evidence;
    }

    /** Build the renewal guard without assuming an annual table exists. */
    private static function get_active_affiliation_not_exists_sql( $clubs_table, $affiliations_table, $season ) {
        global $wpdb;
        if ( '' === $affiliations_table || ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $affiliations_table ) ) ) {
            return '1=1';
        }
        $columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $affiliations_table ) : array();
        $club_column = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : '' );
        $season_column = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
        $status_column = in_array( 'status', $columns, true ) ? 'status' : ( in_array( 'statut', $columns, true ) ? 'statut' : '' );
        if ( '' === $club_column || '' === $season_column || '' === $status_column ) {
            return '1=1';
        }
        return $wpdb->prepare( "NOT EXISTS (SELECT 1 FROM `{$affiliations_table}` ua WHERE ua.`{$club_column}` = `{$clubs_table}`.id AND REPLACE(ua.`{$season_column}`, '/', '-') = %s AND LOWER(ua.`{$status_column}`) IN ('active','validated','valide','actif'))", $season );
    }

    /** Build an EXISTS clause matching licences by normalized season for historical season filters. */
    private static function get_licence_season_exists_sql( $clubs_table, $season ) {
        global $wpdb;
        $licences_table = function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : ( class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : '' );
        if ( '' === $licences_table || ( function_exists( 'ufsc_table_exists' ) && ! ufsc_table_exists( $licences_table ) ) ) { return ''; }
        $columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $licences_table ) : ( class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_columns( $licences_table ) : array() );
        if ( ! self::has_verified_column( $columns, $licences_table, 'club_id' ) ) { return ''; }
        $season_column = self::get_season_column( $columns, $licences_table );
        if ( '' === $season_column ) { return ''; }
        $parts = array( "lx.club_id = `{$clubs_table}`.id" );
        if ( 'season_end_year' === $season_column ) {
            $normalized = function_exists( 'ufsc_normalize_season_reference' ) ? ufsc_normalize_season_reference( $season ) : $season;
            $end_year = preg_match( '/^\d{4}-(\d{4})$/', $normalized, $m ) ? (int) $m[1] : 0;
            if ( $end_year <= 0 ) { return ''; }
            $parts[] = $wpdb->prepare( 'lx.season_end_year = %d', $end_year );
        } else {
            $normalized = function_exists( 'ufsc_normalize_season_reference' ) ? ufsc_normalize_season_reference( $season ) : $season;
            $season_parts = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_season_reference_parts( $normalized ) : array( 'end_year' => 0 );
            $parts[] = $wpdb->prepare( "REPLACE(lx.`{$season_column}`, '/', '-') IN (%s, %s, %s)", $season, $normalized, (string) ( $season_parts['end_year'] ?? '' ) );
        }
        if ( class_exists( 'UFSC_Storage_Resolver' ) ) { $parts[] = UFSC_Storage_Resolver::not_deleted_sql( $licences_table, 'lx' ); }
        return "EXISTS (SELECT 1 FROM `{$licences_table}` lx WHERE " . implode( ' AND ', $parts ) . ')';
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
        if ( ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return;
        }
        echo '<p class="ufsc-primary-actions">';
        echo '<a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs&action=new' ) ) . '" class="button button-primary">';
        echo esc_html__( 'Ajouter un club', 'ufsc-clubs' );
        echo '</a> ';
        $current_url = self::get_current_request_url();
        echo '<a href="' . esc_url( add_query_arg( 'export', 'csv', $current_url ) ) . '" class="button">';
        echo esc_html__( 'Exporter CSV', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( add_query_arg( 'export', 'xlsx', $current_url ) ) . '" class="button">';
        echo esc_html__( 'Exporter XLSX', 'ufsc-clubs' );
        echo '</a>';

        echo '<a href="' . esc_url( admin_url('admin.php?page=ufsc-import') ) . '" class="button">';
        echo esc_html__( 'Importer', 'ufsc-clubs' );
        echo '</a>';

        echo '</p>';
    }

    /**
     * Render statistics cards.
     */
    private static function render_statistics_cards( $columns, $clubs_table, $licence_counts ) {
        global $wpdb;
        $where_scope = '';
        if ( self::has_verified_column( $columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) {
                $where_scope = 'WHERE ' . $scope_condition;
            }
        }

        $current_season = self::get_admin_season_label();
        $active_statuses = array( 'active', 'validated', 'actif', 'valide' );
        $pending_statuses = array( 'pending_payment', 'pending_validation', 'correction_required', 'pending', 'en_attente' );
        $base_where = '' === $where_scope ? 'WHERE 1=1' : $where_scope;
        $not_deleted = class_exists( 'UFSC_Storage_Resolver' ) ? ' AND ' . UFSC_Storage_Resolver::not_deleted_sql( $clubs_table ) : '';
        $active_condition = self::get_annual_affiliation_condition( $clubs_table, $current_season, $active_statuses );
        $pending_condition = self::get_annual_affiliation_condition( $clubs_table, $current_season, $pending_statuses );
        $renewal_condition = self::get_admin_kpi_filter_condition( 'renewals', $columns, $clubs_table, $current_season );
        $documents_condition = self::get_admin_kpi_filter_condition( 'documents_incomplete', $columns, $clubs_table, $current_season );
        $numbers_condition = self::get_admin_kpi_filter_condition( 'annual_numbers_missing', $columns, $clubs_table, $current_season );

        $stats = array(
            'total' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted}" ),
            'active' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted} AND {$active_condition}" ),
            'pending' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted} AND {$pending_condition}" ),
            'to_renew' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted} AND {$renewal_condition}" ),
            'documents_incomplete' => '' !== $documents_condition ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted} AND {$documents_condition}" ) : 0,
            'annual_numbers_missing' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$base_where}{$not_deleted} AND {$numbers_condition}" ),
            'licences_active' => self::sum_licence_counts_for_scope( $columns, $clubs_table, $licence_counts, $where_scope ),
        );

        $make_url = static function( $args ) use ( $current_season ) {
            return add_query_arg( array_merge( array( 'page' => 'ufsc-sql-clubs', 'season' => $current_season ), $args ), admin_url( 'admin.php' ) );
        };
        $cards = array(
            array( 'label' => __( 'Clubs enregistrés', 'ufsc-clubs' ), 'value' => $stats['total'], 'tone' => 'primary', 'url' => $make_url( array( 'season' => 'permanent' ) ), 'description' => __( 'Clubs enregistrés non supprimés.', 'ufsc-clubs' ) ),
            array( 'label' => sprintf( __( 'Affiliations actives %s', 'ufsc-clubs' ), $current_season ), 'value' => $stats['active'], 'tone' => 'success', 'url' => $make_url( array( 'kpi_filter' => 'affiliations_active' ) ), 'description' => __( 'Affiliation annuelle active ou validated uniquement.', 'ufsc-clubs' ) ),
            array( 'label' => __( 'Renouvellements à traiter', 'ufsc-clubs' ), 'value' => $stats['to_renew'], 'tone' => 'danger', 'url' => $make_url( array( 'kpi_filter' => 'renewals' ) ), 'description' => __( 'Clubs historiques sans affiliation annuelle active/validated pour la saison courante.', 'ufsc-clubs' ) ),
            array( 'label' => sprintf( __( 'Affiliations en attente %s', 'ufsc-clubs' ), $current_season ), 'value' => $stats['pending'], 'tone' => 'warning', 'url' => $make_url( array( 'kpi_filter' => 'affiliations_pending' ) ), 'description' => __( 'Paiement, validation ou correction en attente.', 'ufsc-clubs' ) ),
            array( 'label' => __( 'Dossiers clubs incomplets', 'ufsc-clubs' ), 'value' => $stats['documents_incomplete'], 'tone' => 'danger', 'url' => $make_url( array( 'kpi_filter' => 'documents_incomplete' ) ), 'description' => __( 'Nombre de clubs distincts avec au moins un document permanent obligatoire manquant.', 'ufsc-clubs' ) ),
            array( 'label' => sprintf( __( 'Licences %s actives', 'ufsc-clubs' ), $current_season ), 'value' => $stats['licences_active'], 'tone' => 'primary', 'url' => admin_url( 'admin.php?page=ufsc-licenses' ), 'description' => __( 'Licences actives selon le compteur canonique du périmètre affiché.', 'ufsc-clubs' ) ),
            array( 'label' => __( 'Numéros annuels à attribuer', 'ufsc-clubs' ), 'value' => $stats['annual_numbers_missing'], 'tone' => 'warning', 'url' => $make_url( array( 'kpi_filter' => 'annual_numbers_missing' ) ), 'description' => __( 'Affiliations annuelles validées dont le numéro annuel est vide.', 'ufsc-clubs' ) ),
        );

        echo '<div class="ufsc-stats-grid">';
        foreach ( $cards as $card ) {
            $tag = ! empty( $card['url'] ) ? 'a' : 'div';
            $href = ! empty( $card['url'] ) ? ' href="' . esc_url( $card['url'] ) . '"' : '';
            $active = ! empty( $card['url'] ) && self::get_query_value( 'kpi_filter', 'key' ) && false !== strpos( $card['url'], 'kpi_filter=' . self::get_query_value( 'kpi_filter', 'key' ) );
            echo '<' . $tag . $href . ' class="ufsc-stat-card ufsc-stat-card--' . esc_attr( $card['tone'] ) . ( $active ? ' is-active' : '' ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>';
            echo '<span>' . esc_html( $card['label'] ) . '</span>';
            echo '<strong>' . esc_html( number_format_i18n( (int) $card['value'] ) ) . '</strong>';
            if ( ! empty( $card['description'] ) ) { echo '<small title="' . esc_attr( $card['description'] ) . '">' . esc_html( $card['description'] ) . '</small>'; }
            echo '</' . $tag . '>';
        }
        echo '</div>';
    }


    /**
     * Sum licence counts only for clubs in the same regional perimeter as the cards/table.
     */
    private static function sum_licence_counts_for_scope( $columns, $clubs_table, $licence_counts, $where_scope ) {
        if ( '' === $where_scope ) {
            return array_sum( array_map( 'intval', (array) $licence_counts ) );
        }

        global $wpdb;
        $id_column = function_exists( 'ufsc_club_col' ) ? ufsc_club_col( 'id' ) : 'id';
        if ( ! self::has_verified_column( $columns, $clubs_table, $id_column ) ) {
            $id_column = 'id';
        }

        $club_ids = $wpdb->get_col( "SELECT `{$id_column}` FROM `{$clubs_table}` {$where_scope}" );
        $total    = 0;
        foreach ( (array) $club_ids as $club_id ) {
            $club_id = (int) $club_id;
            $total  += isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
        }

        return $total;
    }

    /** Render a non-destructive explanation/fallback for empty historical season evidence. */
    private static function render_historical_archive_notice( $filters, $columns, $clubs_table ) {
        global $wpdb;
        $selected_filter = self::get_selected_season_filter();
        if ( in_array( $selected_filter, array( '', 'all', 'permanent', '__archives' ), true ) ) { return; }
        $archive_scope = sanitize_key( (string) ( $filters['archive_scope'] ?? '' ) );
        $counts = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_season_evidence_counts( $selected_filter ) : array();
        $where_scope = '';
        if ( self::has_verified_column( $columns, $clubs_table, 'region' ) ) {
            $scope_condition = UFSC_Scope::build_scope_condition( 'region' );
            if ( $scope_condition ) { $where_scope = 'WHERE ' . $scope_condition; }
        }
        $permanent_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$clubs_table}` {$where_scope}" );
        if ( 'all_historical' === $archive_scope ) {
            echo '<div class="notice notice-info ufsc-historical-archive-notice"><p>' . esc_html( sprintf( __( 'Consultation historique étendue : %1$d clubs permanents restent visibles pour %2$s avec le badge « Saison historique non renseignée » lorsqu’aucune preuve saisonnière stricte n’existe. Aucun club n’est déclaré actif ou validé sans preuve.', 'ufsc-clubs' ), $permanent_total, $selected_filter ) ) . '</p></div>';
            return;
        }
        if ( empty( $counts['total_distinct'] ) && $permanent_total > 0 ) {
            $fallback_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'season' => $selected_filter, 'archive_scope' => 'all_historical' ), admin_url( 'admin.php' ) );
            echo '<div class="notice notice-warning ufsc-historical-archive-notice"><p><strong>' . esc_html__( 'Clubs historiques sans saison prouvée', 'ufsc-clubs' ) . '</strong></p><p>' . esc_html( sprintf( __( 'Les affiliations %1$s ne peuvent pas être déterminées avec certitude à partir des données historiques. Les %2$d clubs permanents restent consultables.', 'ufsc-clubs' ), $selected_filter, $permanent_total ) ) . '</p><p><a class="button" href="' . esc_url( $fallback_url ) . '">' . esc_html__( 'Voir les clubs historiques', 'ufsc-clubs' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '">' . esc_html__( 'Retour à la saison courante', 'ufsc-clubs' ) . '</a></p></div>';
        }
    }

    /** Render business labels and individually removable filters (never raw GET keys). */
    private static function render_active_filters_summary( $filters, $search ) {
        $view_labels = array( 'affiliations_active' => __( 'Affiliations actives', 'ufsc-clubs' ), 'affiliations_pending' => __( 'Affiliations en attente', 'ufsc-clubs' ), 'renewals' => __( 'Renouvellements à traiter', 'ufsc-clubs' ), 'documents_incomplete' => __( 'Dossiers clubs incomplets', 'ufsc-clubs' ), 'annual_numbers_missing' => __( 'Numéros annuels à attribuer', 'ufsc-clubs' ) );
        $labels = array();
        $business = array( 'season' => __( 'Saison', 'ufsc-clubs' ), 'region' => __( 'Région', 'ufsc-clubs' ), 'statut' => __( 'Statut permanent', 'ufsc-clubs' ), 'doc_status' => __( 'Documents', 'ufsc-clubs' ), 'licence_range' => __( 'Licences', 'ufsc-clubs' ), 'created_from' => __( 'Créé depuis', 'ufsc-clubs' ), 'created_to' => __( 'Créé avant', 'ufsc-clubs' ) );
        foreach ( $business as $key => $label ) {
            if ( ! empty( $filters[ $key ] ) ) { $labels[ $key ] = $label . ' : ' . $filters[ $key ]; }
        }
        if ( ! empty( $filters['kpi_filter'] ) ) { $labels['kpi_filter'] = __( 'Vue', 'ufsc-clubs' ) . ' : ' . ( $view_labels[ $filters['kpi_filter'] ] ?? __( 'Vue sélectionnée', 'ufsc-clubs' ) ); }
        if ( '' !== $search ) { $labels['q'] = __( 'Recherche', 'ufsc-clubs' ) . ' : ' . $search; }
        if ( empty( $labels ) ) { return; }
        echo '<div class="ufsc-active-filters"><strong>' . esc_html__( 'Filtres actifs :', 'ufsc-clubs' ) . '</strong><ul>';
        foreach ( $labels as $key => $label ) { echo '<li>' . esc_html( $label ) . ' <a href="' . esc_url( remove_query_arg( array( $key, 'paged' ), self::get_current_request_url() ) ) . '" aria-label="' . esc_attr( sprintf( __( 'Retirer le filtre %s', 'ufsc-clubs' ), $label ) ) . '">× ' . esc_html__( 'Retirer', 'ufsc-clubs' ) . '</a></li>'; }
        echo '</ul><a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '">' . esc_html__( 'Effacer tous les filtres', 'ufsc-clubs' ) . '</a></div>';
    }

    /** Render the SQL evidence diagnostic only for administrators in debug mode. */
    private static function render_debug_season_query_diagnostic( $filters, $clubs_table, $total_query, $clubs_query, $where_clause, $total_items ) {
        global $wpdb;
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) { return; }
        $selected_filter = self::get_selected_season_filter();
		$columns = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : array();
		$business_condition = ! empty( $filters['kpi_filter'] ) ? self::get_admin_kpi_filter_condition( $filters['kpi_filter'], $columns, $clubs_table, self::get_admin_season_label() ) : '';
		$kpi_conditions = ! empty( $filters['kpi_filter'] ) ? self::build_where_conditions( array_merge( $filters, array( 'season' => self::get_admin_season_label(), 'club_view' => '', 'archive_scope' => '', 'region' => '', 'statut' => '', 'created_from' => '', 'created_to' => '', 'doc_status' => '', 'affiliation_status' => '', 'licence_range' => '' ) ), '', $columns, $clubs_table ) : array();
		$kpi_count_query = $business_condition ? "SELECT COUNT(*) FROM `{$clubs_table}` WHERE " . implode( ' AND ', $kpi_conditions ) : '';
		$kpi_count = $kpi_count_query ? (int) $wpdb->get_var( $kpi_count_query ) : null;
		if ( null !== $kpi_count && $kpi_count !== $total_items ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Incohérence KPI/liste détectée', 'ufsc-clubs' ) . '</strong></p></div>';
		}
        $evidence = self::get_season_evidence_conditions( $clubs_table, $selected_filter );
        $legacy = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_club_season_evidence_sql( $clubs_table, $clubs_table, $selected_filter ) : array();
        $counts = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_season_evidence_counts( $selected_filter ) : array();
        echo '<details class="ufsc-debug-season-diagnostic"><summary>' . esc_html__( 'Diagnostic filtre saison', 'ufsc-clubs' ) . '</summary><pre>' . esc_html( wp_json_encode( array(
            'selected_season' => $selected_filter,
            'selected_filter' => $filters['season'] ?? '',
            'club_view' => $filters['club_view'] ?? '',
            'archive_scope' => $filters['archive_scope'] ?? '',
			'business_filter' => $filters['kpi_filter'] ?? '',
			'shared_condition' => $business_condition,
			'kpi_count' => $kpi_count,
			'list_count' => $total_items,
			'kpi_count_query' => $kpi_count_query,
            'clubs_table' => $clubs_table,
            'affiliations_table' => class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_annual_affiliations_table() : '',
            'licences_table' => class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : '',
            'evidence_sql' => $evidence,
            'legacy_evidence_sql' => $legacy['sql'] ?? '',
            'diagnostic_label' => __( 'Licences historiques sans saison renseignée', 'ufsc-clubs' ),
            'counts_by_source' => $counts,
            'where_clause' => $where_clause,
            'count_query' => $total_query,
            'select_query' => $clubs_query,
            'total_items' => $total_items,
            'last_error' => $wpdb->last_error,
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
    }

    /**
     * Render filters and search in a single panel.
     */
    private static function render_filters( $filters, $columns, $clubs_table, $search = '' ) {
        echo '<div class="ufsc-filters-panel">';
        echo '<div class="ufsc-panel-heading"><h2>' . esc_html__( 'Filtres de recherche', 'ufsc-clubs' ) . '</h2><p>' . esc_html__( 'Affinez la liste sans modifier les exports, la pagination ou les actions existantes.', 'ufsc-clubs' ) . '</p></div>';
        echo '<form method="get" class="ufsc-filters-form">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs">';

        echo '<div class="ufsc-filters-grid">';
        echo '<label><span>' . esc_html__( 'Région', 'ufsc-clubs' ) . '</span>';
        self::render_region_filter( $filters['region'], $columns, $clubs_table );
        echo '</label>';

        echo '<label><span>' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</span>';
        self::render_status_filter( $filters['statut'] );
        echo '</label>';

        echo '<label><span>' . esc_html__( 'Créé du', 'ufsc-clubs' ) . '</span><input type="date" name="created_from" value="' . esc_attr( $filters['created_from'] ) . '"></label>';
        echo '<label><span>' . esc_html__( 'Créé au', 'ufsc-clubs' ) . '</span><input type="date" name="created_to" value="' . esc_attr( $filters['created_to'] ) . '"></label>';

        echo '<label class="ufsc-club-search"><span>' . esc_html__( 'Rechercher un club', 'ufsc-clubs' ) . '</span><input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nom du club, email ou numéro d’affiliation', 'ufsc-clubs' ) . '"></label>';

        echo '<label><span>' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</span><select name="doc_status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        echo '<option value="complete"' . selected( $filters['doc_status'], 'complete', false ) . '>' . esc_html__( 'Complets', 'ufsc-clubs' ) . '</option>';
        echo '<option value="incomplete"' . selected( $filters['doc_status'], 'incomplete', false ) . '>' . esc_html__( 'Incomplets', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'N° affiliation', 'ufsc-clubs' ) . '</span><select name="affiliation_status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        echo '<option value="assigned"' . selected( $filters['affiliation_status'], 'assigned', false ) . '>' . esc_html__( 'Attribué', 'ufsc-clubs' ) . '</option>';
        echo '<option value="missing"' . selected( $filters['affiliation_status'], 'missing', false ) . '>' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</span><select name="licence_range">';
        echo '<option value="">' . esc_html__( 'Toutes', 'ufsc-clubs' ) . '</option>';
        echo '<option value="zero"' . selected( $filters['licence_range'], 'zero', false ) . '>' . esc_html__( '0 licence', 'ufsc-clubs' ) . '</option>';
        echo '<option value="one_to_nine"' . selected( $filters['licence_range'], 'one_to_nine', false ) . '>' . esc_html__( '1 à 9 licences', 'ufsc-clubs' ) . '</option>';
        echo '<option value="ten_plus"' . selected( $filters['licence_range'], 'ten_plus', false ) . '>' . esc_html__( '10 licences et +', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</span><select name="season">';
        foreach ( self::get_season_options() as $value => $label ) {
            echo '<option value="' . esc_attr( $value ) . '"' . selected( $filters['season'], $value, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></label>';

        echo '<label><span>' . esc_html__( 'Archive', 'ufsc-clubs' ) . '</span><select name="archive_scope">';
        echo '<option value=""' . selected( $filters['archive_scope'], '', false ) . '>' . esc_html__( 'Présence prouvée', 'ufsc-clubs' ) . '</option>';
        echo '<option value="all_historical"' . selected( $filters['archive_scope'], 'all_historical', false ) . '>' . esc_html__( 'Tous les clubs historiques', 'ufsc-clubs' ) . '</option>';
        echo '</select></label>';
        echo '</div>';

        echo '<div class="ufsc-filters-actions">';
        submit_button( __( 'Filtrer', 'ufsc-clubs' ), 'primary', null, false );
        echo ' <a href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '" class="button ufsc-reset-button">' . esc_html__( 'Réinitialiser', 'ufsc-clubs' ) . '</a>';
        echo '</div>';

        echo '</form>';
        echo '<script>(function(){document.querySelectorAll(".ufsc-filters-form").forEach(function(form){form.addEventListener("submit",function(){form.querySelectorAll("input,select").forEach(function(el){if(el.name!=="page" && !el.value){el.disabled=true;}});});});})();</script>';
        echo '</div>';
    }

    /**
     * Backward-compatible no-op search renderer: search now lives in the filter panel.
     */
    private static function render_search( $search ) {
        unset( $search );
    }

    /**
     * Render quick GET filters above the table.
     */
    private static function render_quick_filters( $filters ) {
        $base = admin_url( 'admin.php?page=ufsc-sql-clubs' );
		$season = self::get_admin_season_label();
        $links = array(
            array( 'label' => __( 'Clubs de la saison', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'club_view' => 'season' ) ),
            array( 'label' => __( 'Tous les clubs enregistrés', 'ufsc-clubs' ), 'args' => array( 'club_view' => 'permanent' ) ),
            array( 'label' => __( 'Clubs à renouveler / anciens clubs', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'club_view' => 'renewals' ) ),
            array( 'label' => __( 'Diagnostic stockage', 'ufsc-clubs' ), 'args' => array( 'archive_scope' => 'all_historical' ) ),
            array( 'label' => __( 'Renouvellements à traiter', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'kpi_filter' => 'renewals' ) ),
            array( 'label' => __( 'Actifs', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'kpi_filter' => 'affiliations_active' ) ),
            array( 'label' => __( 'En attente', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'kpi_filter' => 'affiliations_pending' ) ),
            array( 'label' => __( 'Dossiers clubs incomplets', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'kpi_filter' => 'documents_incomplete' ) ),
            array( 'label' => __( 'Numéros annuels à attribuer', 'ufsc-clubs' ), 'args' => array( 'season' => $season, 'kpi_filter' => 'annual_numbers_missing' ) ),
            array( 'label' => __( 'Moins de 10 licences', 'ufsc-clubs' ), 'args' => array( 'licence_range' => 'under_ten' ) ),
            array( 'label' => __( 'Clubs sans licence', 'ufsc-clubs' ), 'args' => array( 'licence_range' => 'zero' ) ),
        );

        echo '<div class="ufsc-quick-filters" aria-label="' . esc_attr__( 'Filtres rapides', 'ufsc-clubs' ) . '">';
        foreach ( $links as $link ) {
            $url = empty( $link['args'] ) ? $base : add_query_arg( $link['args'], $base );
			$active = true;
			foreach ( $link['args'] as $key => $value ) { if ( (string) ( $filters[ $key ] ?? '' ) !== (string) $value ) { $active = false; break; } }
            echo '<a class="button' . ( $active ? ' button-primary is-active' : '' ) . '" href="' . esc_url( $url ) . '"' . ( $active ? ' aria-current="page"' : '' ) . '>' . esc_html( $link['label'] ) . '</a>';
        }
        echo '</div>';
    }

    //add action groppe
    // private static function bulck_action_grop_by_club(){
    //     echo '<form method="post" id="bulk-actions-form">';
    //     // Bulk actions
    //     echo '<div class="ufsc-bulk-actions">';

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
        $start = $total_items > 0 ? ( ( $pagination['paged'] - 1 ) * $pagination['per_page'] ) + 1 : 0;
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
            $url = add_query_arg( 'per_page', $per_page, self::get_current_request_url() );
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
        if ( isset( $_GET['processed'] ) ) {
            $processed = absint( wp_unslash( $_GET['processed'] ) );
            if ( 1 === $processed ) {
                echo UFSC_CL_Utils::show_success( sprintf( __( '%d élément(s) traité(s)', 'ufsc-clubs' ), $processed ) );
            } elseif ( 0 === $processed ) {
                echo UFSC_CL_Utils::show_error( __( 'Impossible de supprimer les clubs - présence probable de licences liées.', 'ufsc-clubs' ) );
            }
        }

        $can_manage_clubs = ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
        $table_mode_class = $can_manage_clubs ? 'ufsc-clubs-table-form--manage' : 'ufsc-clubs-table-form--readonly';

        echo '<form method="post" id="bulk-actions-form" class="ufsc-clubs-table-form ' . esc_attr( $table_mode_class ) . '">';
        echo '<input type="hidden" name="page" value="ufsc-sql-clubs" />';
        if ( $can_manage_clubs ) {
            // Bulk actions are write operations and are hidden for read-only users.
            echo '<div class="ufsc-bulk-actions">';

            wp_nonce_field('ufsc_bulk_clubs_actions');
            echo '<select name="bulk_action" id="bulk-action-selector">';
            echo '<option value="">'.esc_html__('Actions groupées', 'ufsc-clubs').'</option>';
            echo '<option value="delete">'.esc_html__('Supprimer', 'ufsc-clubs').'</option>';
            echo '<option value="actif">'.esc_html__('Actif', 'ufsc-clubs').'</option>';
            echo '<option value="en_attente">'.esc_html__('En attente', 'ufsc-clubs').'</option>';
            echo '<option value="creating">'.esc_html__('En cours de création', 'ufsc-clubs').'</option>';
            echo '<option value="export_selection" disabled="disabled">'.esc_html__('Exporter la sélection (bientôt)', 'ufsc-clubs').'</option>';
            echo '<option value="remind_documents" disabled="disabled">'.esc_html__('Relance documents (bientôt)', 'ufsc-clubs').'</option>';
            echo '<option value="remind_affiliation" disabled="disabled">'.esc_html__('Relance affiliation (bientôt)', 'ufsc-clubs').'</option>';
            echo '</select>';
            echo ' <button type="submit" class="button">'.esc_html__('Appliquer', 'ufsc-clubs').'</button>';
            echo '</div>';
        }

        //table
        echo '<table class="wp-list-table widefat striped ufsc-clubs-table ' . ( $can_manage_clubs ? 'ufsc-clubs-table--manage' : 'ufsc-clubs-table--readonly' ) . '">';
        echo '<thead>';
        echo '<tr>';
        if ( $can_manage_clubs ) {
            echo '<td class="check-column column-checkbox"><input type="checkbox" id="select-all-club" /></td>';
        }
        echo '<th class="column-id">ID</th>';
        echo '<th class="column-club">' . self::get_sortable_header( 'nom', __( 'Nom du club', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th class="column-region">' . self::get_sortable_header( 'region', __( 'Région', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th class="column-affiliation">' . esc_html__( 'N° Affiliation', 'ufsc-clubs' ) . '</th>';
        echo '<th class="column-status">' . esc_html__( 'Statut', 'ufsc-clubs' ) . '</th>';
        echo '<th class="column-licences">' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th>';
        echo '<th class="column-documents">' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</th>';
        echo '<th class="column-created">' . self::get_sortable_header( 'date_creation', __( 'Créé le', 'ufsc-clubs' ), $sorting ) . '</th>';
        echo '<th class="column-actions">' . esc_html__( 'Actions', 'ufsc-clubs' ) . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';



        if ( $clubs ) {
            foreach ( $clubs as $club ) {
                self::render_club_row( $club, $licence_counts, $can_manage_clubs );
            }
        } else {
                echo '<tr><td colspan="' . ( $can_manage_clubs ? '10' : '9' ) . '"><div class="ufsc-empty-state"><strong>' . esc_html__( 'Aucun club ne correspond aux filtres actuels.', 'ufsc-clubs' ) . '</strong><p>' . esc_html__( 'Retirez un filtre ci-dessus ou revenez à une vue complète.', 'ufsc-clubs' ) . '</p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) . '">' . esc_html__( 'Réinitialiser les filtres', 'ufsc-clubs' ) . '</a> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=ufsc-sql-clubs&club_view=permanent' ) ) . '">' . esc_html__( 'Voir tous les clubs enregistrés', 'ufsc-clubs' ) . '</a></div></td></tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</form>';

    }

    /**
     * Render individual club row
     */
    private static function render_club_row( $club, $licence_counts, $can_manage_clubs = null ) {
    if ( null === $can_manage_clubs ) {
        $can_manage_clubs = ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE );
    }
    echo '<tr>';

    // Checkbox (write-only bulk actions)
    if ( $can_manage_clubs ) {
        echo '<th class="check-column column-checkbox"><input type="checkbox" name="club_ids[]" value="' . (int) ( $club->id ?? 0 ) . '" /></th>';
    }

    // ID
    echo '<td class="column-id">' . (int) ( $club->id ?? 0 ) . '</td>';

    // Nom + Email
    $club_name = isset( $club->nom ) ? $club->nom : '';
    $club_email = isset( $club->email ) ? $club->email : '';
    echo '<td class="column-club ufsc-club-name-cell"><strong>' . esc_html( $club_name ) . '</strong>';
    if ( ! empty( $club_email ) ) {
        echo '<br><small>' . esc_html( $club_email ) . '</small>';
    }
    echo self::render_alerts( $club, $licence_counts );
    echo '</td>';

    // Région
    echo '<td class="column-region">' . esc_html( isset( $club->region ) ? $club->region : '' ) . '</td>';

	$current_season = self::get_admin_season_label();
	$selected_season = self::get_selected_season_filter();
	$annual_affiliation = ( ! in_array( $selected_season, array( 'all', '__archives' ), true ) && class_exists( 'UFSC_Season_Archive_Manager' ) ) ? UFSC_Season_Archive_Manager::get_affiliation( (int) ( $club->id ?? 0 ), $current_season ) : null;

    // Numéro d’affiliation annuel uniquement; no historical ASPTT carry-over.
    echo '<td class="column-affiliation">';
    echo in_array( $selected_season, array( 'all', '__archives' ), true ) ? '<em>' . esc_html__( 'Voir les saisons ci-contre', 'ufsc-clubs' ) . '</em>' : self::render_affiliation_number( $annual_affiliation && isset( $annual_affiliation->num_affiliation ) ? $annual_affiliation->num_affiliation : '' );
    echo '</td>';

    // Statut
    echo '<td class="column-status"><span class="ufsc-permanent-status"><strong>' . esc_html__( 'Club permanent :', 'ufsc-clubs' ) . '</strong> ' . esc_html__( 'Enregistré', 'ufsc-clubs' ) . '</span><br>';
    if ( in_array( $selected_season, array( 'all', '__archives' ), true ) ) {
        global $wpdb;
        $affiliations_table = UFSC_Season_Archive_Manager::get_affiliations_table();
        $archive_clause = '__archives' === $selected_season ? $wpdb->prepare( ' AND season < %s', UFSC_Season_Service::get_current_season() ) : '';
        $annual_rows = $wpdb->get_results( $wpdb->prepare( "SELECT season, status, num_affiliation FROM `{$affiliations_table}` WHERE club_id = %d{$archive_clause} ORDER BY season DESC", (int) ( $club->id ?? 0 ) ) );
        if ( $annual_rows ) { foreach ( $annual_rows as $annual_row ) { echo '<span class="ufsc-annual-status"><strong>' . esc_html( $annual_row->season ) . ' :</strong> ' . self::render_status_badge( $annual_row->status ) . ' <small>' . esc_html( $annual_row->num_affiliation ?: __( 'sans numéro', 'ufsc-clubs' ) ) . '</small></span><br>'; } }
        else { echo '<em>' . esc_html__( 'Aucune affiliation annuelle enregistrée dans cette vue.', 'ufsc-clubs' ) . '</em>'; }
    } else {
        $status_value = $annual_affiliation && isset( $annual_affiliation->status ) ? $annual_affiliation->status : 'a_renouveler';
        echo '<span class="ufsc-annual-status"><strong>' . esc_html( sprintf( __( 'Affiliation %s :', 'ufsc-clubs' ), $current_season ) ) . '</strong> ' . self::render_status_badge( $status_value ) . '</span>';
        if ( ! $annual_affiliation ) { echo '<br><small title="' . esc_attr( sprintf( __( 'Ce club existe dans l’historique UFSC mais n’est pas encore affilié pour la saison %s.', 'ufsc-clubs' ), $current_season ) ) . '">' . esc_html__( 'Affiliation annuelle à renouveler', 'ufsc-clubs' ) . '</small>'; }
    }
    echo '</td>';

    // Licences validées
    $club_id = (int) ( $club->id ?? 0 );
    $licence_count = isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
    $licence_url = add_query_arg(
        array(
            'page'          => 'ufsc_lc_licences',
            'filter_club'   => absint( $club_id ),
            'filter_status' => 'valide',
			'filter_season' => in_array( $selected_season, array( 'all', '__archives' ), true ) ? $selected_season : $current_season,
        ),
        admin_url( 'admin.php' )
    );
    $licence_label = sprintf( _n( '%d licence', '%d licences', $licence_count, 'ufsc-clubs' ), $licence_count );
    echo '<td class="column-licences"><a class="ufsc-licence-link" href="' . esc_url( $licence_url ) . '">' . esc_html( $licence_label ) . '</a></td>';

    // Documents
    echo '<td class="column-documents">' . self::render_documents_badge( $club ) . '</td>';

    // Date de création
    $date_creation = isset( $club->date_creation ) ? $club->date_creation : '';
    echo '<td class="column-created">' . ( $date_creation ? esc_html( mysql2date( 'd/m/Y', $date_creation ) ) : '<em>' . esc_html__( 'Non défini', 'ufsc-clubs' ) . '</em>' ) . '</td>';

    // Actions
    echo '<td class="column-actions ufsc-row-actions"><div class="ufsc-actions-grid">';
    $club_id = (int) ( $club->id ?? 0 );
    $return_to = self::get_current_request_url();
    $view_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'view', 'id' => $club_id, 'return_to' => $return_to ), admin_url( 'admin.php' ) );
    $edit_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'edit', 'id' => $club_id, 'return_to' => $return_to ), admin_url( 'admin.php' ) );
    $delete_url = wp_nonce_url(
        admin_url( 'admin-post.php?action=ufsc_sql_delete_club&id=' . $club_id ),
        'ufsc_sql_delete_club'
    );
    $documents_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'edit', 'id' => $club_id, 'tab' => 'documents' ), admin_url( 'admin.php' ) );
    echo '<a href="' . esc_url( $view_url ) . '" class="button button-small">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a> ';
    echo '<a href="' . esc_url( $licence_url ) . '" class="button button-small">' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</a> ';
    if ( $can_manage_clubs ) {
        echo '<a href="' . esc_url( $edit_url ) . '" class="button button-small">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a> ';
        echo '<a href="' . esc_url( $documents_url ) . '" class="button button-small">' . esc_html__( 'Documents', 'ufsc-clubs' ) . '</a> ';
		$annual_status = $annual_affiliation && isset( $annual_affiliation->status ) ? sanitize_key( $annual_affiliation->status ) : '';
		$open_statuses = array( 'active', 'validated', 'actif', 'valide', 'pending_payment', 'pending_validation', 'pending', 'en_attente' );
		$affiliation_url = add_query_arg( array( 'page' => 'ufsc-sql-clubs', 'action' => 'edit', 'id' => $club_id, 'tab' => 'affiliation', 'return_to' => $return_to ), admin_url( 'admin.php' ) );
		echo '<a href="' . esc_url( $affiliation_url ) . '" class="button button-small">' . esc_html( sprintf( __( 'Gérer l’affiliation %s', 'ufsc-clubs' ), $current_season ) ) . '</a> ';
		if ( ! in_array( $annual_status, $open_statuses, true ) ) {
			$renew_url = function_exists( 'ufsc_get_affiliation_renewal_url' ) ? ufsc_get_affiliation_renewal_url( $club_id, $current_season ) : $edit_url;
			echo '<a href="' . esc_url( $renew_url ) . '" class="button button-small button-primary">' . esc_html( sprintf( __( 'Renouveler pour %s', 'ufsc-clubs' ), $current_season ) ) . '</a> ';
			if ( $club_email ) { echo '<a class="button button-small" href="mailto:' . esc_attr( $club_email ) . '?subject=' . rawurlencode( sprintf( __( 'Renouvellement affiliation UFSC %s', 'ufsc-clubs' ), $current_season ) ) . '">' . esc_html__( 'Relancer', 'ufsc-clubs' ) . '</a> '; }
		}
		if ( in_array( $annual_status, array( 'active', 'validated', 'actif', 'valide' ), true ) && empty( $annual_affiliation->num_affiliation ) ) { echo '<a href="' . esc_url( $affiliation_url ) . '" class="button button-small button-primary">' . esc_html__( 'Attribuer le numéro', 'ufsc-clubs' ) . '</a> '; }
        echo '<a href="' . esc_url( $delete_url ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Êtes-vous sûr de vouloir supprimer ce club ?', 'ufsc-clubs' ) ) . '\')">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</a>';
    }
    echo '</div></td>';

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

        $base_url = remove_query_arg( 'paged', self::get_current_request_url() );

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

        $scope_slug  = UFSC_Scope::get_user_scope_region();
        $scope_label = $scope_slug ? UFSC_Scope::get_region_label( $scope_slug ) : '';
        if ( $scope_label ) {
            $regions = array( $scope_label );
        } elseif ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() ) {
            $regions = function_exists( 'ufsc_current_user_allowed_regions' ) ? ufsc_current_user_allowed_regions() : array();
        } else {
            $regions = $wpdb->get_col( "SELECT DISTINCT region FROM `{$clubs_table}` WHERE region IS NOT NULL AND region != '' ORDER BY region" );
        }

        echo '<select name="region">';
        if ( ! $scope_label && ( ! function_exists( 'ufsc_user_has_all_regions_access' ) || ufsc_user_has_all_regions_access() ) ) {
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
        $url = add_query_arg( array( 'orderby' => $column, 'order' => $order ), self::get_current_request_url() );

        $arrow = '';
        if ( $sorting['orderby'] === $column ) {
            $arrow = $sorting['order'] === 'asc' ? ' ↑' : ' ↓';
        }

        return '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . $arrow . '</a>';
    }

    private static function render_status_badge( $status ) {
        $raw        = is_scalar( $status ) ? (string) $status : '';
        $normalized = sanitize_key( strtolower( remove_accents( $raw ) ) );
        $label      = '' !== $raw ? $raw : __( 'Inconnu', 'ufsc-clubs' );
        $class      = 'ufsc-badge ufsc-badge--neutral';

        if ( in_array( $normalized, array( 'actif', 'active', 'valide', 'validated' ), true ) ) {
            $label = __( 'Actif', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--success';
		} elseif ( 'a_renouveler' === $normalized ) {
			$label = __( 'À renouveler', 'ufsc-clubs' );
			$class = 'ufsc-badge ufsc-badge--warning';
        } elseif ( in_array( $normalized, array( 'en_attente', 'pending', 'a_regler', 'creating', 'en_cours_de_creation' ), true ) ) {
            $label = __( 'En attente', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--warning';
        } elseif ( in_array( $normalized, array( 'suspendu', 'suspended', 'refuse', 'rejected', 'desactive', 'inactive' ), true ) ) {
            $label = __( 'Suspendu / refusé', 'ufsc-clubs' );
            $class = 'ufsc-badge ufsc-badge--danger';
        }

        return '<span class="' . esc_attr( $class ) . '" data-status="' . esc_attr( $normalized ) . '">' . esc_html( $label ) . '</span>';
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
            return '<span class="ufsc-badge ufsc-badge--success" title="' . esc_attr__( 'Documents complets', 'ufsc-clubs' ) . '">' .
                   esc_html__( 'Complet', 'ufsc-clubs' ) . '</span>';
        } else {
            return '<span class="ufsc-badge ufsc-badge--warning" title="' . esc_attr( sprintf( __( '%d/%d documents', 'ufsc-clubs' ), $complete_count, $total_count ) ) . '">' .
                   esc_html__( 'Incomplet', 'ufsc-clubs' ) . '</span>';
        }
    }


    private static function is_documents_complete( $club ) {
        $doc_fields = array(
            'doc_statuts',
            'doc_recepisse',
            'doc_jo',
            'doc_pv_ag',
            'doc_cer',
            'doc_attestation_cer'
        );
        foreach ( $doc_fields as $field ) {
            if ( ! isset( $club->$field ) || empty( $club->$field ) ) {
                return false;
            }
        }
        return true;
    }

    private static function render_affiliation_number( $number ) {
        $number = is_scalar( $number ) ? trim( (string) $number ) : '';
        if ( '' === $number ) {
            return '<span class="ufsc-badge ufsc-badge--muted">' . esc_html__( 'Non attribué', 'ufsc-clubs' ) . '</span>';
        }
        return '<span class="ufsc-affiliation-number">' . esc_html( $number ) . '</span>';
    }

    private static function render_alerts( $club, $licence_counts ) {
        $club_id       = (int) ( $club->id ?? 0 );
        $licence_count = isset( $licence_counts[ $club_id ] ) ? (int) $licence_counts[ $club_id ] : 0;
        $alerts        = array();

        if ( ! self::is_documents_complete( $club ) ) {
            $alerts[] = __( 'Documents incomplets', 'ufsc-clubs' );
        }
        if ( empty( $club->num_affiliation ) ) {
            $alerts[] = __( 'N° affiliation manquant', 'ufsc-clubs' );
        }
        if ( 0 === $licence_count ) {
            $alerts[] = __( 'Club sans licence', 'ufsc-clubs' );
        } elseif ( $licence_count < 10 ) {
            $alerts[] = __( 'Moins de 10 licences', 'ufsc-clubs' );
        }

        if ( empty( $alerts ) ) {
            return '';
        }

        $html = '<div class="ufsc-alert-tags">';
        foreach ( $alerts as $alert ) {
            $html .= '<span>' . esc_html( $alert ) . '</span>';
        }
        $html .= '</div>';
        return $html;
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
        $page = self::get_request_value( $_REQUEST, 'page', 'key' );
        if ( 'ufsc-sql-clubs' !== $page ) {
            return;
        }
        if ( ! ufsc_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            return;
        }

        $nonce = self::get_request_value( $_POST, '_wpnonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ufsc_bulk_clubs_actions' ) ) {
            return;
        }

        $action    = self::get_request_value( $_POST, 'bulk_action', 'key' );
        if ( '' === $action ) {
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
        $raw_ids   = isset( $_POST['club_ids'] ) ? (array) wp_unslash( $_POST['club_ids'] ) : array();
        $item_ids  = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );
        if ( empty( $item_ids ) ) {
            return;
        }
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
        $redirect_to = isset( $_POST['_wp_http_referer'] ) ? wp_validate_redirect( wp_unslash( $_POST['_wp_http_referer'] ), admin_url( 'admin.php?page=ufsc-sql-clubs' ) ) : admin_url( 'admin.php?page=ufsc-sql-clubs' );
        wp_safe_redirect( add_query_arg( 'processed', count( $item_ids ), $redirect_to ) );
        exit;
    }

    private static function bulk_delete_items($item_ids, $settings) {
        global $wpdb;
        $deleteds = [];
        foreach ($item_ids as $item_id) {
            UFSC_Scope::assert_club_in_scope( $item_id );
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT count(*) as nb FROM `{$settings['table_licences']}` WHERE club_id = %d", $item_id ) );
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
            UFSC_Scope::assert_club_in_scope( $item_id );
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
            UFSC_Scope::assert_club_in_scope( $item_id );
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
            UFSC_Scope::assert_club_in_scope( $item_id );
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
