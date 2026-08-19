<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Domain rules shared by front, administration and WooCommerce renewal entry points. */
final class UFSC_Renewal_Service {
    const DUPLICATE_MESSAGE = 'Cette licence est déjà renouvelée ou fait déjà l’objet d’une demande pour %s.';

    /** Fields a club may propose for the new annual row; never applied to the source row. */
    public static function editable_renewal_fields() {
        return array( 'adresse', 'suite_adresse', 'complement_adresse', 'code_postal', 'ville', 'pays', 'email', 'telephone', 'tel_fixe', 'tel_mobile', 'profession', 'contact_urgence', 'legal_representative_name', 'representant_legal_nom', 'representant_legal_email', 'representant_legal_telephone', 'fighter_level', 'poids', 'competition', 'discipline', 'pratique', 'role', 'reduction_benevole', 'reduction_benevole_num', 'reduction_postier', 'reduction_postier_num', 'identifiant_laposte_flag', 'identifiant_laposte', 'fonction_publique', 'licence_delegataire', 'diffusion_image', 'infos_fsasptt', 'infos_asptt', 'infos_cr', 'infos_partenaires', 'honorabilite', 'honorability_confirmed', 'assurance_dommage_corporel', 'assurance_assistance', 'health_questionnaire_confirmed', 'note', 'nom', 'prenom', 'date_naissance', 'sexe' );
    }

