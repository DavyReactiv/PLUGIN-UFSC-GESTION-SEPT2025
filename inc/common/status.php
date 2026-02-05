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

/**
 * UFSC PATCH: Check if a club is validated/active.
 *
 * @param int        $club_id Club ID.
 * @param object|nil $club    Optional club record.
 * @return bool
 */
function ufsc_is_club_validated( $club_id, $club = null ) {
    $status = '';

    if ( $club ) {
        if ( isset( $club->statut ) ) {
            $status = (string) $club->statut;
        } elseif ( isset( $club->status ) ) {
            $status = (string) $club->status;
        } elseif ( isset( $club->validated ) ) {
            $status = (string) $club->validated;
        }
    }

    if ( '' === $status ) {
        if ( ! function_exists( 'ufsc_get_clubs_table' ) ) {
            return false;
        }

        global $wpdb;
        $clubs_table = ufsc_get_clubs_table();
        $columns     = function_exists( 'ufsc_table_columns' ) ? ufsc_table_columns( $clubs_table ) : $wpdb->get_col( "DESCRIBE `{$clubs_table}`" );

        $status_column = null;
        foreach ( array( 'status', 'statut', 'validated', 'validation' ) as $col ) {
            if ( in_array( $col, $columns, true ) ) {
                $status_column = $col;
                break;
            }
        }

        if ( ! $status_column ) {
            return false;
        }

        $status = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `{$status_column}` FROM `{$clubs_table}` WHERE id = %d LIMIT 1",
                $club_id
            )
        );
    }

    $valid_statuses = array( 'actif', 'active', 'valide', 'validé', 'validée', 'approved', 'validate', 'validated' );
    return $status && in_array( strtolower( $status ), $valid_statuses, true );
}
