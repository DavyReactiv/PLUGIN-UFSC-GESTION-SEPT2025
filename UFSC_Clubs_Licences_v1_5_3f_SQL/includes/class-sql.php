<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_SQL {
    
    public static function get_status_values() {
        return array(
            'en_attente' => __('En attente','ufsc-clubs'),
            'a_regler'   => __('À régler','ufsc-clubs'),
            'valide'     => __('Validé','ufsc-clubs'),
            'desactive'  => __('Désactivé','ufsc-clubs'),
        );
    }

    public static function get_settings(){
        global $wpdb;
        $defaults = array(
            'table_clubs'    => $wpdb->prefix.'ufsc_clubs',
            'table_licences' => $wpdb->prefix.'ufsc_licences',
            'pk_club'        => 'id',
            'pk_licence'     => 'id',
            'club_fields' => array(
                'nom'             => array( __('Nom du club','ufsc-clubs'), 'text' ),
                'region'          => array( __('Région','ufsc-clubs'), 'region' ),
                'adresse'         => array( __('Adresse','ufsc-clubs'), 'text' ),
                'complement_adresse' => array( __('Complément d\'adresse','ufsc-clubs'), 'text' ),
                'code_postal'     => array( __('Code postal','ufsc-clubs'), 'text' ),
                'ville'           => array( __('Ville','ufsc-clubs'), 'text' ),
                'email'           => array( __('Email','ufsc-clubs'), 'text' ),
                'telephone'       => array( __('Téléphone','ufsc-clubs'), 'text' ),
                'type'            => array( __('Type','ufsc-clubs'), 'text' ),
                'siren'           => array( __('SIREN','ufsc-clubs'), 'text' ),
                'ape'             => array( __('APE','ufsc-clubs'), 'text' ),
                'ccn'             => array( __('CCN','ufsc-clubs'), 'text' ),
                'ancv'            => array( __('ANCV','ufsc-clubs'), 'text' ),
                'num_declaration' => array( __('N° déclaration','ufsc-clubs'), 'text' ),
                'date_declaration' => array( __('Date déclaration','ufsc-clubs'), 'date' ),
                'president_prenom' => array( __('Président – Prénom','ufsc-clubs'), 'text' ),
                'president_nom'   => array( __('Président – Nom','ufsc-clubs'), 'text' ),
                'president_tel'   => array( __('Président – Téléphone','ufsc-clubs'), 'text' ),
                'president_email' => array( __('Président – Email','ufsc-clubs'), 'text' ),
                'secretaire_prenom' => array( __('Secrétaire – Prénom','ufsc-clubs'), 'text' ),
                'secretaire_nom'  => array( __('Secrétaire – Nom','ufsc-clubs'), 'text' ),
                'secretaire_tel'  => array( __('Secrétaire – Téléphone','ufsc-clubs'), 'text' ),
                'secretaire_email' => array( __('Secrétaire – Email','ufsc-clubs'), 'text' ),
                'tresorier_prenom' => array( __('Trésorier – Prénom','ufsc-clubs'), 'text' ),
                'tresorier_nom'   => array( __('Trésorier – Nom','ufsc-clubs'), 'text' ),
                'tresorier_tel'   => array( __('Trésorier – Téléphone','ufsc-clubs'), 'text' ),
                'tresorier_email' => array( __('Trésorier – Email','ufsc-clubs'), 'text' ),
                'entraineur_prenom' => array( __('Entraîneur – Prénom','ufsc-clubs'), 'text' ),
                'entraineur_nom'  => array( __('Entraîneur – Nom','ufsc-clubs'), 'text' ),
                'entraineur_tel'  => array( __('Entraîneur – Téléphone','ufsc-clubs'), 'text' ),
                'entraineur_email' => array( __('Entraîneur – Email','ufsc-clubs'), 'text' ),
                'num_affiliation' => array( __('N° Affiliation','ufsc-clubs'), 'text' ),
                'quota_licences'  => array( __('Quota licences','ufsc-clubs'), 'number' ),
                'statut'          => array( __('Statut','ufsc-clubs'), 'licence_status' ),
                'date_creation'   => array( __('Date création','ufsc-clubs'), 'date' ),
                'responsable_id'  => array( __('User ID responsable','ufsc-clubs'), 'number' ),
                'precision_distribution' => array( __('Précision distribution','ufsc-clubs'), 'text' ),
                'url_site'        => array( __('Site','ufsc-clubs'), 'text' ),
                'url_facebook'    => array( __('Facebook','ufsc-clubs'), 'text' ),
                'date_affiliation' => array( __('Date affiliation','ufsc-clubs'), 'date' ),
                'contact'         => array( __('Contact','ufsc-clubs'), 'text' ),
                'url_instagram'   => array( __('Instagram','ufsc-clubs'), 'text' ),
                'rna_number'      => array( __('RNA','ufsc-clubs'), 'text' ),
                'iban'            => array( __('IBAN','ufsc-clubs'), 'text' ),
                'logo_url'        => array( __('Logo URL','ufsc-clubs'), 'text' ),
            ),
            'licence_fields' => array(
                'club_id'         => array( __('Club','ufsc-clubs'), 'number' ),
                'nom'             => array( __('Nom','ufsc-clubs'), 'text' ),
                'prenom'          => array( __('Prénom','ufsc-clubs'), 'text' ),
                'sexe'            => array( __('Sexe','ufsc-clubs'), 'sex' ),
                'date_naissance'  => array( __('Date de naissance','ufsc-clubs'), 'date' ),
                'email'           => array( __('Email','ufsc-clubs'), 'text' ),
                'adresse'         => array( __('Adresse','ufsc-clubs'), 'text' ),
                'suite_adresse'   => array( __('Complément d\'adresse','ufsc-clubs'), 'text' ),
                'code_postal'     => array( __('Code postal','ufsc-clubs'), 'text' ),
                'ville'           => array( __('Ville','ufsc-clubs'), 'text' ),
                'tel_fixe'        => array( __('Téléphone fixe','ufsc-clubs'), 'text' ),
                'tel_mobile'      => array( __('Téléphone mobile','ufsc-clubs'), 'text' ),
                'reduction_benevole' => array( __('Réduction bénévole','ufsc-clubs'), 'bool' ),
                'reduction_postier' => array( __('Réduction postier','ufsc-clubs'), 'bool' ),
                'identifiant_laposte' => array( __('Identifiant La Poste','ufsc-clubs'), 'text' ),
                'profession'      => array( __('Profession','ufsc-clubs'), 'text' ),
                'fonction_publique' => array( __('Fonction publique','ufsc-clubs'), 'bool' ),
                'competition'     => array( __('Compétition','ufsc-clubs'), 'bool' ),
                'licence_delegataire' => array( __('Licence délégataire','ufsc-clubs'), 'bool' ),
                'numero_licence_delegataire' => array( __('N° licence délégataire','ufsc-clubs'), 'text' ),
                'diffusion_image' => array( __('Autoriser diffusion image','ufsc-clubs'), 'bool' ),
                'infos_fsasptt'   => array( __('Infos FSASPTT','ufsc-clubs'), 'bool' ),
                'infos_asptt'     => array( __('Infos ASPTT','ufsc-clubs'), 'bool' ),
                'infos_cr'        => array( __('Infos CR','ufsc-clubs'), 'bool' ),
                'infos_partenaires' => array( __('Infos partenaires','ufsc-clubs'), 'bool' ),
                'honorabilite'    => array( __('Honorabilité','ufsc-clubs'), 'bool' ),
                'assurance_dommage_corporel' => array( __('Assurance dommage corporel','ufsc-clubs'), 'bool' ),
                'assurance_assistance' => array( __('Assurance assistance','ufsc-clubs'), 'bool' ),
                'note'            => array( __('Note','ufsc-clubs'), 'textarea' ),
                'region'          => array( __('Région','ufsc-clubs'), 'region' ),
                'statut'          => array( __('Statut','ufsc-clubs'), 'licence_status' ),
                'is_included'     => array( __('Incluse dans quota','ufsc-clubs'), 'bool' ),
                'date_inscription' => array( __('Date inscription','ufsc-clubs'), 'date' ),
                'responsable_id'  => array( __('User ID responsable','ufsc-clubs'), 'number' ),
                'certificat_date' => array( __('Date certificat médical','ufsc-clubs'), 'date' ),
                'certificat_url'  => array( __('URL certificat médical','ufsc-clubs'), 'text' )
            )
        );
        
        $opts = get_option( 'ufsc_sql_settings', array() );
        return wp_parse_args( $opts, $defaults );
    }

    public static function statuses() { 
        return self::get_status_values(); 
    }

    /**
     * Installation/activation hook
     */
    public static function install() {
        self::create_tables();
        self::set_default_options();
        add_option('ufsc_sql_db_version', UFSC_CL_VERSION);
    }

    /**
     * Maybe upgrade database schema
     */
    public static function maybe_upgrade() {
        $current_version = get_option('ufsc_sql_db_version', '1.0.0');
        if (version_compare($current_version, UFSC_CL_VERSION, '<')) {
            self::create_tables();
            update_option('ufsc_sql_db_version', UFSC_CL_VERSION);
        }
    }

    /**
     * Create database tables with dbDelta
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $settings = self::get_settings();
        
        $clubs_table = $settings['table_clubs'];
        $licences_table = $settings['table_licences'];

        $sql_clubs = "CREATE TABLE $clubs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            nom varchar(255) NOT NULL,
            region varchar(255) DEFAULT NULL,
            adresse varchar(255) DEFAULT NULL,
            complement_adresse varchar(255) DEFAULT NULL,
            code_postal varchar(10) DEFAULT NULL,
            ville varchar(255) DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            telephone varchar(20) DEFAULT NULL,
            type varchar(100) DEFAULT NULL,
            siren varchar(20) DEFAULT NULL,
            ape varchar(20) DEFAULT NULL,
            ccn varchar(20) DEFAULT NULL,
            ancv varchar(20) DEFAULT NULL,
            num_declaration varchar(50) DEFAULT NULL,
            date_declaration date DEFAULT NULL,
            president_prenom varchar(100) DEFAULT NULL,
            president_nom varchar(100) DEFAULT NULL,
            president_tel varchar(20) DEFAULT NULL,
            president_email varchar(255) DEFAULT NULL,
            secretaire_prenom varchar(100) DEFAULT NULL,
            secretaire_nom varchar(100) DEFAULT NULL,
            secretaire_tel varchar(20) DEFAULT NULL,
            secretaire_email varchar(255) DEFAULT NULL,
            tresorier_prenom varchar(100) DEFAULT NULL,
            tresorier_nom varchar(100) DEFAULT NULL,
            tresorier_tel varchar(20) DEFAULT NULL,
            tresorier_email varchar(255) DEFAULT NULL,
            entraineur_prenom varchar(100) DEFAULT NULL,
            entraineur_nom varchar(100) DEFAULT NULL,
            entraineur_tel varchar(20) DEFAULT NULL,
            entraineur_email varchar(255) DEFAULT NULL,
            num_affiliation varchar(50) DEFAULT NULL,
            quota_licences int(11) DEFAULT 0,
            statut varchar(20) DEFAULT 'en_attente',
            date_creation datetime DEFAULT CURRENT_TIMESTAMP,
            responsable_id int(11) DEFAULT NULL,
            precision_distribution text DEFAULT NULL,
            url_site varchar(255) DEFAULT NULL,
            url_facebook varchar(255) DEFAULT NULL,
            date_affiliation date DEFAULT NULL,
            contact varchar(255) DEFAULT NULL,
            url_instagram varchar(255) DEFAULT NULL,
            rna_number varchar(20) DEFAULT NULL,
            iban varchar(50) DEFAULT NULL,
            logo_url varchar(255) DEFAULT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB $charset_collate;";

        $sql_licences = "CREATE TABLE $licences_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            club_id int(11) DEFAULT NULL,
            nom varchar(255) NOT NULL,
            prenom varchar(255) NOT NULL,
            sexe varchar(1) DEFAULT NULL,
            date_naissance date DEFAULT NULL,
            email varchar(255) DEFAULT NULL,
            adresse varchar(255) DEFAULT NULL,
            suite_adresse varchar(255) DEFAULT NULL,
            code_postal varchar(10) DEFAULT NULL,
            ville varchar(255) DEFAULT NULL,
            tel_fixe varchar(20) DEFAULT NULL,
            tel_mobile varchar(20) DEFAULT NULL,
            reduction_benevole tinyint(1) DEFAULT 0,
            reduction_postier tinyint(1) DEFAULT 0,
            identifiant_laposte varchar(50) DEFAULT NULL,
            profession varchar(255) DEFAULT NULL,
            fonction_publique tinyint(1) DEFAULT 0,
            competition tinyint(1) DEFAULT 0,
            licence_delegataire tinyint(1) DEFAULT 0,
            numero_licence_delegataire varchar(50) DEFAULT NULL,
            diffusion_image tinyint(1) DEFAULT 0,
            infos_fsasptt tinyint(1) DEFAULT 0,
            infos_asptt tinyint(1) DEFAULT 0,
            infos_cr tinyint(1) DEFAULT 0,
            infos_partenaires tinyint(1) DEFAULT 0,
            honorabilite tinyint(1) DEFAULT 0,
            assurance_dommage_corporel tinyint(1) DEFAULT 0,
            assurance_assistance tinyint(1) DEFAULT 0,
            note text DEFAULT NULL,
            region varchar(255) DEFAULT NULL,
            statut varchar(20) DEFAULT 'en_attente',
            is_included tinyint(1) DEFAULT 1,
            date_inscription datetime DEFAULT CURRENT_TIMESTAMP,
            responsable_id int(11) DEFAULT NULL,
            certificat_date date DEFAULT NULL,
            certificat_url varchar(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY club_id (club_id)
        ) ENGINE=InnoDB $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_clubs);
        dbDelta($sql_licences);
    }

    /**
     * Set default plugin options
     */
    public static function set_default_options() {
        $settings = self::get_settings();
        if (!get_option('ufsc_sql_settings')) {
            add_option('ufsc_sql_settings', $settings);
        }
    }

    /**
     * Get sanitized table name
     */
    public static function sanitize_table_name($table_name) {
        global $wpdb;
        // Remove any non-alphanumeric characters except underscores
        $clean_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        // Ensure it starts with the prefix
        if (strpos($clean_name, $wpdb->prefix) !== 0) {
            $clean_name = $wpdb->prefix . ltrim($clean_name, '_');
        }
        return $clean_name;
    }
}