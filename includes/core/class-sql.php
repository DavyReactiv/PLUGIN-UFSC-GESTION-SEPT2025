<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_SQL {
    public static function default_settings(){
        return array(
            'table_clubs'     => 'clubs',
            'table_licences'  => 'licences',
            'pk_club'         => 'id',
            'pk_licence'      => 'id',
            'status_values'   => array('en_attente'=>'En attente','valide'=>'Validée','a_regler'=>'À régler','desactive'=>'Désactivée'),
            'club_fields' => array(
                'nom'=>array('Nom du club','text'),
                'region'=>array('Région','region'),
                'adresse'=>array('Adresse','text'),
                'complement_adresse'=>array('Complément d\'adresse','text'),
                'code_postal'=>array('Code postal','text'),
                'ville'=>array('Ville','text'),
                'email'=>array('Email','text'),
                'telephone'=>array('Téléphone','text'),
                'type'=>array('Type','text'),
                'siren'=>array('SIREN','text'),
                'ape'=>array('APE','text'),
                'ccn'=>array('CCN','text'),
                'ancv'=>array('ANCV','text'),
                'num_declaration'=>array('N° déclaration','text'),
                'date_declaration'=>array('Date déclaration','date'),
                'president_prenom'=>array('Président – Prénom','text'),
                'president_nom'=>array('Président – Nom','text'),
                'president_tel'=>array('Président – Téléphone','text'),
                'president_email'=>array('Président – Email','text'),
                'secretaire_prenom'=>array('Secrétaire – Prénom','text'),
                'secretaire_nom'=>array('Secrétaire – Nom','text'),
                'secretaire_tel'=>array('Secrétaire – Téléphone','text'),
                'secretaire_email'=>array('Secrétaire – Email','text'),
                'tresorier_prenom'=>array('Trésorier – Prénom','text'),
                'tresorier_nom'=>array('Trésorier – Nom','text'),
                'tresorier_tel'=>array('Trésorier – Téléphone','text'),
                'tresorier_email'=>array('Trésorier – Email','text'),
                'entraineur_prenom'=>array('Entraîneur – Prénom','text'),
                'entraineur_nom'=>array('Entraîneur – Nom','text'),
                'entraineur_tel'=>array('Entraîneur – Téléphone','text'),
                'entraineur_email'=>array('Entraîneur – Email','text'),
                'statuts'=>array('Statuts','text'),
                'recepisse'=>array('Récépissé','text'),
                'jo'=>array('Journal Officiel','text'),
                'pv_ag'=>array('PV AG','text'),
                'cer'=>array('CER','text'),
                'attestation_cer'=>array('Attestation CER','text'),
                'doc_attestation_affiliation'=>array('Attestation UFSC','text'),
                'num_affiliation'=>array('N° Affiliation','text'),
                'quota_licences'=>array('Quota licences','number'),
                'statut'=>array('Statut','licence_status'),
                'date_creation'=>array('Date création','date'),
                'responsable_id'=>array('User ID responsable','number'),
                'precision_distribution'=>array('Précision distribution','text'),
                'url_site'=>array('Site','text'),
                'url_facebook'=>array('Facebook','text'),
                'date_affiliation'=>array('Date affiliation','date'),
                'contact'=>array('Contact','text'),
                'url_instagram'=>array('Instagram','text'),
                'rna_number'=>array('RNA','text'),
                'iban'=>array('IBAN','text'),
                'logo_url'=>array('Logo URL','text'),
                'doc_statuts'=>array('Document Statuts','text'),
                'doc_recepisse'=>array('Document Récépissé','text'),
                'doc_jo'=>array('Document JO','text'),
                'doc_pv_ag'=>array('Document PV AG','text'),
                'doc_cer'=>array('Document CER','text'),
                'doc_attestation_cer'=>array('Document Attestation CER','text'),
            ),
            'licence_fields' => array(
                'club_id'=>array('Club','number'),
                'nom'=>array('Nom','text'),
                'prenom'=>array('Prénom','text'),
                'sexe'=>array('Sexe','sex'),
                'date_naissance'=>array('Date de naissance','date'),
                'email'=>array('Email','text'),
                'adresse'=>array('Adresse','text'),
                'suite_adresse'=>array('Complément d\'adresse','text'),
                'code_postal'=>array('Code postal','text'),
                'ville'=>array('Ville','text'),
                'tel_fixe'=>array('Téléphone fixe','text'),
                'tel_mobile'=>array('Téléphone mobile','text'),
                'reduction_benevole'=>array('Réduction bénévole','bool'),
                'reduction_postier'=>array('Réduction postier','bool'),
                'identifiant_laposte'=>array('Identifiant La Poste','text'),
                'profession'=>array('Profession','text'),
                'fonction_publique'=>array('Fonction publique','bool'),
                'competition'=>array('Compétition','bool'),
                'licence_delegataire'=>array('Licence délégataire','bool'),
                'numero_licence_delegataire'=>array('N° licence délégataire','text'),
                'diffusion_image'=>array('Autoriser diffusion image','bool'),
                'infos_fsasptt'=>array('Infos FSASPTT','bool'),
                'infos_asptt'=>array('Infos ASPTT','bool'),
                'infos_cr'=>array('Infos CR','bool'),
                'infos_partenaires'=>array('Infos partenaires','bool'),
                'honorabilite'=>array('Honorabilité','bool'),
                'assurance_dommage_corporel'=>array('Assurance dommage corporel','bool'),
                'assurance_assistance'=>array('Assurance assistance','bool'),
                'note'=>array('Note','textarea'),
                'region'=>array('Région','region'),
                'statut'=>array('Statut','licence_status'),
                'is_included'=>array('Incluse dans quota','bool'),
                'date_inscription'=>array('Date inscription','date'),
                'responsable_id'=>array('User ID responsable','number'),
                'certificat_date'=>array('Date certificat médical','date'),
                'certificat_url'=>array('URL certificat médical','text')
            )
        );
    }
    public static function get_settings(){
        $opts = get_option( 'ufsc_sql_settings', array() );
        $settings = wp_parse_args( $opts, self::default_settings() );
        return apply_filters( 'ufsc_sql_settings', $settings );
    }
    
    public static function statuses(){ 
        $s = self::get_settings(); 
        return apply_filters( 'ufsc_status_values', $s['status_values'] );
    }
    
    /**
     * Hook pour personnaliser les champs de club
     */
    public static function get_club_fields() {
        $s = self::get_settings();
        return apply_filters( 'ufsc_club_fields', $s['club_fields'] );
    }
    
    /**
     * Hook pour personnaliser les champs de licence
     */
    public static function get_licence_fields() {
        $s = self::get_settings();
        return apply_filters( 'ufsc_licence_fields', $s['licence_fields'] );
    }
}
