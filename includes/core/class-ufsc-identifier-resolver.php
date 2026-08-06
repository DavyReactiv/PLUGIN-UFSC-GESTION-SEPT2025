<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Canonical map; legacy fields are read aliases only and are never cross-copied. */
final class UFSC_Identifier_Resolver {
    const FIELDS = array(
        'club_ufsc'    => array( 'numero_affiliation_ufsc', 'num_affiliation', 'numero_affiliation' ),
        'club_asptt'   => array( 'numero_affiliation_asptt' ),
        'licence_ufsc' => array( 'numero_licence_ufsc', 'numero_licence', 'num_licence', 'licence_number' ),
        // Delegataire/source values are intentionally excluded: their historic
        // semantics are ambiguous and must be diagnosed, never relabelled ASPTT.
        'licence_asptt'=> array( 'numero_licence_asptt' ),
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

    public static function classify_field( $field ) {
        $confirmed = array(
            'numero_affiliation_ufsc' => 'ufsc_confirmed',
            'numero_affiliation_asptt' => 'asptt_confirmed',
            'numero_licence_ufsc' => 'ufsc_confirmed',
            'numero_licence_asptt' => 'asptt_confirmed',
        );
        if ( isset( $confirmed[ $field ] ) ) { return $confirmed[ $field ]; }
        if ( in_array( $field, array( 'numero_licence_delegataire', 'source_licence_number', 'numero_licence', 'num_licence', 'licence_number', 'num_affiliation', 'numero_affiliation' ), true ) ) {
            return 'ambiguous_legacy';
        }
        return 'unknown';
    }
}