    /**
     * Validate a proposed profile without writing anything to the historical licence.
     * Returned values are safe to carry as nominative cart/order metadata.
     */
    public static function sanitize_renewal_updates( $source, $raw ) {
        $source = (object) $source;
        $raw = is_array( $raw ) ? $raw : array();
        $data = array(); $errors = array(); $changes = array(); $sensitive = false;
        $text_fields = array( 'adresse', 'suite_adresse', 'complement_adresse', 'code_postal', 'ville', 'pays', 'profession', 'contact_urgence', 'legal_representative_name', 'representant_legal_nom', 'discipline', 'pratique', 'role', 'reduction_benevole_num', 'reduction_postier_num', 'identifiant_laposte', 'note', 'nom', 'prenom', 'sexe' );
        foreach ( $text_fields as $field ) {
            if ( ! array_key_exists( $field, $raw ) ) { continue; }
            $data[$field] = sanitize_text_field( wp_unslash( $raw[$field] ) );
        }
        foreach ( array( 'email', 'representant_legal_email' ) as $field ) {
            if ( ! array_key_exists( $field, $raw ) ) { continue; }
            $value = sanitize_email( wp_unslash( $raw[$field] ) );
            if ( '' !== trim( (string) $raw[$field] ) && ! is_email( $value ) ) { $errors[$field] = __( 'Adresse e-mail invalide.', 'ufsc-clubs' ); }
            $data[$field] = $value;
        }
        foreach ( array( 'telephone', 'tel_fixe', 'tel_mobile', 'representant_legal_telephone' ) as $field ) {
            if ( ! array_key_exists( $field, $raw ) ) { continue; }
            $data[$field] = preg_replace( '/[^0-9+(). -]/', '', sanitize_text_field( wp_unslash( $raw[$field] ) ) );
        }
        if ( array_key_exists( 'date_naissance', $raw ) ) {
            $value = sanitize_text_field( wp_unslash( $raw['date_naissance'] ) );
            $valid = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
            if ( ! $valid || $valid->format( 'Y-m-d' ) !== $value ) { $errors['date_naissance'] = __( 'Date de naissance invalide.', 'ufsc-clubs' ); }
            $data['date_naissance'] = $value;
        }
        $birth_for_level = $data['date_naissance'] ?? ( $source->date_naissance ?? '' );
        $level_source = array_key_exists( 'fighter_level', $raw ) ? $raw['fighter_level'] : ( $source->fighter_level ?? '' );
        $level = function_exists( 'ufsc_normalize_fighter_level' ) ? ufsc_normalize_fighter_level( $level_source ) : sanitize_key( (string) $level_source );
        if ( function_exists( 'ufsc_is_selectable_fighter_level' ) && ! ufsc_is_selectable_fighter_level( $level ) && function_exists( 'ufsc_get_default_fighter_level' ) ) {
            $level = ufsc_get_default_fighter_level( $birth_for_level );
        }
        if ( function_exists( 'ufsc_validate_fighter_level' ) ) {
            $level_validation = ufsc_validate_fighter_level( $level, $birth_for_level, false );
            if ( is_wp_error( $level_validation ) ) { $errors['fighter_level'] = $level_validation->get_error_message(); }
        } elseif ( ! isset( ufsc_get_sport_level_options()[$level] ) ) {
            $errors['fighter_level'] = __( 'Le niveau sportif est obligatoire pour renouveler cette licence.', 'ufsc-clubs' );
        }
        $data['fighter_level'] = $level;
        $weight = UFSC_Category_Repository::normalize_weight( $raw['poids'] ?? $source->poids ?? '' );
        if ( null === $weight || $weight < 20 || $weight > 300 ) { $errors['poids'] = __( 'Le poids déclaré doit être compris entre 20 et 300 kg.', 'ufsc-clubs' ); }
        $data['poids'] = $weight;
        foreach ( array( 'competition', 'reduction_benevole', 'reduction_postier', 'identifiant_laposte_flag', 'fonction_publique', 'licence_delegataire', 'diffusion_image', 'infos_fsasptt', 'infos_asptt', 'infos_cr', 'infos_partenaires', 'honorabilite', 'honorability_confirmed', 'assurance_dommage_corporel', 'assurance_assistance', 'health_questionnaire_confirmed' ) as $field ) { $data[$field] = empty( $raw[$field] ) ? 0 : 1; }
        foreach ( array( 'nom', 'prenom', 'email', 'date_naissance', 'sexe', 'adresse', 'ville', 'code_postal' ) as $field ) {
            $value = $data[$field] ?? $source->{$field} ?? '';
            if ( '' === trim( (string) $value ) ) { $errors[$field] = sprintf( __( 'Le champ %s est obligatoire.', 'ufsc-clubs' ), $field ); }
            elseif ( ! array_key_exists( $field, $data ) ) { $data[$field] = $value; }
        }
        foreach ( $data as $field => $value ) {
            $old = isset( $source->{$field} ) ? (string) $source->{$field} : '';
            if ( (string) $value !== $old ) { $changes[$field] = array( 'old' => $old, 'new' => (string) $value ); }
        }
        foreach ( array( 'nom', 'prenom', 'date_naissance', 'sexe' ) as $field ) { if ( isset( $changes[$field] ) ) { $sensitive = true; } }
        if ( $sensitive && empty( $raw['confirm_identity_change'] ) ) { $errors['confirm_identity_change'] = __( 'Confirmez la modification sensible de l’identité.', 'ufsc-clubs' ); }
        return array( 'data' => $data, 'changes' => $changes, 'errors' => $errors, 'sensitive_identity_change' => $sensitive );
    }

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
        $source_row = is_array( $source ) ? (object) $source : $source;
        if ( absint( $source_row->club_id ?? 0 ) !== absint( $club_id ) ) { return new WP_Error( 'source_club_mismatch', __( 'Cette licence n’appartient pas au club connecté.', 'ufsc-clubs' ) ); }
        $source_status = function_exists( 'ufsc_get_licence_status_from_record' ) ? ufsc_get_licence_status_from_record( $source_row ) : sanitize_key( (string) ( ! empty( $source_row->statut ) ? $source_row->statut : ( $source_row->status ?? '' ) ) );
        if ( in_array( $source_status, array( 'validated', 'valid', 'active', 'approved' ), true ) ) { $source_status = 'valide'; }
        if ( 'valide' !== $source_status ) { return new WP_Error( 'source_status_blocked', __( 'Seule une licence validée de la saison précédente peut être renouvelée.', 'ufsc-clubs' ) ); }
        $target_start = self::season_start_year( $target_season );
        $expected_source_season = $target_start ? ( $target_start - 1 ) . '-' . $target_start : '';
        if ( ! $expected_source_season || self::licence_season( $source_row ) !== $expected_source_season ) { return new WP_Error( 'source_season_mismatch', __( 'La licence source doit appartenir exactement à la saison précédente.', 'ufsc-clubs' ) ); }
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

