<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Espace admin FFST.
 *
 * Réservé à l'administration UFSC : aucun document FFST n'est exposé dans
 * l'espace du représentant du club. Le module consolide progressivement les
 * données déjà présentes dans UFSC Gestion avant génération des modèles
 * officiels FFST.
 */
final class UFSC_FFST_Documents_Admin {

    public static function render() {
        if ( ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Vous n’avez pas l’autorisation d’accéder aux dossiers FFST.', 'ufsc-clubs' ) );
        }

        $season = class_exists( 'UFSC_Season_Service' )
            ? (string) UFSC_Season_Service::get_current_season()
            : ( function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '' );
        $club_id = isset( $_GET['club_id'] ) ? absint( $_GET['club_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtre lecture seule.
        $clubs = self::get_clubs();
        $club = $club_id ? self::get_club( $club_id ) : null;

        echo '<div class="wrap ufsc-ffst-admin">';
        if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); }
        echo '<h1>' . esc_html__( 'Dossiers FFST', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Espace interne UFSC : préparation des documents FFST à partir des données clubs et licences déjà enregistrées.', 'ufsc-clubs' ) . '</p>';

        echo '<form method="get" style="margin:20px 0;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:8px;">';
        echo '<input type="hidden" name="page" value="ufsc-ffst-documents">';
        echo '<label for="ufsc_ffst_club"><strong>' . esc_html__( 'Club à préparer', 'ufsc-clubs' ) . '</strong></label> ';
        echo '<select id="ufsc_ffst_club" name="club_id">';
        echo '<option value="">' . esc_html__( 'Sélectionner un club…', 'ufsc-clubs' ) . '</option>';
        foreach ( $clubs as $row ) {
            $id = absint( self::value( $row, array( 'id', 'club_id', 'ID' ) ) );
            $name = self::value( $row, array( 'nom', 'name', 'club_name' ) );
            echo '<option value="' . esc_attr( $id ) . '"' . selected( $club_id, $id, false ) . '>' . esc_html( $name ?: sprintf( __( 'Club #%d', 'ufsc-clubs' ), $id ) ) . '</option>';
        }
        echo '</select> <button class="button button-primary">' . esc_html__( 'Ouvrir le dossier', 'ufsc-clubs' ) . '</button>';
        echo '</form>';

        if ( ! $club ) {
            echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Sélectionnez un club pour afficher la complétude de son dossier FFST.', 'ufsc-clubs' ) . '</p></div>';
            echo '</div>';
            return;
        }

        $readiness = self::build_readiness( $club, $club_id, $season );
        self::render_summary( $club, $season, $readiness );
        self::render_affiliation_section( $readiness );
        self::render_licences_section( $readiness );
        self::render_documents_section( $readiness );
        echo '</div>';
    }

    private static function get_clubs() {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $name_col = in_array( 'nom', $columns, true ) ? 'nom' : ( in_array( 'name', $columns, true ) ? 'name' : '' );
        $order = $name_col ? " ORDER BY `{$name_col}` ASC" : '';
        return (array) $wpdb->get_results( "SELECT * FROM `{$table}`{$order}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table interne.
    }

    private static function get_club( $club_id ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_clubs_table() : ( function_exists( 'ufsc_get_clubs_table' ) ? ufsc_get_clubs_table() : $wpdb->prefix . 'ufsc_clubs' );
        $pk = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::first_existing_column( $table, array( 'id', 'club_id', 'ID' ) ) : 'id';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$pk}`=%d LIMIT 1", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function get_licences( $club_id, $season ) {
        global $wpdb;
        $table = class_exists( 'UFSC_Storage_Resolver' ) ? UFSC_Storage_Resolver::get_licences_table() : ( function_exists( 'ufsc_get_licences_table' ) ? ufsc_get_licences_table() : $wpdb->prefix . 'ufsc_licences' );
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : array();
        $club_col = in_array( 'club_id', $columns, true ) ? 'club_id' : ( in_array( 'id_club', $columns, true ) ? 'id_club' : 'club_id' );
        $season_col = in_array( 'season', $columns, true ) ? 'season' : ( in_array( 'saison', $columns, true ) ? 'saison' : '' );
        if ( $season_col ) {
            return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d AND `{$season_col}`=%s", $club_id, $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE `{$club_col}`=%d", $club_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    private static function build_readiness( $club, $club_id, $season ) {
        $licences = self::get_licences( $club_id, $season );
        $fields = array(
            'Nom du club' => self::value( $club, array( 'nom', 'name', 'club_name' ) ),
            'Adresse du siège' => self::value( $club, array( 'adresse', 'adresse_siege', 'adresse_complete', 'address' ) ),
            'Téléphone' => self::value( $club, array( 'telephone', 'tel', 'phone' ) ),
            'Mail club' => self::value( $club, array( 'email', 'mail', 'club_email' ) ),
            'Site Internet' => self::value( $club, array( 'site', 'site_web', 'website', 'url' ) ),
            'Déclaration préfecture' => self::value( $club, array( 'numero_recepisse', 'declaration_prefecture', 'numero_declaration', 'rna' ) ),
            'Adresse salle d’entraînement' => self::value( $club, array( 'adresse_salle', 'salle_adresse', 'training_address' ) ),
        );
        $filled = count( array_filter( $fields, static function( $value ) { return '' !== trim( (string) $value ); } ) );
        $roles = array(
            'Président' => self::find_role_licence( $licences, array( 'president', 'président' ) ),
            'Secrétaire' => self::find_role_licence( $licences, array( 'secretaire', 'secrétaire' ) ),
            'Trésorier' => self::find_role_licence( $licences, array( 'tresorier', 'trésorier' ) ),
            'Entraîneur / instructeur' => self::find_role_licence( $licences, array( 'entraineur', 'entraîneur', 'instructeur', 'coach' ) ),
        );
        $role_count = count( array_filter( $roles ) );
        $total = count( $licences );
        $percent = (int) round( ( ( $filled + $role_count + min( 10, $total ) ) / ( count( $fields ) + count( $roles ) + 10 ) ) * 100 );
        return array(
            'fields' => $fields,
            'roles' => $roles,
            'licences' => $licences,
            'licence_count' => $total,
            'minimum_licences_ok' => $total >= 10,
            'percent' => max( 0, min( 100, $percent ) ),
        );
    }

    private static function render_summary( $club, $season, $readiness ) {
        $name = self::value( $club, array( 'nom', 'name', 'club_name' ) );
        echo '<div class="ufsc-dashboard-cards" style="margin:20px 0;">';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Club', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $name ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Saison FFST', 'ufsc-clubs' ) . '</div><div class="card-value" style="font-size:20px;">' . esc_html( $season ) . '</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Dossier prêt', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['percent'] ) . '%</div></div>';
        echo '<div class="ufsc-dashboard-card"><div class="card-label">' . esc_html__( 'Licences saison', 'ufsc-clubs' ) . '</div><div class="card-value">' . esc_html( $readiness['licence_count'] ) . '/10</div></div>';
        echo '</div>';
    }

    private static function render_affiliation_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;margin-top:20px;"><h2 style="margin-top:0;">' . esc_html__( '1. Dossier affiliation / réaffiliation FFST', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Information officielle FFST', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Valeur UFSC', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'État', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( $readiness['fields'] as $label => $value ) {
            $ok = '' !== trim( (string) $value );
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $value ?: '—' ) . '</td><td>' . ( $ok ? '<span style="color:#008a20;font-weight:700;">✓ Complet</span>' : '<span style="color:#b32d2e;font-weight:700;">À compléter</span>' ) . '</td></tr>';
        }
        foreach ( $readiness['roles'] as $label => $licence ) {
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong></td><td>' . esc_html( $licence ? self::person_name( $licence ) : '—' ) . '</td><td>' . ( $licence ? '<span style="color:#008a20;font-weight:700;">✓ Identifié</span>' : '<span style="color:#b32d2e;font-weight:700;">Licence dirigeant manquante</span>' ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function render_licences_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">' . esc_html__( '2. Bordereau licences dirigeants FFST', 'ufsc-clubs' ) . '</h2>';
        if ( $readiness['minimum_licences_ok'] ) {
            echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Minimum FFST atteint : au moins 10 licences pour la saison.', 'ufsc-clubs' ) . '</strong></p></div>';
        } else {
            echo '<div class="notice notice-warning inline"><p><strong>' . esc_html( sprintf( __( 'Minimum FFST non atteint : %1$d licence(s) enregistrée(s), 10 requises.', 'ufsc-clubs' ), $readiness['licence_count'] ) ) . '</strong></p></div>';
        }
        echo '<p>' . esc_html__( 'Le président, le secrétaire, le trésorier et le ou les entraîneurs doivent être identifiés parmi les licences de la saison avant génération du bordereau.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function render_documents_section( $readiness ) {
        echo '<div class="postbox" style="padding:18px;"><h2 style="margin-top:0;">' . esc_html__( '3. Génération des documents', 'ufsc-clubs' ) . '</h2>';
        echo '<p>' . esc_html__( 'Cette étape contrôle et prépare les données. Les modèles FFST officiels seront générés depuis ce même écran dans la prochaine PR.', 'ufsc-clubs' ) . '</p>';
        $disabled = $readiness['percent'] < 100 ? ' disabled' : '';
        echo '<button type="button" class="button button-primary"' . $disabled . '>' . esc_html__( 'Générer le dossier FFST (à brancher)', 'ufsc-clubs' ) . '</button>';
        echo '<p class="description">' . esc_html__( 'Aucune donnée ni document FFST n’est exposé dans l’espace du représentant du club.', 'ufsc-clubs' ) . '</p></div>';
    }

    private static function find_role_licence( $licences, $aliases ) {
        foreach ( (array) $licences as $licence ) {
            $role = strtolower( remove_accents( trim( (string) self::value( $licence, array( 'role', 'fonction', 'poste', 'position' ) ) ) ) );
            foreach ( $aliases as $alias ) {
                if ( false !== strpos( $role, strtolower( remove_accents( $alias ) ) ) ) { return $licence; }
            }
        }
        return null;
    }

    private static function person_name( $licence ) {
        return trim( self::value( $licence, array( 'prenom', 'first_name' ) ) . ' ' . self::value( $licence, array( 'nom', 'last_name' ) ) );
    }

    private static function value( $object, $keys ) {
        foreach ( (array) $keys as $key ) {
            if ( is_object( $object ) && isset( $object->{$key} ) && '' !== trim( (string) $object->{$key} ) ) { return (string) $object->{$key}; }
            if ( is_array( $object ) && isset( $object[$key] ) && '' !== trim( (string) $object[$key] ) ) { return (string) $object[$key]; }
        }
        return '';
    }
}
