<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**

 * Centralized column mapping system for UFSC Gestion
 * Provides ufsc_club_col() and ufsc_lic_col() accessor functions
 * with filter hooks for customization
 */
class UFSC_Column_Map {
    
    /**
     * Get default clubs column mapping
     * 
     * @return array Default column mappings for clubs table
     */
    public static function get_default_clubs_columns() {
        return array(
            'id' => 'id',
            'nom' => 'nom',
            'region' => 'region',
            'adresse' => 'adresse',
            'complement_adresse' => 'complement_adresse',
            'code_postal' => 'code_postal',
            'ville' => 'ville',
            'email' => 'email',
            'telephone' => 'telephone',
            'type' => 'type',
            'siren' => 'siren',
            'ape' => 'ape',
            'ccn' => 'ccn',
            'ancv' => 'ancv',
            'num_declaration' => 'num_declaration',
            'date_declaration' => 'date_declaration',
            'president_prenom' => 'president_prenom',
            'president_nom' => 'president_nom',
            'president_tel' => 'president_tel',
            'president_email' => 'president_email',
            'secretaire_prenom' => 'secretaire_prenom',
            'secretaire_nom' => 'secretaire_nom',
            'secretaire_tel' => 'secretaire_tel',
            'secretaire_email' => 'secretaire_email',
            'tresorier_prenom' => 'tresorier_prenom',
            'tresorier_nom' => 'tresorier_nom',
            'tresorier_tel' => 'tresorier_tel',
            'tresorier_email' => 'tresorier_email',
            'entraineur_prenom' => 'entraineur_prenom',
            'entraineur_nom' => 'entraineur_nom',
            'entraineur_tel' => 'entraineur_tel',
            'entraineur_email' => 'entraineur_email',
            'statuts' => 'statuts',
            'recepisse' => 'recepisse',
            'jo' => 'jo',
            'pv_ag' => 'pv_ag',
            'cer' => 'cer',
            'attestation_cer' => 'attestation_cer',
            'doc_attestation_affiliation' => 'doc_attestation_affiliation',
            'num_affiliation' => 'num_affiliation',
            'quota_licences' => 'quota_licences',
            'statut' => 'statut',
            'date_creation' => 'date_creation',
            'responsable_id' => 'responsable_id',
            'doc_statuts' => 'doc_statuts',
            'doc_recepisse' => 'doc_recepisse',
            'doc_jo' => 'doc_jo',
            'doc_pv_ag' => 'doc_pv_ag',
            'doc_cer' => 'doc_cer',
            'doc_attestation_cer' => 'doc_attestation_cer',
            'precision_distribution' => 'precision_distribution',
            'url_site' => 'url_site',
            'url_facebook' => 'url_facebook',
            'date_affiliation' => 'date_affiliation',
            'contact' => 'contact',
            'url_instagram' => 'url_instagram',
            'rna_number' => 'rna_number'
        );
    }
    
    /**
     * Get default licences column mapping
     * 
     * @return array Default column mappings for licences table
     */
    public static function get_default_licences_columns() {
        return array(
            'id' => 'id',
            'club_id' => 'club_id',
            'nom' => 'nom',
            'prenom' => 'prenom',
            'sexe' => 'sexe',
            'date_naissance' => 'date_naissance',
            'email' => 'email',
            'adresse' => 'adresse',
            'suite_adresse' => 'suite_adresse',
            'code_postal' => 'code_postal',
            'ville' => 'ville',
            'tel_fixe' => 'tel_fixe',
            'tel_mobile' => 'tel_mobile',
            'reduction_benevole' => 'reduction_benevole',
            'reduction_postier' => 'reduction_postier',
            'identifiant_laposte' => 'identifiant_laposte',
            'profession' => 'profession',
            'fonction_publique' => 'fonction_publique',
            'competition' => 'competition',
            'licence_delegataire' => 'licence_delegataire',
            'numero_licence_delegataire' => 'numero_licence_delegataire',
            'diffusion_image' => 'diffusion_image',
            'infos_fsasptt' => 'infos_fsasptt',
            'infos_asptt' => 'infos_asptt',
            'infos_cr' => 'infos_cr',
            'infos_partenaires' => 'infos_partenaires',
            'honorabilite' => 'honorabilite',
            'assurance_dommage_corporel' => 'assurance_dommage_corporel',
            'assurance_assistance' => 'assurance_assistance',
            'note' => 'note',
            'region' => 'region',
            'statut' => 'statut',
            'is_included' => 'is_included',
            'date_inscription' => 'date_inscription',
            'responsable_id' => 'responsable_id',
            'certificat_date' => 'certificat_date',
            'certificat_url' => 'certificat_url'
        );
    }
    
    /**
     * Get clubs column mapping with filter hook
     * 
     * @return array Filtered column mappings for clubs table
     */
    public static function get_clubs_columns() {
        return apply_filters( 'ufsc_clubs_columns_map', self::get_default_clubs_columns() );
    }
    
    /**
     * Get licences column mapping with filter hook
     * 
     * @return array Filtered column mappings for licences table
     */
    public static function get_licences_columns() {
        return apply_filters( 'ufsc_licences_columns_map', self::get_default_licences_columns() );
    }

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

 * @param string $key The field key
 * @return string The actual column name
 */
function ufsc_club_col( $key ) {
    $columns = UFSC_Column_Map::get_clubs_columns();
    return isset( $columns[ $key ] ) ? $columns[ $key ] : $key;

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

 * @param string $key The field key  
 * @return string The actual column name
 */
function ufsc_lic_col( $key ) {
    $columns = UFSC_Column_Map::get_licences_columns();
    return isset( $columns[ $key ] ) ? $columns[ $key ] : $key;

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