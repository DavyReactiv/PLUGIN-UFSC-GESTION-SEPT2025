<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Determine allowed fields for an entity based on context and status.
 *
 * @param string $entity  Entity type: 'licence' or 'club'.
 * @param string $context Operation context: 'create', 'update', 'frontend', etc.
 * @param string $status  Current status of the entity.
 * @return array List of allowed field keys.
 */
function ufsc_allowed_fields( $entity, $context = 'create', $status = '' ) {
    switch ( $entity ) {
        case 'licence':
            $fields = array_keys( UFSC_SQL::get_licence_fields() );
            if ( in_array( $context, array( 'update', 'frontend' ), true ) && 'valide' === $status ) {
                $fields = array( 'email', 'tel_fixe', 'tel_mobile' );
            }
            return $fields;

        case 'club':
            $fields = array_keys( UFSC_SQL::get_club_fields() );
            if ( in_array( $context, array( 'update', 'frontend' ), true ) && 'valide' === $status ) {
                $fields = array( 'email', 'telephone' );
            }
            return $fields;
    }

    return array();
}
