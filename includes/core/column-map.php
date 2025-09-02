<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Column mapping system for flexible database column names
 * Allows adaptation to different DB schemas without hard-coding column names
 */

/**
 * Get column mapping for a specific table type
 * 
 * @param string $table_type 'clubs' or 'licences'
 * @return array Column mapping array
 */
function ufsc_sql_columns_map( $table_type ) {
    $defaults = array();
    
    if ( $table_type === 'clubs' ) {
        $defaults = array(
            'id' => 'id',
            'name' => 'nom',
            'email' => 'email',
            'phone' => 'telephone',
            'region' => 'region',
            'city' => 'ville',
            'zipcode' => 'code_postal',
            'address' => 'adresse',
            'status' => 'statut',
            'validated' => 'validated',
            'created_at' => 'date_creation',
            'manager_user_id' => 'responsable_id'
        );
        
        // Allow customization via filter
        $mapping = apply_filters( 'ufsc_clubs_columns_map', $defaults );
        
    } elseif ( $table_type === 'licences' ) {
        $defaults = array(
            'id' => 'id',
            'club_id' => 'club_id',
            'first_name' => 'prenom',
            'last_name' => 'nom',
            'email' => 'email',
            'status' => 'statut',
            'season' => 'season',
            'paid_flag' => 'is_paid',
            'paid_season' => 'paid_season',
            'created_at' => 'date_inscription'
        );
        
        // Allow customization via filter
        $mapping = apply_filters( 'ufsc_licences_columns_map', $defaults );
        
    } else {
        $mapping = array();
    }
    
    return $mapping;
}

/**
 * Get mapped column name for clubs table
 * 
 * @param string $key Logical key name
 * @return string|false Actual database column name or false if not found
 */
function ufsc_club_col( $key ) {
    $mapping = ufsc_sql_columns_map( 'clubs' );
    return isset( $mapping[ $key ] ) ? $mapping[ $key ] : false;
}

/**
 * Get mapped column name for licences table
 * 
 * @param string $key Logical key name  
 * @return string|false Actual database column name or false if not found
 */
function ufsc_lic_col( $key ) {
    $mapping = ufsc_sql_columns_map( 'licences' );
    return isset( $mapping[ $key ] ) ? $mapping[ $key ] : false;
}

/**
 * Check if a column exists in a table and return the mapped name
 * 
 * @param string $table_name Database table name
 * @param string $logical_key Logical column key
 * @param string $table_type 'clubs' or 'licences'
 * @return string|false Actual column name if exists, false otherwise
 */
function ufsc_get_mapped_column_if_exists( $table_name, $logical_key, $table_type ) {
    global $wpdb;
    
    // Get the mapped column name
    $mapped_column = $table_type === 'clubs' ? ufsc_club_col( $logical_key ) : ufsc_lic_col( $logical_key );
    
    if ( ! $mapped_column ) {
        return false;
    }
    
    // Check if column exists in the table
    $columns = $wpdb->get_col( "DESCRIBE `{$table_name}`" );
    
    return in_array( $mapped_column, $columns, true ) ? $mapped_column : false;
}