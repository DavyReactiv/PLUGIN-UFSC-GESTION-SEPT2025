<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Single authority for permanent UFSC identifiers and administrator-owned ASPTT values. */
final class UFSC_Identifier_Service {
    const TYPES = array( 'club' => 'UFSC-C-', 'licence' => 'UFSC-L-' );

    public static function get( $type, $entity_id ) {
        global $wpdb;
        if ( ! isset( self::TYPES[ $type ] ) || absint( $entity_id ) < 1 ) { return ''; }
        return (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT identifier_value FROM {$wpdb->prefix}ufsc_identifiers WHERE identifier_type=%s AND entity_type=%s AND entity_id=%d LIMIT 1",
            $type, $type, absint( $entity_id )
        ) );
    }

    /** Reserve atomically. Repeated calls for an entity always return its first identifier. */
    public static function assign( $type, $entity_id, $user_id = 0 ) {
        global $wpdb;
        $entity_id = absint( $entity_id );
        if ( ! isset( self::TYPES[ $type ] ) || ! $entity_id ) {
            return new WP_Error( 'invalid_entity', __( 'Entité UFSC invalide.', 'ufsc-clubs' ) );
        }
        list( $entity_table ) = self::entity_storage( $type );
        if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$entity_table}` WHERE id=%d", $entity_id ) ) ) {
            return new WP_Error( 'unknown_entity', __( 'Cette entité n’existe pas.', 'ufsc-clubs' ) );
        }
        $existing = self::get( $type, $entity_id );
        if ( $existing ) { return $existing; }

        $sequence = $wpdb->prefix . 'ufsc_identifier_sequences';
        $registry = $wpdb->prefix . 'ufsc_identifiers';
        $wpdb->query( 'START TRANSACTION' );
        $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$sequence} (identifier_type,next_value,updated_at) VALUES (%s,LAST_INSERT_ID(1),%s)
             ON DUPLICATE KEY UPDATE next_value=LAST_INSERT_ID(next_value+1),updated_at=VALUES(updated_at)",
            $type, current_time( 'mysql' )
        ) );
        $number = (int) $wpdb->insert_id;
        $prefix = (string) apply_filters( 'ufsc_identifier_prefix', self::TYPES[ $type ], $type );
        // A filter may customize formatting, but may not merge the two value spaces.
        if ( '' === $prefix || $prefix === (string) apply_filters( 'ufsc_identifier_prefix', self::TYPES[ 'club' === $type ? 'licence' : 'club' ], 'club' === $type ? 'licence' : 'club' ) ) {
            $prefix = self::TYPES[ $type ];
        }
        $width  = max( 6, min( 12, (int) apply_filters( 'ufsc_identifier_width', 6, $type ) ) );
        $value  = $prefix . str_pad( (string) $number, $width, '0', STR_PAD_LEFT );
        $inserted = $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO {$registry} (identifier_type,identifier_value,entity_type,entity_id,status,created_by,created_at)
             VALUES (%s,%s,%s,%d,'active',%d,%s)",
            $type, $value, $type, $entity_id, absint( $user_id ), current_time( 'mysql' )
        ) );
        if ( 1 !== $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            $existing = self::get( $type, $entity_id );
            return $existing ?: new WP_Error( 'identifier_collision', __( 'Le numéro n’a pas pu être réservé sans collision.', 'ufsc-clubs' ) );
        }
        if ( false === self::write_canonical( $type, $entity_id, $value ) ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'canonical_write_failed', __( 'Le numéro a été réservé mais son rattachement a échoué.', 'ufsc-clubs' ) );
        }
        $wpdb->query( 'COMMIT' );
        self::audit( 'generate_ufsc', $type, $entity_id, '', $value, $user_id );
        return $value;
    }

    public static function save_asptt( $type, $entity_id, $value, $user_id = 0 ) {
        global $wpdb;
        $value = trim( sanitize_text_field( $value ) );
        if ( ! isset( self::TYPES[ $type ] ) || ! absint( $entity_id ) ) { return new WP_Error( 'invalid_entity', __( 'Entité invalide.', 'ufsc-clubs' ) ); }
        if ( 0 === strpos( $value, 'UFSC-' ) || 0 === stripos( $value, 'UFSC-' ) ) { return new WP_Error( 'mixed_identifier', __( 'Un numéro UFSC ne peut pas être enregistré comme numéro ASPTT.', 'ufsc-clubs' ) ); }
        list( $table, $field ) = self::entity_storage( $type, true );
        if ( ! (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE id=%d", absint( $entity_id ) ) ) ) {
            return new WP_Error( 'unknown_entity', __( 'Cette entité n’existe pas.', 'ufsc-clubs' ) );
        }
        if ( $value ) {
            $owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE `{$field}`=%s AND id<>%d LIMIT 1", $value, absint( $entity_id ) ) );
            if ( $owner ) { self::audit( 'reject_duplicate_asptt', $type, $entity_id, '', $value, $user_id ); return new WP_Error( 'duplicate_asptt', __( 'Ce numéro ASPTT est déjà attribué.', 'ufsc-clubs' ) ); }
        }
        $old = (string) $wpdb->get_var( $wpdb->prepare( "SELECT `{$field}` FROM `{$table}` WHERE id=%d", absint( $entity_id ) ) );
        $result = $wpdb->update( $table, array( $field => ( '' === $value ? null : $value ) ), array( 'id' => absint( $entity_id ) ), array( '%s' ), array( '%d' ) );
        if ( false === $result ) { return new WP_Error( 'save_failed', __( 'Le numéro ASPTT n’a pas pu être enregistré.', 'ufsc-clubs' ) ); }
        self::audit( 'update_asptt', $type, $entity_id, $old, $value, $user_id );
        return $value;
    }

    /** Read-only duplicate report. IDs and seasons only; no personal data. */
    public static function duplicate_audit() {
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $checks = array(
            'Numéros UFSC club'    => array($settings['table_clubs'],'numero_affiliation_ufsc'),
            'Numéros ASPTT club'   => array($settings['table_clubs'],'numero_affiliation_asptt'),
            'Numéros UFSC licence' => array($settings['table_licences'],'numero_licence_ufsc'),
            'Numéros ASPTT licence'=> array($settings['table_licences'],'numero_licence_asptt'),
        );
        $report = array();
        foreach ( $checks as $label => $check ) {
            list($table,$column)=$check;
            $columns=(array)$wpdb->get_col("SHOW COLUMNS FROM `{$table}`",0);
            if (!in_array($column,$columns,true)) { $report[$label]=array(); continue; }
            $report[$label]=(array)$wpdb->get_results("SELECT `{$column}` value, GROUP_CONCAT(id ORDER BY id) ids, COUNT(*) count FROM `{$table}` WHERE `{$column}` IS NOT NULL AND TRIM(`{$column}`)<>'' GROUP BY `{$column}` HAVING COUNT(*)>1",ARRAY_A);
        }
        $aff=$wpdb->prefix.'ufsc_affiliations_seasons';
        $report['Affiliations annuelles']= (array)$wpdb->get_results("SELECT CONCAT(club_id,' / ',season) value,GROUP_CONCAT(id ORDER BY id) ids,COUNT(*) count FROM `{$aff}` GROUP BY club_id,season HAVING COUNT(*)>1",ARRAY_A);
        $lic=$settings['table_licences']; $cols=(array)$wpdb->get_col("SHOW COLUMNS FROM `{$lic}`",0); $season=class_exists('UFSC_Renewal_Service')?self::first_column($cols,array('season','saison','paid_season','season_end_year')):'';
        $report['Licences annuelles'] = ($season && in_array('person_identifier',$cols,true)) ? (array)$wpdb->get_results("SELECT CONCAT(club_id,' / ',person_identifier,' / ',`{$season}`) value,GROUP_CONCAT(id ORDER BY id) ids,COUNT(*) count FROM `{$lic}` WHERE person_identifier IS NOT NULL AND person_identifier<>'' GROUP BY club_id,person_identifier,`{$season}` HAVING COUNT(*)>1",ARRAY_A) : array();
        return $report;
    }

    private static function first_column($columns,$candidates) { foreach($candidates as $candidate){if(in_array($candidate,$columns,true)){return $candidate;}} return ''; }

    private static function entity_storage( $type, $asptt = false ) {
        $settings = UFSC_SQL::get_settings();
        return 'club' === $type
            ? array( $settings['table_clubs'], $asptt ? 'numero_affiliation_asptt' : 'numero_affiliation_ufsc' )
            : array( $settings['table_licences'], $asptt ? 'numero_licence_asptt' : 'numero_licence_ufsc' );
    }

    private static function write_canonical( $type, $entity_id, $value ) {
        global $wpdb;
        list( $table, $field ) = self::entity_storage( $type );
        return $wpdb->query( $wpdb->prepare( "UPDATE `{$table}` SET `{$field}`=%s WHERE id=%d AND (`{$field}` IS NULL OR `{$field}`='')", $value, $entity_id ) );
    }

    private static function audit( $action, $type, $id, $old, $new, $user_id, $justification = '' ) {
        global $wpdb;
        $season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '';
        $wpdb->insert( $wpdb->prefix . 'ufsc_identifier_audit', array( 'action'=>$action, 'entity_type'=>$type, 'entity_id'=>$id, 'old_value'=>$old, 'new_value'=>$new, 'user_id'=>absint($user_id), 'season'=>$season, 'justification'=>sanitize_text_field($justification), 'created_at'=>current_time('mysql') ) );
    }

    public static function handle_generate_request() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Action interdite.', 'ufsc-clubs' ), 403 ); }
        $type = sanitize_key( wp_unslash( $_POST['entity_type'] ?? '' ) ); $id = absint( $_POST['entity_id'] ?? 0 );
        check_admin_referer( 'ufsc_generate_identifier_' . $type . '_' . $id );
        $result = self::assign( $type, $id, get_current_user_id() );
        self::redirect_result( $result );
    }

    public static function handle_asptt_request() {
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Action interdite.', 'ufsc-clubs' ), 403 ); }
        $type = sanitize_key( wp_unslash( $_POST['entity_type'] ?? '' ) ); $id = absint( $_POST['entity_id'] ?? 0 );
        check_admin_referer( 'ufsc_save_asptt_' . $type . '_' . $id );
        self::redirect_result( self::save_asptt( $type, $id, wp_unslash( $_POST['asptt_identifier'] ?? '' ), get_current_user_id() ) );
    }

    private static function redirect_result( $result ) {
        $url = wp_get_referer() ?: admin_url();
        $args = is_wp_error( $result ) ? array( 'ufsc_error' => $result->get_error_message() ) : array( 'ufsc_message' => __( 'Identifiant enregistré.', 'ufsc-clubs' ) );
        wp_safe_redirect( add_query_arg( $args, $url ) ); exit;
    }
}