    /**
     * Present a seasonal status without ever changing the stored historical row.
     *
     * @return array<string,mixed>
     */
    public static function season_context_status( $licence, $current_season = '' ) {
        $licence = is_object( $licence ) ? $licence : (object) $licence;
        $current_season = $current_season ?: ( class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : '' );
        $source_season = self::licence_season( $licence );
        $historical_status = sanitize_key( (string) ( $licence->statut ?? '' ) );
        $source_id = absint( $licence->id ?? $licence->ufsc_admin_id ?? 0 );
        $club_id = absint( $licence->club_id ?? 0 );
        $is_historical = self::season_start_year( $source_season ) > 0 && self::season_start_year( $source_season ) < self::season_start_year( $current_season );
        $result = array(
            'historical_status' => $historical_status, 'source_season' => $source_season,
            'target_season' => $current_season, 'is_historical' => $is_historical,
            'is_current' => ! $is_historical && $source_season === $current_season,
            'renewal_state' => $is_historical ? 'renewable' : 'current',
            'renewal_allowed' => false, 'renewal_reason' => '', 'renewed_licence_id' => 0,
            'payable_order_id' => 0, 'label' => $is_historical ? __( 'Saison terminée', 'ufsc-clubs' ) : $historical_status,
            'badge_class' => $is_historical ? 'ufsc-badge-neutral' : '', 'action_label' => '', 'action_url' => '',
        );
        if ( ! $is_historical ) { return $result; }

        $person_key = self::person_key( $licence, $club_id );
        $renewed = $person_key ? self::find_annual_row( $person_key, $club_id, $current_season ) : null;
        if ( $renewed ) {
            $result['renewed_licence_id'] = absint( $renewed->id ?? 0 );
            $renewed_status = sanitize_key( (string) ( $renewed->statut ?? '' ) );
            if ( in_array( $renewed_status, array( 'draft', 'brouillon', 'pending_payment', 'pending', 'en_attente', 'pending_validation' ), true ) ) {
				$order = function_exists( 'ufsc_wc_find_pending_renewal_order' ) ? ufsc_wc_find_pending_renewal_order( 'renew_licence', $club_id, $current_season, $source_id ) : false;
				$payable = $order && is_callable( array( $order, 'needs_payment' ) ) && $order->needs_payment();
				if ( $payable ) {
					$result['payable_order_id'] = is_callable( array( $order, 'get_id' ) ) ? absint( $order->get_id() ) : 0;
					$result['renewal_state'] = 'payable';
					$result['action_label'] = __( 'Finaliser le paiement', 'ufsc-clubs' );
					$result['action_url'] = is_callable( array( $order, 'get_checkout_payment_url' ) ) ? (string) $order->get_checkout_payment_url() : '';
					return $result;
				}
                $result['renewal_state'] = 'pending';
                $result['action_label'] = __( 'Demande en cours', 'ufsc-clubs' );
            } else {
                $result['renewal_state'] = 'renewed';
                $result['action_label'] = __( 'Déjà renouvelée', 'ufsc-clubs' );
            }
            return $result;
        }

        if ( function_exists( 'ufsc_cart_has_renewal_item' ) && ufsc_cart_has_renewal_item( 'renew_licence', $club_id, $current_season, $source_id ) ) {
            $result['renewal_state'] = 'pending';
            $result['action_label'] = __( 'Demande en cours', 'ufsc-clubs' );
            return $result;
        }
        $order = function_exists( 'ufsc_wc_find_pending_renewal_order' ) ? ufsc_wc_find_pending_renewal_order( 'renew_licence', $club_id, $current_season, $source_id ) : false;
        if ( $order ) {
            $result['payable_order_id'] = is_callable( array( $order, 'get_id' ) ) ? absint( $order->get_id() ) : 0;
            $payable = is_callable( array( $order, 'needs_payment' ) ) && $order->needs_payment();
            $result['renewal_state'] = $payable ? 'payable' : 'pending';
            $result['action_label'] = $payable ? __( 'Finaliser le paiement', 'ufsc-clubs' ) : __( 'Demande en cours', 'ufsc-clubs' );
            $result['action_url'] = $payable && is_callable( array( $order, 'get_checkout_payment_url' ) ) ? (string) $order->get_checkout_payment_url() : '';
            return $result;
        }

        $allowed = self::can_renew( $licence, $club_id, $current_season );
        if ( is_wp_error( $allowed ) ) {
            $result['renewal_state'] = 'blocked';
            $result['renewal_reason'] = $allowed->get_error_message();
            $result['action_label'] = __( 'Renouvellement bloqué', 'ufsc-clubs' );
            return $result;
        }
        $result['renewal_allowed'] = true;
        $result['action_label'] = sprintf( __( 'Renouveler pour %s', 'ufsc-clubs' ), $current_season );
        return $result;
    }

