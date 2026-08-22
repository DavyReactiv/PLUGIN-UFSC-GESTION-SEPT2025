<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Front club registration scope compatibility.
 *
 * A normal club applicant is allowed to choose any valid UFSC region when
 * creating their first club. Regional back-office accounts must keep their
 * existing scope restrictions. This layer only relaxes the all-region scope
 * check for the exact initial front registration request and never grants a
 * persistent capability or modifies stored user permissions.
 */

/**
 * Detect the exact first-club front registration request.
 *
 * @return bool
 */
function ufsc_front_club_registration_scope_request() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        return false;
    }

    $action = isset( $_REQUEST['action'] ) && ! is_array( $_REQUEST['action'] )
        ? sanitize_key( wp_unslash( $_REQUEST['action'] ) )
        : '';
    if ( 'ufsc_save_club' !== $action ) {
        return false;
    }

    $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
    if ( $club_id > 0 ) {
        return false;
    }

    return true;
}

/**
 * Temporarily allow an ordinary applicant to pass UFSC_Scope::assert_in_scope()
 * while creating their first club.
 *
 * The selected region is still validated by UFSC_CL_Utils::validate_club_data()
 * against the canonical UFSC region list. UFSC staff/read-only roles are
 * explicitly excluded so their regional perimeter remains enforced.
 *
 * @param array    $allcaps Effective capabilities.
 * @param string[] $caps    Primitive capabilities requested.
 * @param array    $args    Capability arguments.
 * @param WP_User  $user    User object.
 * @return array
 */
function ufsc_front_club_registration_allow_region_choice( $allcaps, $caps, $args, $user ) {
    unset( $args );

    if ( ! $user instanceof WP_User || ! class_exists( 'UFSC_Permissions' ) ) {
        return $allcaps;
    }

    $scope_cap = UFSC_Permissions::CAP_ALL_REGIONS_ACCESS;
    if ( ! in_array( $scope_cap, (array) $caps, true ) || ! ufsc_front_club_registration_scope_request() ) {
        return $allcaps;
    }

    if ( in_array( 'administrator', (array) $user->roles, true ) || ! empty( $user->allcaps['manage_options'] ) ) {
        return $allcaps;
    }

    $ufsc_staff_roles = array(
        'ufsc_region_viewer',
        'ufsc_region_manager',
        'ufsc_competition_manager',
        'ufsc_admin_limited',
    );
    if ( array_intersect( $ufsc_staff_roles, (array) $user->roles ) ) {
        return $allcaps;
    }

    // Do not relax the scope of accounts already carrying UFSC back-office rights.
    foreach ( array(
        UFSC_Permissions::CAP_GESTION_READ,
        UFSC_Permissions::CAP_GESTION_MANAGE,
        UFSC_Permissions::CAP_LICENCES_READ,
        UFSC_Permissions::CAP_LICENCES_MANAGE,
        UFSC_Permissions::CAP_REGIONS_MANAGE,
    ) as $backoffice_cap ) {
        if ( ! empty( $user->allcaps[ $backoffice_cap ] ) || ! empty( $user->caps[ $backoffice_cap ] ) ) {
            return $allcaps;
        }
    }

    $allcaps[ $scope_cap ] = true;
    return $allcaps;
}
add_filter( 'user_has_cap', 'ufsc_front_club_registration_allow_region_choice', 20, 4 );
