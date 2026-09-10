<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Espace admin FFST.
 *
 * Réservé à l'administration UFSC : aucun document FFST n'est exposé dans
 * l'espace du représentant du club. La page est strictement en lecture seule
 * pour les filtres, recherches et indicateurs de complétude.
 */
final class UFSC_FFST_Documents_Admin {

    const DEFAULT_PER_PAGE = 20;

    public static function render() {
        if ( ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder aux dossiers FFST.', 'ufsc-clubs' ) );
        }

        $current_season = class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );

        $requested_season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtres lecture seule.
            ? sanitize_text_field( wp_unslash( $_GET['season'] ) )
            : $current_season;
        $season = self::normalize_season( $requested_season, $current_season );

        $club_id = isset( $_GET['club_id'] ) ? absint( wp_unslash( $_GET['club_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre lecture seule.
        $search = isset( $_GET['s'] ) && ! is_array( $_GET['s'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre lecture seule.
            ? sanitize_text_field( wp_unslash( $_GET['s'] ) )
            : '';
        $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination lecture seule.
        $per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : self::DEFAULT_PER_PAGE; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination lecture seule.
        if ( ! in_array( $per_page, array( 10, 20, 50, 100 ), true ) ) {
            $per_page = self::DEFAULT_PER_PAGE;
        }

        $page_data = self::get_clubs_page( $search, $paged, $per_page );
        $club = $club_id ? self::get_club( $club_id ) : null;
        $seasons = self::get_seasons( $season );

        echo '<div class="wrap ufsc-ffst-admin">';
        self::render_styles();
        if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); }
        echo '<h1>' . esc_html__( 'Dossiers FFST', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Recherche, contrôle et préparation des dossiers FFST par club et par saison.', 'ufsc-clubs' ) . '</p>';

        self::render_filters( $search, $season, $seasons, $per_page );
        self::render_clubs_table( $page_data, $search, $season, $paged, $per_page, $club_id );
        self::render_pagination( $page_data['total'], $paged, $per_page );

        if ( $club ) {
            echo '<hr class="ufsc-ffst-divider">';
            echo '<h2 class="ufsc-ffst-selected-title">' . esc_html__( 'Dossier sélectionné', 'ufsc-clubs' ) . '</h2>';
            $readiness = self::build_readiness( $club, $club_id, $season );
            self::render_summary( $club, $season, $readiness );
            self::render_affiliation_section( $readiness );
            self::render_licences_section( $readiness );
            self::render_documents_section( $club_id, $season, $readiness );
        }

        echo '</div>';
    }

    private static function render_styles() {
        echo '<style>
            .ufsc-ffst-admin{max-width:none}
            .ufsc-ffst-admin .postbox{max-width:none;box-sizing:border-box}
            .ufsc-ffst-filters{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:18px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px}
            .ufsc-ffst-filters label{display:grid;gap:5px}
            .ufsc-ffst-filters input[type="search"]{min-width:320px}
            .ufsc-ffst-filters select{min-width:150px}
            .ufsc-ffst-table-wrap{overflow-x:auto;background:#fff;border:1px solid #dcdcde;border-radius:10px}
            .ufsc-ffst-table-wrap table{border:0}
            .ufsc-ffst-table-wrap th,.ufsc-ffst-table-wrap td{vertical-align:middle}
            .ufsc-ffst-progress{display:flex;align-items:center;gap:8px;min-width:170px}
            .ufsc-ffst-progress__bar{width:100px;height:8px;background:#dcdcde;border-radius:999px;overflow:hidden}
            .ufsc-ffst-progress__value{height:100%;background:#2271b1;border-radius:999px}
            .ufsc-ffst-pagination{margin:16px 0;display:flex;justify-content:flex-end}
            .ufsc-ffst-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;margin-left:4px;border:1px solid #c3c4c7;background:#fff;text-decoration:none;border-radius:4px}
            .ufsc-ffst-pagination .page-numbers.current{background:#2271b1;color:#fff;border-color:#2271b1}
            .ufsc-ffst-divider{margin:28px 0 18px}
            .ufsc-ffst-selected-title{font-size:20px}
            @media(max-width:782px){
                .ufsc-ffst-filters{align-items:stretch}
                .ufsc-ffst-filters label,.ufsc-ffst-filters input[type="search"],.ufsc-ffst-filters select{width:100%;min-width:0}
                .ufsc-ffst-pagination{justify-content:flex-start}
            }
        </style>';
    }

    private static function render_filters( $search, $season, $seasons, $per_page ) {
        echo '<form method="get" class="ufsc-ffst-filters">';
        echo '<input type="hidden" name="page" value="ufsc-ffst-documents">';
        echo '<label><strong>' . esc_html__( 'Recherche', 'ufsc-clubs' ) . '</strong><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nom du club, ID, n° affiliation…', 'ufsc-clubs' ) . '"></label>';
        echo '<label><strong>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</strong><select name="season">';
        foreach ( $seasons as $row_season ) {
            echo '<option value="' . esc_attr( $row_season ) . '"' . selected( $season, $row_season, false ) . '>' . esc_html( $row_season ) . '</option>';
        }
        echo '</select></label>';
        echo '<label><strong>' . esc_html__( 'Par page', 'ufsc-clubs' ) . '</strong><select name="per_page">';
        foreach ( array( 10, 20, 50, 100 ) as $size ) {
            echo '<option value="' . esc_attr( $size ) . '"' . selected( $per_page, $size, false ) . '>' . esc_html( $size ) . '</option>';
        }
        echo '</select></label>';
        echo '<button class="button button-primary">' . esc_html__( 'Filtrer', 'ufsc-clubs' ) . '</button>';
        if ( '' !== $search || $season !== self::current_season() || self::DEFAULT_PER_PAGE !== $per_page ) {
            echo '<a class="button" href="' . esc_url( add_query_arg( 'page', 'ufsc-ffst-documents', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Réinitialiser', 'ufsc-clubs' ) . '</a>';
        }
        echo '</form>';
    }

    private static function render_clubs_table( $page_data, $search, $season, $paged, $per_page, $selected_club_id ) {
        $rows = isset( $page_data['items'] ) ? (array) $page_data['items'] : array();
        $total = absint( $page_data['total'] ?? 0 );

        echo '<p><strong>' . esc_html( sprintf( _n( '%d club trouvé', '%d clubs trouvés', $total, 'ufsc-clubs' ), $total ) ) . '</strong></p>';
        echo '<div class="ufsc-ffst-table-wrap"><table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__( 'Club', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Identifiant', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Complétude', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Action', 'ufsc-clubs' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( ! $rows ) {
            echo '<tr><td colspan="6">' . esc_html__( 'Aucun club ne correspond aux filtres.', 'ufsc-clubs' ) . '</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $id = absint( self::value( $row, array( 'id', 'club_id', 'ID' ) ) );
                $name = self::value( $row, array( 'nom', 'name', 'club_name' ) );
                $identifier = self::value( $row, array( 'numero_affiliation_ffst', 'numero_affiliation_ufsc', 'num_affiliation' ) );
                $readiness = self::build_readiness( $row, $id, $season );
                $url = add_query_arg(
                    array(
                        'page' => 'ufsc-ffst-documents',
                        'club_id' => $id,
                        'season' => $season,
                        's' => $search,
                        'paged' => $paged,
                        'per_page' => $per_page,
                    ),
                    admin_url( 'admin.php' )
                );
                echo '<tr' . ( $selected_club_id === $id ? ' class="is-selected"' : '' ) . '>';
                echo '<td><strong>' . esc_html( $name ?: sprintf( __( 'Club #%d', 'ufsc-clubs' ), $id ) ) . '</strong></td>';
                echo '<td>' . esc_html( $identifier ?: '#' . $id ) . '</td>';
                echo '<td>' . esc_html( $season ) . '</td>';
                echo '<td>' . esc_html( $readiness['licence_count'] ) . '/10</td>';
                echo '<td><div class="ufsc-ffst-progress"><div class="ufsc-ffst-progress__bar"><div class="ufsc-ffst-progress__value" style="width:' . esc_attr( $readiness['percent'] ) . '%"></div></div><strong>' . esc_html( $readiness['percent'] ) . '%</strong></div></td>';
                echo '<td><a class="button' . ( $selected_club_id === $id ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html__( 'Ouvrir le dossier', 'ufsc-clubs' ) . '</a></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    private static function render_pagination( $total, $paged, $per_page ) {
        $pages = max( 1, (int) ceil( absint( $total ) / max( 1, absint( $per_page ) ) ) );
        if ( $pages <= 1 ) {
            return;
        }

        $base_url = remove_query_arg( array( 'paged', 'club_id' ) );
        $links = paginate_links(
            array(
                'base' => add_query_arg( 'paged', '%#%', $base_url ),
                'format' => '',
                'current' => min( $paged, $pages ),
                'total' => $pages,
                'type' => 'array',
                'prev_text' => '‹',
                'next_text' => '›',
            )
        );
        if ( ! $links ) {
            return;
        }

        echo '<nav class="ufsc-ffst-pagination" aria-label="' . esc_attr__( 'Pagination des clubs', 'ufsc-clubs' ) . '">';
        foreach ( $links as $link ) {
            echo wp_kses_post( $link );
        }
        echo '</nav>';
    }

    private static function get_clubs_page( $search, $paged, $per_page ) {
        global $wpdb;

        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_clubs_table()
            : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $pk = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) )
            : 'id';
        $name_col = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'name', $columns, true ) ? 'name' : $pk );

        $where = array( '1=1' );
        $params = array();
        if ( '' !== $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $parts = array();
            foreach ( array( 'nom', 'name', 'club_name', 'numero_affiliation_ffst', 'numero_affiliation_ufsc', 'num_affiliation', 'email', 'ville' ) as $column ) {
                if ( in_array( $column, $columns, true ) ) {
                    $parts[] = "`{$column}` LIKE %s";
                    $params[] = $like;
                }
            }
            if ( ctype_digit( $search ) ) {
                $parts[] = "`{$pk}` = %d";
                $params[] = absint( $search );
            }
            if ( $parts ) {
                $where[] = '(' . implode( ' OR ', $parts ) . ')';
            }
        }

        $where_sql = implode( ' AND ', $where );
        $count_sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}";
        if ( $params ) {
            $count_sql = $wpdb->prepare( $count_sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        $total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $offset = ( max( 1, $paged ) - 1 ) * $per_page;
        $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY `{$name_col}` ASC LIMIT %d OFFSET %d";
        $query_params = array_merge( $params, array( $per_page, $offset ) );
        $prepared = $wpdb->prepare( $query, $query_params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $items = (array) $wpdb->get_results( $prepared ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return array( 'items' => $items, 'total' => $total );
    }

    private static function get_seasons( $selected = '' ) {
        global $wpdb;

        $current = self::current_season();
        $seasons = array( $current );
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            $seasons = array_merge(
                $seasons,
                (array) UFSC_Season_Service::get_available_seasons(),
                array( UFSC_Season_Service::get_previous_season() )
            );
        }

        $table = class_exists( 'UFSC_Storage_Resolver' )
            ? UFSC_Storage_Resolver::get_licences_table()
            : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $season_col = function_exists( 'ufsc_get_detected_season_column' )
            ? ufsc_get_detected_season_column( $table )
            : self::first_existing( $columns, array( 'paid_season', 'season', 'saison', 'season_end_year' ) );

        if ( $season_col ) {
            $values = (array) $wpdb->get_col( "SELECT DISTINCT `{$season_col}` FROM `{$table}` WHERE `{$season_col}` IS NOT NULL AND `{$season_col}` <> ''" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ( $values as $value ) {
                if ( 'season_end_year' === $season_col && is_numeric( $value ) ) {
                    $end = absint( $value );
                    if ( $end > 0 ) {
                        $seasons[] = sprintf( '%d-%d', $end - 1, $end );
                    }
                } else {
                    $seasons[] = (string) $value;
                }
            }
        }
        if ( $selected ) {
            $seasons[] = $selected;
        }

        $seasons = array_values(
            array_unique(
                array_filter(
                    array_map( array( __CLASS__, 'normalize_season_value' ), $seasons )
                )
            )
        );
        usort(
            $seasons,
            static function( $a, $b ) {
                return strcmp( $b, $a );
            }
        );
        return $seasons ?: array( $current );
    }

    private static function current_season() {
        return class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
    }

    private static function normalize_season( $season, $fallback = '' ) {
        $normalized = self::normalize_season_value( $season );
        return $normalized ?: self::normalize_season_value( $fallback );
    }

    private static function normalize_season_value( $season ) {
        if ( class_exists( 'UFSC_Season_Service' ) ) {
            return (string) UFSC_Season_Service::normalize_season( $season );
        }
        $season = trim( str_replace( '/', '-', (string) $season ) );
        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
    }

    private static function first_existing( $columns, $candidates ) {
        foreach ( $candidates as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                return $candidate;
            }
        }
        return '';
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : 'club_id' );
        $season_col = function_exists( 'ufsc_get_detected_season_column' )
            ? ufsc_get_detected_season_column( $table )
            : self::first_existing( $columns, array( 'paid_season', 'season', 'saison', 'season_end_year' ) );

        if ( $season_col ) {
            $season_value = $season;
            if ( 'season_end_year' === $season_col && function_exists( 'ufsc_get_season_end_year_from_label' ) ) {
                $season_value = ufsc_get_season_end_year_from_label( $season );
            }
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `{$season_col}`=%s", $club_id, $season_value ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function build_readiness( $club, $club_id, $season ) {
        $licences = self::get_licences( $club_id, $season );
        $fields = array(
            'Nom du club' => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            'Adresse du siège' => self::value( $club, array( 'adresse', 'adresse_siege', 'adresse_complete', 'address' ) ),
            'Téléphone' => self::value( $club, array( 'telephone', 'tel', 'phone' ) ),
            'Mail club' => self::value( $club, array( 'email', 'mail', 'club_email' ) ),
            'Site Internet' => self::value( $club, array( 'site', 'site_web', 'website', 'url_site', 'url' ) ),
            'Déclaration préfecture' => self::value( $club, array( 'numero_recepisse', 'recepisse', 'declaration_prefecture', 'num_declaration', 'numero_declaration', 'rna_number', 'rna' ) ),
            'Adresse salle d’entraînement' => self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ),
        );
        $filled = count( array_filter( $fields, static function( $value ) { return '' !== trim( (string) $value ); } ) );
        $roles = array(
            'Président' => self::find_role_licence( $licences, array( 'president', 'président' ) ),
            'Secrétaire' => self::find_role_licence( $licences, array( 'secretaire', 'secrétaire' ) ),
            'Trésorier' => self::find_role_licence( $licences, array( 'tresorier', 'trésorier' ) ),
            'Entraîneur / instructeur' => self::find_role_licence( $licences, array( 'entraineur', 'entraîneur', 'instructeur', 'coach' ) ),
        );
        $role_count = count( array_filter( $roles ) );
        $total = count( $licences );
        $percent = (int) round( ( ( $filled + $role_count + min( 10, $total ) ) / ( count( $fields ) + count( $roles ) + 10 ) ) * 100 );
        return array(
            'fields' => $fields,
            'roles' => $roles,
            'licences' => $licences,
            'licence_count' => $total,
            'minimum_licences_ok' => $total >= 10,
            'percent' => max( 0, min( 100, $percent ) ),
        );
    }

    private static function render_summary( $club, $season, $readiness ) {
        $name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        echo '<div class="ufsc-dashboard-cards" style="margin:20px 0;">';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Club', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $name ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Saison FFST', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $season ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Dossier prêt', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['percent'] ) . '%</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Licences saison', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['licence_count'] ) . '/10</div></div>';
        echo '</div>';
    }

    private static function render_affiliation_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;margin-top:20px;"><h2 style="margin-top:0;">' . esc_html__( '1. Dossier affiliation / réaffiliation FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Information officielle FFST', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Valeur UFSC', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'État', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( $readiness['fields'] as $label => $value ) {
            $ok = '' !== trim( (string) $value );
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $value ?: '—' ) . '</td><td>' . ( $ok ? '<span style="color:#008a20;font-weight:700;">✓ Complet</span>' : '<span style="color:#b32d2e;font-weight:700;">À compléter</span>' ) . '</td></tr>';
        }
        foreach ( $readiness['roles'] as $label => $licence ) {
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $licence ? self::person_name( $licence ) : '—' ) . '</td><td>' . ( $licence ? '<span style="color:#008a20;font-weight:700;">✓ Identifié</span>' : '<span style="color:#b32d2e;font-weight:700;">Licence dirigeant manquante</span>' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_licences_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">' . esc_html__( '2. Bordereau licences dirigeants FFST', 'ufsc-clubs' ) . '</h2>';
        if ( $readiness['minimum_licences_ok'] ) {
            echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Minimum FFST atteint : au moins 10 licences pour la saison.', 'ufsc-clubs' ) . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p><strong>' . esc_html( sprintf( __( 'Minimum FFST non atteint : %1$d licence(s) enregistrée(s), 10 requises.', 'ufsc-clubs' ), $readiness['licence_count'] ) ) . '</strong></p></div>';
        }
        echo '<p>' . esc_html__( 'Le président, le secrétaire, le trésorier et le ou les entraîneurs doivent être identifiés parmi les licences de la saison. Une donnée manquante est signalée mais ne bloque plus la génération administrateur.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function render_documents_section( $club_id, $season, $readiness ) {
        if ( class_exists( 'UFSC_FFST_Export_Admin' ) ) {
            UFSC_FFST_Export_Admin::render_actions( $club_id, $season, $readiness );
            return;
        }
        echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Le module de génération FFST n’est pas disponible. Aucun document n’a été modifié.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function find_role_licence( $licences, $aliases ) {
        foreach ( (array) $licences as $licence ) {
            $role = strtolower( remove_accents( trim( (string) self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) ) ) );
            foreach ( $aliases as $alias ) {
                if ( false !== strpos( $role, strtolower( remove_accents( $alias ) ) ) ) { return $licence; }
            }
        }
        return null;
    }

    private static function person_name( $licence ) {
        return trim( self::value( $licence, array( 'prenom', 'first_name' ) ) . ' ' . self::value( $licence, array( 'nom', 'last_name' ) ) );
    }

    private static function value( $object, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( is_object( $object ) && isset( $object->{$key} ) && '' !== trim( (string) $object->{$key} ) ) { return (string) $object->{$key}; }
            if ( is_array( $object ) && isset( $object[$key] ) && '' !== trim( (string) $object[$key] ) ) { return (string) $object[$key]; }
        }
        return '';
    }
}
