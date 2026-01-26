<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Common status module for UFSC Gestion
 * Provides unified status list and validation helpers
 */

/**
 * Get the exact list of 14 UFSC regions
 * 
 * @return array List of region labels
 */
function ufsc_get_statuts_labels() {
    return array(
        'en_attente'=> 'En attente',
        'actif'=> 'Actif',
        'en_cours_de_creation'=> 'En cours de création',
        
    );
}

/**
 * Alias for ufsc_get_status_labels for consistency
 * 
 * @return array List of clubs status labels
 */
function ufsc_get_statuts_list() {
    return ufsc_get_statuts_labels();
}

/**
 * Validate if a region is in the approved list
 * 
 * @param string $region Region to validate
 * @return bool True if valid region
 */
function ufsc_is_valid_statut( $statut ) {
    if ( empty( $statut ) ) {
        return false;
    }
    
    $valid_statuts = array_keys(ufsc_get_statuts_labels());
    return in_array( $statut, $valid_statuts, true );
}

/**
 * Generate HTML select options for regions
 * 
 * @param string $selected Currently selected region
 * @return string HTML options markup
 */
function ufsc_statuts_select_options( $selected = '' ) {
    $options = '';
    $statuts = ufsc_get_statuts_labels();
    
    foreach ( $statuts as $key => $statut ) {
        $options .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $selected, $statut, false ),
            esc_html( $statut )
        );
    }
    
    return $options;
}