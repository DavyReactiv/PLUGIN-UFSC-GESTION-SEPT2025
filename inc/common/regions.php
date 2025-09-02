<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Common regions module for UFSC Gestion
 * Provides unified region list and validation helpers
 */

/**
 * Get the exact list of 14 UFSC regions
 * 
 * @return array List of region labels
 */
function ufsc_get_regions_labels() {
    return array(
        'Auvergne-Rhône-Alpes UFSC',
        'Bourgogne-Franche-Comté UFSC',
        'Bretagne UFSC',
        'Centre-Val de Loire UFSC',
        'Corse UFSC',
        'Grand Est UFSC',
        'Hauts-de-France UFSC',
        'Île-de-France UFSC',
        'Normandie UFSC',
        'Nouvelle-Aquitaine UFSC',
        'Occitanie UFSC',
        'Pays de la Loire UFSC',
        'Provence-Alpes-Côte d\'Azur UFSC',
        'DROM-COM UFSC'
    );
}

/**
 * Validate if a region is in the approved list
 * 
 * @param string $region Region to validate
 * @return bool True if valid region
 */
function ufsc_is_valid_region( $region ) {
    if ( empty( $region ) ) {
        return false;
    }
    
    $valid_regions = ufsc_get_regions_labels();
    return in_array( $region, $valid_regions, true );
}

/**
 * Generate HTML select options for regions
 * 
 * @param string $selected Currently selected region
 * @return string HTML options markup
 */
function ufsc_region_select_options( $selected = '' ) {
    $options = '';
    $regions = ufsc_get_regions_labels();
    
    foreach ( $regions as $region ) {
        $options .= sprintf(
            '<option value="%s"%s>%s</option>',
            esc_attr( $region ),
            selected( $selected, $region, false ),
            esc_html( $region )
        );
    }
    
    return $options;
}