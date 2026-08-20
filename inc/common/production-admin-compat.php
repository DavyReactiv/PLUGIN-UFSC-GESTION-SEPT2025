<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Production admin compatibility fixes discovered during DEV acceptance.
 *
 * 1. WooCommerce settings historically redirect to a non-registered page slug
 *    after a successful save. Rewrite that redirect to the canonical UFSC menu.
 * 2. The admin duplicate-identity SQL historically compares name + first name +
 *    birth date across every season. A normal annual renewal is therefore shown
 *    as a duplicate of its archived source. Scope that comparison to the same
 *    stored season without mutating any licence row.
 * 3. admin-post.php may expose WC()->cart before WooCommerce has loaded its
 *    frontend cart helper functions. Licence renewal and new-licence payment
 *    flows can then fatal on wc_get_cart_item_data_hash(). Load the native Woo
 *    cart functions before every UFSC admin-post flow that can touch the cart.
 * 4. Legacy cart metadata labels every non-empty request_type as an affiliation
 *    renewal. Normalize the visible "Demande" label from the actual UFSC item
 *    type/action so licence renewals and new licences are described correctly.
 * 5. Included quota is now allocated before cart insertion. The historical cart
 *    repricer still queries obsolete club columns and can incorrectly zero a
 *    paid over-quota licence. Remove that legacy hook in the production layer.
 * 6. Legacy identifier columns use an empty string for "not assigned". MySQL
 *    UNIQUE constraints treat repeated empty strings as duplicates; normalize
 *    only those absent values to SQL NULL before migrations run.
 * 7. Strict MySQL rejects comparisons between DATETIME columns and ''. Rewrite
 *    the two legacy read-only predicates to compare their CHAR representation.
 */

/**
 * Rewrite the obsolete post-save WooCommerce settings route.
 *
 * @param string $location Redirect target.
 * @param int    $status   HTTP status.
 * @return string
 */
function ufsc_production_fix_woocommerce_settings_redirect( $location, $status ) {
    unset( $status );

    if ( ! is_string( $location ) || false === strpos( $location, 'page=ufsc-woocommerce-settings' ) ) {
        return $location;
    }

    return str_replace( 'page=ufsc-woocommerce-settings', 'page=ufsc-woocommerce', $location );
}
add_filter( 'wp_redirect', 'ufsc_production_fix_woocommerce_settings_redirect', 998, 2 );

/**
 * Ensure native WooCommerce cart helper functions exist on UFSC admin-post flows.
 *
 * WooCommerce can have a cart object available in an admin-post request while
 * frontend cart functions have not yet been included. WC_Cart and
 * WC_Cart_Session both require wc_get_cart_item_data_hash(), which lives in
 * wc-cart-functions.php. Loading the official WooCommerce file is safer than
 * reimplementing helpers or instantiating a second cart.
 */
function ufsc_production_bootstrap_woocommerce_cart_functions() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return;
    }

    $action = isset( $_POST['action'] ) && ! is_array( $_POST['action'] )
        ? sanitize_key( wp_unslash( $_POST['action'] ) )
        : '';
    $cart_actions = array(
        'ufsc_bulk_renew_licences',
        'ufsc_add_to_cart',
        'ufsc_affiliation_pay',
        'ufsc_save_licence',
        'ufsc_add_licence',
        'ufsc_update_licence',
        'ufsc_journey_finalize_licence',
    );
    if ( ! in_array( $action, $cart_actions, true ) ) {
        return;
    }

    if ( function_exists( 'wc_get_cart_item_data_hash' ) ) {
        return;
    }

    if ( ! defined( 'WC_ABSPATH' ) ) {
        return;
    }

    $cart_functions = trailingslashit( WC_ABSPATH ) . 'includes/wc-cart-functions.php';
    if ( is_readable( $cart_functions ) ) {
        include_once $cart_functions;
    }
}
add_action( 'admin_init', 'ufsc_production_bootstrap_woocommerce_cart_functions', -90 );

/**
 * Correct the visible UFSC request type in WooCommerce cart metadata.
 *
 * The legacy display callback treats any non-empty ufsc_request_type as an
 * affiliation renewal. Use the cart item's canonical action/type instead and
 * replace the existing "Demande" row rather than adding a second one.
 *
 * @param array $item_data Existing WooCommerce display rows.
 * @param array $cart_item Cart item payload.
 * @return array
 */
