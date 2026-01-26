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
function ufsc_get_status_labels() {
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
function ufsc_get_status_list() {
    return ufsc_get_status_labels();
}

/**
 * Validate if a region is in the approved list
 * 
 * @param string $region Region to validate
 * @return bool True if valid region
 */
function ufsc_is_valid_status( $status ) {
    if ( empty( $status ) ) {
        return false;
    }
    
    $valid_statuts = array_keys(ufsc_get_status_labels());
    return in_array( $status, $valid_statuts, true );
}

function ufsc_status_select_options( $selected = '' ) {
    $options = '';
    $statuts = ufsc_get_status_labels();
    
    foreach ( $statuts as $key => $statut ) {
        $options .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $key ),
            selected( $selected, $key, false ),
            esc_html( $statut )
        );
    }
    
    return $options;
}
