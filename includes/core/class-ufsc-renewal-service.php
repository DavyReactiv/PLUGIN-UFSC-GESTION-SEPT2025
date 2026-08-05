<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Domain rules shared by front, administration and WooCommerce renewal entry points. */
final class UFSC_Renewal_Service {
    const DUPLICATE_MESSAGE = 'Cette licence est déjà renouvelée ou fait déjà l’objet d’une demande pour %s.';

    public static function person_key( $licence, $club_id ) {
        $ufsc = UFSC_Identifier_Resolver::read( $licence, 'licence_ufsc' );
        if ( $ufsc ) { return 'ufsc:' . strtolower( $ufsc ); }
        $previous = absint( is_array( $licence ) ? ( $licence['previous_licence_id'] ?? $licence['id'] ?? 0 ) : ( $licence->previous_licence_id ?? $licence->id ?? 0 ) );
        if ( $previous ) { return 'previous:' . $previous; }
        $get = static function( $field ) use ( $licence ) { return trim( (string) ( is_array( $licence ) ? ( $licence[$field] ?? '' ) : ( $licence->{$field} ?? '' ) ) ); };
        if ( ! $get( 'nom' ) || ! $get( 'prenom' ) || ! $get( 'date_naissance' ) ) { return ''; }
        return 'legacy:' . hash( 'sha256', remove_accents( strtolower( $get('nom').'|'.$get('prenom').'|'.$get('date_naissance').'|'.absint($club_id) ) ) );
    }

    public static function can_renew( $source, $club_id, $target_season ) {
        if ( ! $source || ! absint( $club_id ) || ! $target_season ) { return new WP_Error( 'invalid_renewal', __( 'Demande de renouvellement incomplète.', 'ufsc-clubs' ) ); }
        $gate = function_exists( 'ufsc_club_can_manage_licences_for_season' ) ? ufsc_club_can_manage_licences_for_season( $club_id, $target_season ) : array( 'allowed' => false );
        if ( empty( $gate['allowed'] ) ) { return new WP_Error( 'inactive_affiliation', __( 'L’affiliation du club doit être active ou validée pour cette saison.', 'ufsc-clubs' ) ); }
        $key = self::person_key( $source, $club_id );
        if ( ! $key ) { return new WP_Error( 'incomplete_identity', __( 'L’identité est incomplète : le renouvellement est bloqué.', 'ufsc-clubs' ) ); }
        $existing = self::find_annual( $key, $club_id, $target_season );
        return $existing ? new WP_Error( 'duplicate_renewal', sprintf( __( self::DUPLICATE_MESSAGE, 'ufsc-clubs' ), $target_season ) ) : true;
    }

    public static function find_annual( $person_key, $club_id, $season ) {
        global $wpdb;
        $table = UFSC_SQL::get_settings()['table_licences'];
        $columns = (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( in_array( 'person_identifier', $columns, true ) ) {
            $season_column = self::season_column( $columns );
            if ( $season_column ) {
                $season_value = 'season_end_year' === $season_column && function_exists('ufsc_get_season_end_year_from_label') ? ufsc_get_season_end_year_from_label($season) : $season;
                return absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE club_id=%d AND person_identifier=%s AND `{$season_column}`=%s LIMIT 1", $club_id, $person_key, $season_value ) ) );
            }
        }
        return 0;
    }

    /** Build an allow-list for a fresh annual row. ASPTT, payment and expiring documents are intentionally absent. */
    public static function renewal_payload( $source, $club_id, $season ) {
        $source = (array) $source;
        $payload = array( 'club_id'=>absint($club_id), 'previous_licence_id'=>absint($source['id'] ?? 0), 'person_identifier'=>self::person_key($source,$club_id), 'statut'=>'pending_payment', 'payment_status'=>'pending' );
        foreach ( array( 'nom','nom_licence','prenom','date_naissance','sexe','gender','role','fighter_level','niveau_combattant','email','telephone','numero_licence_ufsc' ) as $field ) {
            if ( array_key_exists( $field, $source ) ) { $payload[$field] = $source[$field]; }
        }
        $payload['season'] = $season; $payload['saison'] = $season; $payload['paid_season'] = $season;
        if ( function_exists( 'ufsc_get_season_end_year_from_label' ) ) { $payload['season_end_year'] = ufsc_get_season_end_year_from_label( $season ); }
        return $payload;
    }

    public static function cart_metadata( $source, $club_id, $season, $draft_id = 0 ) {
        return array( 'ufsc_club_id'=>absint($club_id), 'ufsc_licence_id'=>absint($draft_id), 'ufsc_renew_from_licence_id'=>absint(is_array($source)?($source['id']??0):($source->id??0)), 'ufsc_person_identifier'=>self::person_key($source,$club_id), 'ufsc_target_season'=>$season, 'ufsc_item_type'=>'licence_renewal', 'ufsc_action'=>'renew_licence', 'ufsc_numero_licence_ufsc'=>UFSC_Identifier_Resolver::read($source,'licence_ufsc'), 'quantity'=>1 );
    }

    private static function season_column( $columns ) {
        foreach ( array( 'season','saison','paid_season','season_end_year' ) as $column ) { if ( in_array($column,$columns,true) ) { return $column; } }
        return '';
    }
}
