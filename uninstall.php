<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package UFSC Gestion
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove stored options.
$option_names = [
    'ufsc_gestion_settings',
    'ufsc_woocommerce_settings',
    'ufsc_sql_settings',
    'ufsc_db_migration_version',
    'ufsc_license_pricing_rules',
    'ufsc_dashboard_page',
];

foreach ( $option_names as $option ) {
    delete_option( $option );
}

// Remove dynamically created options.
global $wpdb;
$like_patterns = [
    'ufsc_club_logo_%',
    'ufsc_club_doc_%',
];

foreach ( $like_patterns as $pattern ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        )
    );
}

