<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Utils {
    public static function esc_badge( $label, $type='info' ){
        $type = sanitize_html_class($type);
        return '<span class="ufsc-badge ufsc-badge-'.$type.'">'.esc_html($label).'</span>';
    }
    
    public static function sanitize_text_arr( $arr ){
        $out = array();
        foreach( (array) $arr as $k=>$v ){ $out[$k] = is_array($v) ? self::sanitize_text_arr($v) : sanitize_text_field($v); }
        return $out;
    }
    
    public static function kpi_cards( $cards ){
        $html = '<div class="ufsc-cards">';
        foreach( $cards as $c ){
            $html .= '<div class="ufsc-card"><div class="ufsc-card-kpi">'.esc_html($c['value']).'</div><div class="ufsc-card-label">'.esc_html($c['label']).'</div></div>';
        }
        $html .= '</div>';
        return $html;
    }
    
    public static function regions(){
        $default = array(
            'UFSC AUVERGNE-RHONE-ALPES',
            'UFSC BOURGOGNE-FRANCHE-COMTE',
            'UFSC BRETAGNE',
            'UFSC CENTRE-VAL DE LOIRE',
            'UFSC GRAND EST',
            'UFSC HAUTS-DE-FRANCE',
            'UFSC ILE-DE-FRANCE',
            'UFSC NORMANDIE',
            'UFSC NOUVELLE-AQUITAINE',
            'UFSC OCCITANIE',
            'UFSC PAYS DE LA LOIRE',
            'UFSC PROVENCE-ALPES-COTE D\'AZUR',
            'UFSC DROM-COM'
        );
        return apply_filters( 'ufsc_regions_list', $default );
    }
    
    /**
     * Validation des données de club
     */
    public static function validate_club_data( $data ) {
        $errors = array();
        
        // Validation du nom (requis)
        if ( empty($data['nom']) ) {
            $errors['nom'] = __('Le nom du club est requis', 'ufsc-clubs');
        }
        
        // Validation de l'email (format)
        if ( !empty($data['email']) && !is_email($data['email']) ) {
            $errors['email'] = __('Format d\'email invalide', 'ufsc-clubs');
        }
        
        // Validation de la région
        if ( !empty($data['region']) && !in_array($data['region'], self::regions()) ) {
            $errors['region'] = __('Région non valide', 'ufsc-clubs');
        }
        
        // Validation du quota de licences (nombre positif)
        if ( !empty($data['quota_licences']) && (!is_numeric($data['quota_licences']) || $data['quota_licences'] < 0) ) {
            $errors['quota_licences'] = __('Le quota doit être un nombre positif', 'ufsc-clubs');
        }
        
        return $errors;
    }
    
    /**
     * Validation des données de licence
     */
    public static function validate_licence_data( $data ) {
        $errors = array();
        
        // Validation nom/prénom (requis)
        if ( empty($data['nom']) ) {
            $errors['nom'] = __('Le nom est requis', 'ufsc-clubs');
        }
        if ( empty($data['prenom']) ) {
            $errors['prenom'] = __('Le prénom est requis', 'ufsc-clubs');
        }
        
        // Validation de l'email
        if ( !empty($data['email']) && !is_email($data['email']) ) {
            $errors['email'] = __('Format d\'email invalide', 'ufsc-clubs');
        }
        
        // Validation de la date de naissance
        if ( !empty($data['date_naissance']) && !self::validate_date($data['date_naissance']) ) {
            $errors['date_naissance'] = __('Format de date invalide', 'ufsc-clubs');
        }
        
        // Validation du sexe
        if ( !empty($data['sexe']) && !in_array($data['sexe'], array('M', 'F')) ) {
            $errors['sexe'] = __('Sexe non valide', 'ufsc-clubs');
        }
        
        return $errors;
    }
    
    /**
     * Validation d'une date au format Y-m-d
     */
    public static function validate_date( $date, $format = 'Y-m-d' ) {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Affichage d'une erreur formatée
     */
    public static function show_error( $message ) {
        return '<div class="ufsc-alert error"><strong>'.esc_html__('Erreur', 'ufsc-clubs').':</strong> '.esc_html($message).'</div>';
    }
    
    /**
     * Affichage d'un succès formaté
     */
    public static function show_success( $message ) {
        return '<div class="ufsc-alert success"><strong>'.esc_html__('Succès', 'ufsc-clubs').':</strong> '.esc_html($message).'</div>';
    }
    
    /**
     * Log sécurisé pour debug
     */
    public static function log( $message, $level = 'info' ) {
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('[UFSC Plugin] [' . strtoupper($level) . '] ' . $message);
        }
    }
}
