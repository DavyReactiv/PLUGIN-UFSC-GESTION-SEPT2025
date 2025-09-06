<?php
/**
 * EXAMPLE: Integration of UFSC Gestion stubs with existing database
 * 
 * This file shows how to implement the stub functions to work with
 * the existing wp_ufsc_clubs and wp_ufsc_licences tables.
 * 
 * Copy these implementations to the appropriate files and modify
 * according to your actual database schema.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * EXAMPLE: Get club ID for a user
 * 
 * This assumes the clubs table has a 'responsable_id' field linking to wp_users.id
 * Modify the field name according to your actual schema.
 */
function ufsc_get_user_club_id( $user_id ) {
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $club_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM `{$clubs_table}` WHERE responsable_id = %d LIMIT 1",
        $user_id
    ) );
    
    return $club_id ? (int) $club_id : false;
}

/**
 * EXAMPLE: Get club name by ID
 * 
 * This assumes the clubs table has a 'nom' field for the club name.
 */
function ufsc_get_club_name( $club_id ) {
    global $wpdb;
    $clubs_table = ufsc_get_clubs_table();
    
    $club_name = $wpdb->get_var( $wpdb->prepare(
        "SELECT nom FROM `{$clubs_table}` WHERE id = %d",
        $club_id
    ) );
    
    return $club_name ?: false;
}

/**
 * EXAMPLE: Mark affiliation as paid for a season
 * 
 * Option 1: If you have a separate affiliation_payments table
 */
function ufsc_mark_affiliation_paid( $club_id, $season ) {
    global $wpdb;
    
    // Option 1: Separate affiliation payments table
    $table = $wpdb->prefix . 'ufsc_affiliation_payments';
    
    $wpdb->replace(
        $table,
        array(
            'club_id' => $club_id,
            'season' => $season,
            'paid' => 1,
            'paid_date' => current_time( 'mysql' )
        ),
        array( '%d', '%s', '%d', '%s' )
    );
    
    /* Option 2: If you store in the clubs table with season-specific columns
    $clubs_table = ufsc_get_clubs_table();
    $season_column = 'affiliation_paid_' . str_replace( '-', '_', (string) ( $season ?? '' ) );
    
    $wpdb->update(
        $clubs_table,
        array( $season_column => 1 ),
        array( 'id' => $club_id ),
        array( '%d' ),
        array( '%d' )
    );
    */
}

/**
 * EXAMPLE: Mark a specific license as paid
 * 
 * This assumes your licences table has fields for payment tracking.
 */
function ufsc_mark_licence_paid( $license_id, $season ) {
    global $wpdb;
    $licences_table = ufsc_get_licences_table();
    
    $wpdb->update(
        $licences_table,
        array(
            'paid_season' => $season,
            'paid_date' => current_time( 'mysql' ),
            'is_included' => 0  // Mark as additional (not included in pack)
        ),
        array( 'id' => $license_id ),
        array( '%s', '%s', '%d' ),
        array( '%d' )
    );
}

/**
 * EXAMPLE: Add included licenses to club quota
 * 
 * This tracks the included licenses quota from affiliation packs.
 */
function ufsc_quota_add_included( $club_id, $quantity, $season ) {
    global $wpdb;
    
    // Option 1: Using a separate quota tracking table
    $quota_table = $wpdb->prefix . 'ufsc_quota_tracking';
    
    $wpdb->query( $wpdb->prepare(
        "INSERT INTO `{$quota_table}` (club_id, season, type, quantity, created_date) 
         VALUES (%d, %s, 'included', %d, %s)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)",
        $club_id,
        $season,
        $quantity,
        current_time( 'mysql' )
    ) );
    
    /* Option 2: If you store quota directly in clubs table
    $clubs_table = ufsc_get_clubs_table();
    
    $wpdb->query( $wpdb->prepare(
        "UPDATE `{$clubs_table}` 
         SET quota_included = COALESCE(quota_included, 0) + %d 
         WHERE id = %d",
        $quantity,
        $club_id
    ) );
    */
}

/**
 * EXAMPLE: Check if club should be charged for additional licenses
 * 
 * This compares available quota against used licenses.
 */
function ufsc_should_charge_license( $club_id, $season ) {
    global $wpdb;
    $licences_table = ufsc_get_licences_table();
    
    // Get total available quota (included + paid additional)
    $quota_table = $wpdb->prefix . 'ufsc_quota_tracking';
    
    $total_quota = $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(quantity), 0) 
         FROM `{$quota_table}` 
         WHERE club_id = %d AND season = %s",
        $club_id,
        $season
    ) );
    
    // Get total licenses for this club and season
    $used_licenses = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM `{$licences_table}` 
         WHERE club_id = %d AND (
             is_included = 1 OR 
             paid_season = %s
         )",
        $club_id,
        $season
    ) );
    
    // Return true if quota is exhausted
    return (int) $used_licenses >= (int) $total_quota;
}

/**
 * EXAMPLE: Database schema suggestions
 * 
 * If you need to add fields to existing tables, here are suggestions:
 * 
 * wp_ufsc_clubs table additions:
 * - responsable_id INT (if not exists) - links to wp_users.id
 * - quota_included INT DEFAULT 0 - included licenses from packs
 * - quota_paid INT DEFAULT 0 - additional paid licenses
 * 
 * wp_ufsc_licences table additions:
 * - is_included TINYINT(1) DEFAULT 0 - 1 if included in pack, 0 if additional
 * - paid_season VARCHAR(10) - season when paid (e.g., "2025-2026")
 * - paid_date DATETIME - when payment was processed
 * 
 * Optional separate tables:
 * 
 * wp_ufsc_affiliation_payments:
 * - club_id INT
 * - season VARCHAR(10)
 * - paid TINYINT(1)
 * - paid_date DATETIME
 * - woocommerce_order_id INT
 * 
 * wp_ufsc_quota_tracking:
 * - club_id INT
 * - season VARCHAR(10)
 * - type ENUM('included', 'paid')
 * - quantity INT
 * - created_date DATETIME
 * - UNIQUE KEY (club_id, season, type)
 */