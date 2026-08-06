<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical map; legacy fields are read aliases only and are never cross-copied. */
final class UFSC_Identifier_Resolver {
    const FIELDS = array(
        'club_ufsc'    => array( 'numero_affiliation_ufsc', 'num_affiliation', 'numero_affiliation' ),
        'club_asptt'   => array( 'numero_affiliation_asptt' ),
        'licence_ufsc' => array( 'numero_licence_ufsc', 'numero_licence', 'num_licence', 'licence_number' ),
        'licence_asptt'=> array( 'numero_licence_asptt' ),
    );

    const AMBIGUOUS_FIELDS = array(
        'club'    => array( 'num_affiliation', 'numero_affiliation' ),
        'licence' => array( 'numero_licence', 'num_licence', 'licence_number', 'numero_licence_delegataire', 'source_licence_number' ),
    );

    public static function canonical_field( $kind ) {
        return self::FIELDS[ $kind ][0] ?? '';
    }

    public static function read( $row, $kind ) {
        foreach ( self::FIELDS[ $kind ] ?? array() as $field ) {
            $value = is_array( $row ) ? ( $row[ $field ] ?? '' ) : ( $row->{$field} ?? '' );
            if ( '' !== trim( (string) $value ) ) { return trim( (string) $value ); }
        }
        return '';
    }

    /** Return legacy values for diagnostics only; callers must not reclassify or migrate them. */
    public static function read_ambiguous( $row, $entity_type ) {
        $values = array();
        foreach ( self::AMBIGUOUS_FIELDS[ $entity_type ] ?? array() as $field ) {
            $value = is_array( $row ) ? ( $row[ $field ] ?? '' ) : ( $row->{$field} ?? '' );
            if ( '' !== trim( (string) $value ) ) { $values[ $field ] = trim( (string) $value ); }
        }
        return $values;
    }
}
