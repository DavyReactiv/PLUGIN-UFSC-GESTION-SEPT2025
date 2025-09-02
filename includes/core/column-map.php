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
}