function ufsc_production_fix_cart_request_label( $item_data, $cart_item ) {
    if ( ! is_array( $item_data ) || ! is_array( $cart_item ) ) {
        return $item_data;
    }

    $action         = sanitize_key( (string) ( $cart_item['ufsc_action'] ?? '' ) );
    $item_type      = sanitize_key( (string) ( $cart_item['ufsc_item_type'] ?? '' ) );
    $operation_type = sanitize_key( (string) ( $cart_item['ufsc_operation_type'] ?? '' ) );
    $request_type   = sanitize_key( (string) ( $cart_item['ufsc_request_type'] ?? '' ) );
    $has_licence    = ! empty( $cart_item['ufsc_licence_id'] ) || ! empty( $cart_item['ufsc_license_ids'] ) || ! empty( $cart_item['ufsc_licence_ids'] );

    $label = '';
    if ( 'renew_affiliation' === $action || 'affiliation_renewal' === $item_type ) {
        $label = __( 'Renouvellement d’affiliation', 'ufsc-clubs' );
    } elseif ( 'renew_licence' === $action || 'licence_renewal' === $item_type || ( $has_licence && 'renewal' === $operation_type ) ) {
        $label = __( 'Renouvellement de licence', 'ufsc-clubs' );
    } elseif ( $has_licence && ( 'new_licence' === $operation_type || 'new' === $request_type ) ) {
        $label = __( 'Nouvelle licence', 'ufsc-clubs' );
    }

    if ( '' === $label ) {
        return $item_data;
    }

    $filtered = array();
    foreach ( $item_data as $row ) {
        $key = is_array( $row ) && isset( $row['key'] ) ? wp_strip_all_tags( (string) $row['key'] ) : '';
        if ( 'Demande' === $key ) {
            continue;
        }
        $filtered[] = $row;
    }
    $filtered[] = array(
        'key'   => __( 'Demande', 'ufsc-clubs' ),
        'value' => $label,
    );

    return $filtered;
}
add_filter( 'woocommerce_get_item_data', 'ufsc_production_fix_cart_request_label', 999, 2 );

/**
 * Remove the obsolete cart-time included-quota repricer.
 *
 * Canonical licence finalization consumes included pack credits before a cart
 * line is created; only the over-quota paid remainder reaches WooCommerce. The
 * legacy callback reads removed columns such as included_quota_used and can
 * incorrectly turn a genuinely paid line into a zero-price line.
 */
function ufsc_production_remove_legacy_cart_quota_repricing() {
    remove_action( 'woocommerce_before_calculate_totals', 'ufsc_apply_included_quota_to_cart', 10 );
}
add_action( 'wp_loaded', 'ufsc_production_remove_legacy_cart_quota_repricing', 5 );

/**
 * Normalize optional legacy identifiers before the migration layer creates
 * UNIQUE constraints. Empty string and NULL both mean "identifier not assigned";
 * SQL NULL is the representation compatible with multiple missing values.
 */
