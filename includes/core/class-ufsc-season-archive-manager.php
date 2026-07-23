<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Non-destructive season archive foundation.
 *
 * Clubs remain permanent records in the existing clubs table. Annual
 * affiliations are stored separately, while licences receive optional season
 * metadata. No existing column is renamed or removed so integrations that read
 * UFSC Gestion tables keep working.
 */
final class UFSC_Season_Archive_Manager {
    const SCHEMA_VERSION = '1.0.0';
    const SCHEMA_OPTION  = 'ufsc_season_archive_schema_version';
    const TABLE_SUFFIX   = 'ufsc_affiliation_seasons';

    /**
     * Register hooks.
     *
     * @return void
     */
    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'maybe_migrate' ), 20 );
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ), 30 );
    }

    /**
     * Run idempotent schema changes.
     *
     * @return void
     */
    public static function maybe_migrate() {
        $installed = (string) get_option( self::SCHEMA_OPTION, '0.0.0' );
        if ( version_compare( $installed, self::SCHEMA_VERSION, '>=' ) ) {
            return;
        }

        $ok = self::create_affiliation_seasons_table();
        $ok = self::ensure_licence_season_columns() && $ok;

        if ( $ok ) {
            self::backfill_existing_licence_seasons();
            update_option( self::SCHEMA_OPTION, self::SCHEMA_VERSION, false );
        }
    }

    /**
     * Create the annual affiliation table without modifying club records.
     *
     * @return bool
     */
    private static function create_affiliation_seasons_table() {
        global $wpdb;

        $table   = self::get_affiliation_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            club_id bigint(20) unsigned NOT NULL,
            season varchar(9) NOT NULL,
            status varchar(30) NOT NULL DEFAULT 'draft',
            renewal_status varchar(30) NOT NULL DEFAULT 'initial',
            numero_affiliation varchar(100) NULL DEFAULT NULL,
            previous_affiliation_id bigint(20) unsigned NULL DEFAULT NULL,
            requested_at datetime NULL DEFAULT NULL,
            validated_at datetime NULL DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            metadata longtext NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_club_season (club_id, season),
            KEY idx_season (season),
            KEY idx_status (status),
            KEY idx_numero_affiliation (numero_affiliation),
            KEY idx_previous_affiliation (previous_affiliation_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        return self::table_exists( $table );
    }

    /**
     * Add optional columns to the existing licences table.
     *
     * These columns are nullable/defaulted so existing INSERT statements and
     * the Licence & Competition plugin remain compatible.
     *
     * @return bool
     */
    private static function ensure_licence_season_columns() {
        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return false;
        }

        $settings = UFSC_SQL::get_settings();
        $table    = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( ! $table || ! self::table_exists( $table ) ) {
            return false;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( ! is_array( $columns ) ) {
            return false;
        }

        $expected = array(
            'season'              => "varchar(9) NULL DEFAULT NULL",
            'season_start_year'   => "smallint(4) unsigned NULL DEFAULT NULL",
            'season_end_year'     => "smallint(4) unsigned NULL DEFAULT NULL",
            'previous_licence_id' => "bigint(20) unsigned NULL DEFAULT NULL",
            'renewal_status'      => "varchar(30) NOT NULL DEFAULT 'initial'",
            'renewed_at'          => "datetime NULL DEFAULT NULL",
        );

        foreach ( $expected as $column => $definition ) {
            if ( in_array( $column, $columns, true ) ) {
                continue;
            }

            $result = $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}" );
            if ( false === $result ) {
                self::log_error( sprintf( 'Impossible d’ajouter la colonne %s à %s : %s', $column, $table, $wpdb->last_error ) );
                return false;
            }
        }

        self::add_index_if_missing( $table, 'idx_licences_season', 'season' );
        self::add_index_if_missing( $table, 'idx_licences_previous', 'previous_licence_id' );
        self::add_index_if_missing( $table, 'idx_licences_club_season', 'club_id, season' );

        if ( function_exists( 'ufsc_flush_table_columns_cache' ) ) {
            ufsc_flush_table_columns_cache();
        }

        return true;
    }

    /**
     * Assign a season only to records where it is currently absent.
     * Existing explicit season values are never overwritten.
     *
     * @return void
     */
    private static function backfill_existing_licence_seasons() {
        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return;
        }

        $settings = UFSC_SQL::get_settings();
        $table    = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( ! $table || ! self::table_exists( $table ) ) {
            return;
        }

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( ! is_array( $columns ) || ! in_array( 'season', $columns, true ) ) {
            return;
        }

        $date_column = '';
        foreach ( array( 'date_creation', 'date_inscription', 'created_at' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                $date_column = $candidate;
                break;
            }
        }

        if ( $date_column ) {
            $wpdb->query(
                "UPDATE `{$table}`
                 SET season = CASE
                    WHEN MONTH(`{$date_column}`) >= 8
                        THEN CONCAT(YEAR(`{$date_column}`), '-', YEAR(`{$date_column}`) + 1)
                    ELSE CONCAT(YEAR(`{$date_column}`) - 1, '-', YEAR(`{$date_column}`))
                 END
                 WHERE (season IS NULL OR season = '')
                   AND `{$date_column}` IS NOT NULL"
            );
        }

        $fallback = class_exists( 'UFSC_Season_Service' )
            ? UFSC_Season_Service::get_current_season()
            : '';

        if ( $fallback ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE `{$table}` SET season = %s WHERE season IS NULL OR season = ''",
                    $fallback
                )
            );
        }

        $wpdb->query(
            "UPDATE `{$table}`
             SET season_start_year = CAST(SUBSTRING_INDEX(season, '-', 1) AS UNSIGNED),
                 season_end_year = CAST(SUBSTRING_INDEX(season, '-', -1) AS UNSIGNED)
             WHERE season REGEXP '^[0-9]{4}-[0-9]{4}$'
               AND (season_start_year IS NULL OR season_end_year IS NULL)"
        );
    }

    /**
     * Register an archive overview page.
     *
     * @return void
     */
    public static function register_admin_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) ) {
            return;
        }

        add_submenu_page(
            'ufsc-dashboard',
            __( 'Archives par saison', 'ufsc-clubs' ),
            __( 'Saisons & archives', 'ufsc-clubs' ),
            UFSC_Permissions::CAP_GESTION_READ,
            'ufsc-seasons-archives',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    /**
     * Render a read-only overview for the first rollout.
     *
     * @return void
     */
    public static function render_admin_page() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_READ ) ) {
            wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        global $wpdb;

        $current = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '';
        $rows    = self::get_season_counts();

        echo '<div class="wrap ufsc-season-archives">';
        echo '<h1>' . esc_html__( 'Saisons et archives UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Vue non destructive des licences classées par saison. Les clubs conservent leur identifiant permanent.', 'ufsc-clubs' ) . '</p>';

        if ( $current ) {
            echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Saison configurée :', 'ufsc-clubs' ) . '</strong> ' . esc_html( $current ) . '</p></div>';
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Saison', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Licences', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Clubs concernés', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Affiliations annuelles', 'ufsc-clubs' ) . '</th>';
        echo '<th>' . esc_html__( 'Accès rapide', 'ufsc-clubs' ) . '</th>';
        echo '</tr></thead><tbody>';

        if ( empty( $rows ) ) {
            echo '<tr><td colspan="5">' . esc_html__( 'Aucune donnée de saison disponible.', 'ufsc-clubs' ) . '</td></tr>';
        } else {
            foreach ( $rows as $row ) {
                $season = (string) $row['season'];
                $url    = add_query_arg(
                    array(
                        'page'   => 'ufsc_lc_licences',
                        'season' => $season,
                    ),
                    admin_url( 'admin.php' )
                );

                echo '<tr>';
                echo '<td><strong>' . esc_html( $season ) . '</strong>' . ( $season === $current ? ' <span class="dashicons dashicons-yes-alt" title="' . esc_attr__( 'Saison courante', 'ufsc-clubs' ) . '"></span>' : '' ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( (int) $row['licence_count'] ) ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( (int) $row['club_count'] ) ) . '</td>';
                echo '<td>' . esc_html( number_format_i18n( (int) $row['affiliation_count'] ) ) . '</td>';
                echo '<td><a class="button button-secondary" href="' . esc_url( $url ) . '">' . esc_html__( 'Voir les licences', 'ufsc-clubs' ) . '</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__( 'Les numéros ASPTT de licence et d’affiliation restent facultatifs et peuvent être saisis ultérieurement.', 'ufsc-clubs' ) . '</p>';
        echo '</div>';
    }

    /**
     * Return season counters without changing source data.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function get_season_counts() {
        global $wpdb;

        if ( ! class_exists( 'UFSC_SQL' ) ) {
            return array();
        }

        $settings       = UFSC_SQL::get_settings();
        $licences_table = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
        if ( ! $licences_table || ! self::table_exists( $licences_table ) ) {
            return array();
        }

        $affiliations_table = self::get_affiliation_table();
        $affiliation_counts = array();
        if ( self::table_exists( $affiliations_table ) ) {
            $items = $wpdb->get_results(
                "SELECT season, COUNT(*) AS total FROM `{$affiliations_table}` GROUP BY season",
                ARRAY_A
            );
            foreach ( (array) $items as $item ) {
                $affiliation_counts[ $item['season'] ] = (int) $item['total'];
            }
        }

        $licence_rows = $wpdb->get_results(
            "SELECT season, COUNT(*) AS licence_count, COUNT(DISTINCT club_id) AS club_count
             FROM `{$licences_table}`
             WHERE season IS NOT NULL AND season <> ''
             GROUP BY season
             ORDER BY season DESC",
            ARRAY_A
        );

        $result = array();
        foreach ( (array) $licence_rows as $row ) {
            $season = (string) $row['season'];
            $result[] = array(
                'season'            => $season,
                'licence_count'     => (int) $row['licence_count'],
                'club_count'        => (int) $row['club_count'],
                'affiliation_count' => isset( $affiliation_counts[ $season ] ) ? $affiliation_counts[ $season ] : 0,
            );
        }

        return $result;
    }

    /**
     * Public helper for integrations and future renewal workflows.
     *
     * @return string
     */
    public static function get_affiliation_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * @param string $table Table name.
     * @return bool
     */
    private static function table_exists( $table ) {
        global $wpdb;
        return $table && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
    }

    /**
     * @param string $table Table name.
     * @param string $name  Index name.
     * @param string $cols  SQL column list controlled by this class.
     * @return void
     */
    private static function add_index_if_missing( $table, $name, $cols ) {
        global $wpdb;

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
                $name
            )
        );

        if ( ! $exists ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})" );
        }
    }

    /**
     * @param string $message Error message.
     * @return void
     */
    private static function log_error( $message ) {
        if ( class_exists( 'UFSC_Audit_Logger' ) && method_exists( 'UFSC_Audit_Logger', 'log' ) ) {
            UFSC_Audit_Logger::log( $message );
            return;
        }
        error_log( '[UFSC seasons] ' . $message );
    }
}

UFSC_Season_Archive_Manager::init();
