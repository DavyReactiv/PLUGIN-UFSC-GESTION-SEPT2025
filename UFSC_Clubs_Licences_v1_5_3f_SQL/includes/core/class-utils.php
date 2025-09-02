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
     * 
     * @param array $data Club data to validate
     * @param bool $is_affiliation Whether this is for affiliation (stricter validation)
     * @return array Array of validation errors
     */
    public static function validate_club_data( $data, $is_affiliation = false ) {
        $errors = array();
        
        // Required fields
        $required_fields = array('nom', 'region', 'adresse', 'code_postal', 'ville', 'email', 'telephone', 'num_declaration', 'date_declaration');
        
        foreach ( $required_fields as $field ) {
            if ( empty( $data[$field] ) ) {
                $field_labels = array(
                    'nom' => __('Le nom du club', 'ufsc-clubs'),
                    'region' => __('La région', 'ufsc-clubs'),
                    'adresse' => __('L\'adresse', 'ufsc-clubs'),
                    'code_postal' => __('Le code postal', 'ufsc-clubs'),
                    'ville' => __('La ville', 'ufsc-clubs'),
                    'email' => __('L\'email', 'ufsc-clubs'),
                    'telephone' => __('Le téléphone', 'ufsc-clubs'),
                    'num_declaration' => __('Le numéro de déclaration', 'ufsc-clubs'),
                    'date_declaration' => __('La date de déclaration', 'ufsc-clubs')
                );
                $errors[$field] = sprintf( __('%s est requis', 'ufsc-clubs'), $field_labels[$field] ?? $field );
            }
        }
        
        // Validation dirigeants (required)
        $dirigeants = array('president', 'secretaire', 'tresorier');
        foreach ( $dirigeants as $dirigeant ) {
            $required_dirigeant_fields = array('prenom', 'nom', 'email', 'tel');
            foreach ( $required_dirigeant_fields as $field ) {
                $key = $dirigeant . '_' . $field;
                if ( empty( $data[$key] ) ) {
                    $errors[$key] = sprintf( __('%s du %s est requis', 'ufsc-clubs'), ucfirst($field), $dirigeant );
                }
            }
            
            // Validate dirigeant email
            $email_key = $dirigeant . '_email';
            if ( !empty( $data[$email_key] ) && !is_email( $data[$email_key] ) ) {
                $errors[$email_key] = sprintf( __('Format d\'email invalide pour %s', 'ufsc-clubs'), $dirigeant );
            }
        }
        
        // Validation de l'email principal (format)
        if ( !empty($data['email']) && !is_email($data['email']) ) {
            $errors['email'] = __('Format d\'email invalide', 'ufsc-clubs');
        }
        
        // Validation de la région
        if ( !empty($data['region']) && !in_array($data['region'], self::regions()) ) {
            $errors['region'] = __('Région non valide', 'ufsc-clubs');
        }
        
        // Validation du code postal (pattern)
        if ( !empty($data['code_postal']) && !preg_match('/^\d{5}$/', $data['code_postal']) ) {
            $errors['code_postal'] = __('Le code postal doit contenir 5 chiffres', 'ufsc-clubs');
        }
        
        // Validation de la date de déclaration
        if ( !empty($data['date_declaration']) && !self::validate_date($data['date_declaration']) ) {
            $errors['date_declaration'] = __('Format de date invalide (AAAA-MM-JJ)', 'ufsc-clubs');
        }
        
        // Basic IBAN validation (optional)
        if ( !empty($data['iban']) && !self::validate_iban($data['iban']) ) {
            $errors['iban'] = __('Format IBAN invalide', 'ufsc-clubs');
        }
        
        // Validation du quota de licences (nombre positif)
        if ( !empty($data['quota_licences']) && (!is_numeric($data['quota_licences']) || $data['quota_licences'] < 0) ) {
            $errors['quota_licences'] = __('Le quota doit être un nombre positif', 'ufsc-clubs');
        }
        
        // Additional validation for affiliation mode
        if ( $is_affiliation ) {
            $required_docs = array('doc_statuts', 'doc_recepisse', 'doc_cer');
            foreach ( $required_docs as $doc ) {
                if ( empty( $data[$doc] ) ) {
                    $doc_labels = array(
                        'doc_statuts' => __('Les statuts', 'ufsc-clubs'),
                        'doc_recepisse' => __('Le récépissé', 'ufsc-clubs'),
                        'doc_cer' => __('Le CER', 'ufsc-clubs')
                    );
                    $errors[$doc] = sprintf( __('%s sont requis pour l\'affiliation', 'ufsc-clubs'), $doc_labels[$doc] ?? $doc );
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Basic IBAN validation
     * 
     * @param string $iban IBAN to validate
     * @return bool True if valid format
     */
    public static function validate_iban( $iban ) {
        // Remove spaces and convert to uppercase
        $iban = strtoupper( preg_replace('/\s+/', '', $iban) );
        
        // Basic format check (starts with 2 letters, followed by 2 digits, then alphanumeric)
        return preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban) && strlen($iban) >= 15 && strlen($iban) <= 34;
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