    private static function find_annual_row( $person_key, $club_id, $season ) {
        global $wpdb;
        $id = self::find_annual( $person_key, $club_id, $season );
        if ( ! $id ) { return null; }
        $table = UFSC_SQL::get_settings()['table_licences'];
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d LIMIT 1", $id ) );
    }

    private static function licence_season( $licence ) {
        foreach ( array( 'season_resolved', 'season', 'saison', 'paid_season', 'season_end_year' ) as $field ) {
            $value = trim( (string) ( $licence->{$field} ?? '' ) );
            if ( '' === $value ) { continue; }
            if ( 'season_end_year' === $field && ctype_digit( $value ) ) { return ( (int) $value - 1 ) . '-' . (int) $value; }
            return str_replace( '/', '-', $value );
        }
        return '';
    }

    private static function season_start_year( $season ) {
        return preg_match( '/^(\d{4})-\d{4}$/', (string) $season, $matches ) ? (int) $matches[1] : 0;
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

    /**
     * Create the target-season draft before checkout, without mutating history.
     *
     * A database advisory lock makes the source/season pair idempotent across
     * concurrent requests. The returned row is therefore the single canonical
     * open renewal request used by the cart and by the renewal counter.
     *
     * @return array|WP_Error {licence_id:int, created:bool}
     */
    public static function create_target_draft( $source, $club_id, $season, $updates = array() ) {
        global $wpdb;

        $source = is_object( $source ) ? $source : (object) $source;
        $source_id = absint( $source->id ?? 0 );
        $club_id = absint( $club_id );
        if ( ! $source_id || ! $club_id || ! preg_match( '/^\d{4}-\d{4}$/', (string) $season ) ) {
            return new WP_Error( 'invalid_renewal_target', __( 'La demande de renouvellement est incomplete.', 'ufsc-clubs' ) );
        }

        $table = UFSC_SQL::get_settings()['table_licences'];
        $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );
        if ( ! $columns ) {
            return new WP_Error( 'renewal_schema_unavailable', __( 'Le schema des licences est indisponible.', 'ufsc-clubs' ) );
        }

        $lock_name = 'ufsc_renew_' . $club_id . '_' . $source_id . '_' . sanitize_key( $season );
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) ) ) {
            return new WP_Error( 'renewal_busy', __( 'Ce renouvellement est deja en cours. Reessayez dans quelques secondes.', 'ufsc-clubs' ) );
        }

        try {
            $person_key = self::person_key( $source, $club_id );
            $existing_id = $person_key ? self::find_annual( $person_key, $club_id, $season ) : 0;
            if ( $existing_id ) {
                $target = array( 'licence_id' => $existing_id, 'created' => false );
                return function_exists( 'apply_filters' )
                    ? apply_filters( 'ufsc_renewal_target_draft_result', $target, $source, $club_id, $season, $updates )
                    : $target;
            }

            $allowed = self::can_renew( $source, $club_id, $season );
            if ( is_wp_error( $allowed ) ) {
                return $allowed;
            }

            $data = self::renewal_payload( $source, $club_id, $season );
            $copy_fields = function_exists( 'ufsc_get_renewal_copy_fields' )
                ? (array) ufsc_get_renewal_copy_fields()
                : self::editable_renewal_fields();
            foreach ( array_unique( array_merge( $copy_fields, self::editable_renewal_fields() ) ) as $field ) {
                if ( in_array( $field, $columns, true ) && isset( $source->{$field} ) ) {
                    $data[ $field ] = $source->{$field};
                }
            }
            foreach ( (array) $updates as $field => $value ) {
                if ( in_array( $field, self::editable_renewal_fields(), true ) && in_array( $field, $columns, true ) ) {
                    $data[ $field ] = $value;
                }
            }

            if ( in_array( 'status', $columns, true ) ) { $data['status'] = 'pending_payment'; }
            if ( in_array( 'statut', $columns, true ) ) { $data['statut'] = 'pending_payment'; }
            if ( in_array( 'renewed_from_licence_id', $columns, true ) ) { $data['renewed_from_licence_id'] = $source_id; }
            if ( in_array( 'renewal_status', $columns, true ) ) { $data['renewal_status'] = 'renouvellement_en_attente'; }
            if ( in_array( 'is_included', $columns, true ) ) { $data['is_included'] = 0; }
            foreach ( array( 'date_creation', 'date_modification', 'date_inscription' ) as $date_column ) {
                if ( in_array( $date_column, $columns, true ) ) { $data[ $date_column ] = current_time( 'mysql' ); }
            }
            $data = array_intersect_key( $data, array_flip( $columns ) );
            if ( false === $wpdb->insert( $table, $data ) ) {
                return new WP_Error( 'renewal_insert_failed', __( 'La licence de la nouvelle saison n a pas pu etre creee.', 'ufsc-clubs' ) );
            }

            $new_id = absint( $wpdb->insert_id );
            if ( $new_id && function_exists( 'ufsc_set_licence_season' ) ) { ufsc_set_licence_season( $new_id, $season ); }
            $target = array( 'licence_id' => $new_id, 'created' => true );
            return function_exists( 'apply_filters' )
                ? apply_filters( 'ufsc_renewal_target_draft_result', $target, $source, $club_id, $season, $updates )
                : $target;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public static function cart_metadata( $source, $club_id, $season, $draft_id = 0 ) {
        return array( 'ufsc_club_id'=>absint($club_id), 'ufsc_licence_id'=>absint($draft_id), 'ufsc_renew_from_licence_id'=>absint(is_array($source)?($source['id']??0):($source->id??0)), 'ufsc_person_identifier'=>self::person_key($source,$club_id), 'ufsc_target_season'=>$season, 'ufsc_item_type'=>'licence_renewal', 'ufsc_action'=>'renew_licence', 'ufsc_numero_licence_ufsc'=>UFSC_Identifier_Resolver::read($source,'licence_ufsc'), 'quantity'=>1 );
    }

    private static function season_column( $columns ) {
        foreach ( array( 'season','saison','paid_season','season_end_year' ) as $column ) { if ( in_array($column,$columns,true) ) { return $column; } }
        return '';
    }
}