function ufsc_production_prepare_optional_unique_identifiers() {
    global $wpdb;

    if ( ! class_exists( 'UFSC_SQL' ) ) {
        return;
    }

    $settings = (array) UFSC_SQL::get_settings();
    $targets  = array(
        array( (string) ( $settings['table_licences'] ?? '' ), 'numero_licence_delegataire' ),
        array( (string) ( $settings['table_clubs'] ?? '' ), 'num_affiliation' ),
    );

    foreach ( $targets as $target ) {
        $table  = preg_replace( '/[^A-Za-z0-9_]/', '', $target[0] );
        $column = preg_replace( '/[^A-Za-z0-9_]/', '', $target[1] );
        if ( '' === $table || '' === $column ) {
            continue;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are strictly allow-listed above.
        $column_info = $wpdb->get_row( $wpdb->prepare( "SHOW COLUMNS FROM `{$table}` LIKE %s", $column ) );
        if ( ! $column_info ) {
            continue;
        }

        // These two identifier fields are textual and optional by business rule.
        // Preserve their exact SQL type while allowing NULL when a legacy schema
        // still declares the column NOT NULL DEFAULT ''.
        if ( 'YES' !== strtoupper( (string) ( $column_info->Null ?? '' ) ) ) {
            $type = strtolower( trim( (string) ( $column_info->Type ?? '' ) ) );
            if ( ! preg_match( '/^(?:var)?char\([0-9]+\)$/', $type ) ) {
                continue;
            }
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column/type have strict validation.
            $wpdb->query( "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$type} NULL DEFAULT NULL" );
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- identifiers are strictly allow-listed above.
        $wpdb->query( "UPDATE `{$table}` SET `{$column}` = NULL WHERE `{$column}` IS NOT NULL AND TRIM(CAST(`{$column}` AS CHAR)) = ''" );
    }
}

// feature-flags.php is loaded before UFSC_CL_Bootstrap registers its activation
// callback, so this repair executes first on activation and prevents the legacy
// ADD CONSTRAINT statements from seeing repeated empty-string values.
if ( defined( 'UFSC_CL_DIR' ) ) {
    register_activation_hook( UFSC_CL_DIR . 'ufsc-clubs-licences-sql.php', 'ufsc_production_prepare_optional_unique_identifiers' );
}
add_action( 'plugins_loaded', 'ufsc_production_prepare_optional_unique_identifiers', 1 );

/**
 * Make legacy UFSC read-only date predicates compatible with strict MySQL.
 *
 * This filter is intentionally pure: it performs no DB/helper call, so it cannot
 * re-enter wpdb::query(). It only rewrites known UFSC legacy SQL fragments.
 *
 * @param string $query SQL query.
 * @return string
 */
function ufsc_production_strict_datetime_query_compat( $query ) {
    if ( ! is_string( $query ) || false === stripos( $query, 'ufsc_' ) ) {
        return $query;
    }

    $deleted_pattern = "/\(((?:[A-Za-z0-9_]+\.)?`deleted_at`)\s+IS\s+NULL\s+OR\s+\\1\s*=\s*''\s+OR\s+\\1\s*=\s*'0000-00-00 00:00:00'\)/i";
    $query = preg_replace_callback(
        $deleted_pattern,
        static function( $matches ) {
            $column = $matches[1];
            return "({$column} IS NULL OR TRIM(CAST({$column} AS CHAR)) IN ('', '0000-00-00', '0000-00-00 00:00:00'))";
        },
        $query
    );

    $query = preg_replace_callback(
        '/DATE\(((?:[A-Za-z0-9_]+\.)?`(?:date_affiliation|date_validation|date_asptt)`)\)/i',
        static function( $matches ) {
            return 'LEFT(TRIM(CAST(' . $matches[1] . ' AS CHAR)), 10)';
        },
        $query
    );

    return is_string( $query ) ? $query : '';
}
add_filter( 'query', 'ufsc_production_strict_datetime_query_compat', 5 );

/**
 * Register a tightly-scoped SQL compatibility filter on licence admin lists.
 * Database helpers are resolved before the global `query` filter is attached to
 * avoid recursion inside wpdb::query().
 */
function ufsc_production_register_same_season_duplicate_filter() {
    if ( ! is_admin() || ! is_user_logged_in() ) {
        return;
    }

    $page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] )
        ? sanitize_key( wp_unslash( $_GET['page'] ) )
        : '';
    $licence_pages = array( 'ufsc_lc_licences', 'ufsc-gestion-licences', 'ufsc-licences', 'ufsc-sql-licences', 'ufsc-sql-licenses' );
    if ( ! in_array( $page, $licence_pages, true ) || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return;
    }

    $table = (string) ufsc_get_licences_table();
    if ( '' === $table ) {
        return;
    }

    $season_column = '';
    if ( function_exists( 'ufsc_get_detected_season_column' ) ) {
        $season_column = (string) ufsc_get_detected_season_column( $table );
    }

    if ( '' === $season_column ) {
        global $wpdb;
        $columns = function_exists( 'ufsc_table_columns' )
            ? (array) ufsc_table_columns( $table )
            : (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        foreach ( array( 'season', 'saison', 'paid_season', 'season_end_year' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                $season_column = $candidate;
                break;
            }
        }
    }

    $season_column = preg_replace( '/[^a-zA-Z0-9_]/', '', $season_column );
    if ( '' === $season_column ) {
        return;
    }

    $GLOBALS['ufsc_production_duplicate_table'] = $table;
    $GLOBALS['ufsc_production_duplicate_season_column'] = $season_column;
    add_filter( 'query', 'ufsc_production_scope_duplicate_identity_to_same_season', 998 );
}
add_action( 'admin_init', 'ufsc_production_register_same_season_duplicate_filter', 50 );

/**
 * Add same-season equality to the legacy duplicate-identity EXISTS subquery.
 *
 * The callback performs no database access. It only touches the exact licence
 * table query shape containing aliases `l` and `l2` generated by SQL admin.
 *
 * @param string $query SQL query.
 * @return string
 */
function ufsc_production_scope_duplicate_identity_to_same_season( $query ) {
    if ( ! is_string( $query ) ) {
        return $query;
    }

    $table = isset( $GLOBALS['ufsc_production_duplicate_table'] )
        ? (string) $GLOBALS['ufsc_production_duplicate_table']
        : '';
    $season_column = isset( $GLOBALS['ufsc_production_duplicate_season_column'] )
        ? (string) $GLOBALS['ufsc_production_duplicate_season_column']
        : '';

    if ( '' === $table || '' === $season_column || false === strpos( $query, $table ) || false === stripos( $query, 'EXISTS' ) ) {
        return $query;
    }

    $same_season_sql = "l2.`{$season_column}` <=> l.`{$season_column}`";
    if ( false !== strpos( $query, $same_season_sql ) ) {
        return $query;
    }

    // The duplicate detector always starts its correlated subquery with
    // `WHERE l2.<pk> <> l.<pk>`. Add the season condition exactly there.
    $pattern = '/(WHERE\s+l2\.`[^`]+`\s*<>\s*l\.`[^`]+`)/i';
    $replacement = '$1 AND ' . $same_season_sql;
    $scoped = preg_replace( $pattern, $replacement, $query, 1 );

    return is_string( $scoped ) ? $scoped : $query;
}
