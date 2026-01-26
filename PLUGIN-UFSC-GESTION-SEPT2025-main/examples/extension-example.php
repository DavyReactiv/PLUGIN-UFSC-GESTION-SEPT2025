<?php
/**
 * Exemple d'extension du plugin UFSC
 * 
 * Ce fichier montre comment étendre les fonctionnalités du plugin
 * en utilisant les hooks fournis.
 * 
 * À placer dans le thème actif ou un plugin séparé.
 */

// Sécurité WordPress
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Ajouter des champs personnalisés aux clubs
 */
add_filter( 'ufsc_club_fields', 'mon_site_custom_club_fields' );
function mon_site_custom_club_fields( $fields ) {
    
    // Ajouter un champ pour le site web
    $fields['site_web'] = array( 'Site Web', 'text' );
    
    // Ajouter un champ pour les réseaux sociaux
    $fields['instagram'] = array( 'Instagram', 'text' );
    $fields['twitter'] = array( 'Twitter', 'text' );
    
    // Ajouter un champ pour la description
    $fields['description'] = array( 'Description', 'textarea' );
    
    return $fields;
}

/**
 * Shortcode personnalisé pour afficher les clubs d'une région
 */
add_shortcode( 'ufsc_clubs_region', 'mon_site_clubs_par_region' );
function mon_site_clubs_par_region( $atts ) {
    
    $atts = shortcode_atts( array(
        'region' => 'UFSC ILE-DE-FRANCE',
        'limite' => 10
    ), $atts );
    
    global $wpdb;
    $settings = UFSC_SQL::get_settings();
    $table = $settings['table_clubs'];
    
    $clubs = $wpdb->get_results( $wpdb->prepare(
        "SELECT nom, ville, email FROM `$table` WHERE region = %s AND statut = 'valide' LIMIT %d",
        $atts['region'],
        (int) $atts['limite']
    ));
    
    if ( ! $clubs ) {
        return '<p>Aucun club trouvé dans cette région.</p>';
    }
    
    $output = '<div class="ufsc-clubs-region">';
    $output .= '<h3>Clubs - ' . esc_html( $atts['region'] ) . '</h3>';
    $output .= '<ul>';
    
    foreach ( $clubs as $club ) {
        $output .= '<li>';
        $output .= '<strong>' . esc_html( $club->nom ) . '</strong>';
        if ( $club->ville ) {
            $output .= ' - ' . esc_html( $club->ville );
        }
        if ( $club->email ) {
            $output .= ' (<a href="mailto:' . esc_attr( $club->email ) . '">' . esc_html( $club->email ) . '</a>)';
        }
        $output .= '</li>';
    }
    
    $output .= '</ul></div>';
    
    return $output;
}