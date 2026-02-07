<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Capabilities and Roles registry.
 */
class UFSC_Capabilities {
    const ROLE_RESPONSABLE_LIGUE = 'ufsc_responsable_ligue';

    const CAP_MANAGE_READ              = 'ufsc_manage_read';
    const CAP_MANAGE_VIEW_REPORTS      = 'ufsc_manage_view_reports';
    const CAP_LICENCE_READ             = 'ufsc_licence_read';
    const CAP_LICENCE_CREATE           = 'ufsc_licence_create';
    const CAP_LICENCE_EDIT             = 'ufsc_licence_edit';
    const CAP_LICENCE_EXPORT           = 'ufsc_licence_export';
    const CAP_COMPETITION_READ         = 'ufsc_competition_read';
    const CAP_COMPETITION_CREATE       = 'ufsc_competition_create';
    const CAP_COMPETITION_EDIT         = 'ufsc_competition_edit';
    const CAP_COMPETITION_ENTRIES      = 'ufsc_competition_entries_manage';
    const CAP_COMPETITION_EXPORT       = 'ufsc_competition_export';
    const CAP_SCOPE_ALL_REGIONS        = 'ufsc_scope_all_regions';

    /**
     * Register capabilities and roles (idempotent).
     */
    public static function register_caps() {
        $caps = self::get_all_caps();

        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            foreach ( $caps as $cap ) {
                $admin_role->add_cap( $cap );
            }
        }

        $responsable_caps = array(
            'read'                         => true,
            self::CAP_MANAGE_READ          => true,
            self::CAP_LICENCE_READ         => true,
            self::CAP_LICENCE_CREATE       => true,
            self::CAP_LICENCE_EDIT         => true,
            self::CAP_LICENCE_EXPORT       => true,
            self::CAP_COMPETITION_READ     => true,
            self::CAP_COMPETITION_CREATE   => true,
            self::CAP_COMPETITION_EDIT     => true,
            self::CAP_COMPETITION_ENTRIES  => true,
            self::CAP_COMPETITION_EXPORT   => true,
        );

        $role = get_role( self::ROLE_RESPONSABLE_LIGUE );
        if ( ! $role ) {
            add_role( self::ROLE_RESPONSABLE_LIGUE, __( 'Responsable de Ligue', 'ufsc-clubs' ), $responsable_caps );
        } else {
            foreach ( $responsable_caps as $cap => $grant ) {
                if ( $grant ) {
                    $role->add_cap( $cap );
                }
            }
        }
    }

    /**
     * Get the UFSC capabilities list.
     *
     * @return string[]
     */
    public static function get_all_caps() {
        return array(
            self::CAP_MANAGE_READ,
            self::CAP_MANAGE_VIEW_REPORTS,
            self::CAP_LICENCE_READ,
            self::CAP_LICENCE_CREATE,
            self::CAP_LICENCE_EDIT,
            self::CAP_LICENCE_EXPORT,
            self::CAP_COMPETITION_READ,
            self::CAP_COMPETITION_CREATE,
            self::CAP_COMPETITION_EDIT,
            self::CAP_COMPETITION_ENTRIES,
            self::CAP_COMPETITION_EXPORT,
            self::CAP_SCOPE_ALL_REGIONS,
        );
    }

    /**
     * Backward-compatible capability check.
     *
     * @param string $cap Capability to check.
     * @param int    $user_id Optional user ID.
     * @return bool
     */
    public static function user_can( $cap, $user_id = 0 ) {
        if ( $user_id ) {
            if ( user_can( $user_id, $cap ) ) {
                return true;
            }
        } elseif ( current_user_can( $cap ) ) {
            return true;
        }

        $aliases = array(
            self::CAP_MANAGE_READ => 'manage_options',
        );

        if ( isset( $aliases[ $cap ] ) ) {
            return $user_id ? user_can( $user_id, $aliases[ $cap ] ) : current_user_can( $aliases[ $cap ] );
        }

        return false;
    }
}
