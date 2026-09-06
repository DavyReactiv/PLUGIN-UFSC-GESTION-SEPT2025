<?php
/** Static guards for canonical season filtering and historical UI. */
$root = dirname( __DIR__ );
$admin = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$front = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$service = file_get_contents( $root . '/includes/core/class-ufsc-renewal-service.php' );
$assert = static function( $condition, $message ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$message}\n" ); exit( 1 ); } };
$assert( false !== strpos( $admin, "array( 'season', 'saison', 'paid_season', 'season_end_year' )" ), 'Filter and SELECT use the same canonical season priority.' );
$assert( false !== strpos( $admin, 'l.{$season_column}' ) && false !== strpos( $admin, "\$filters['filter_season'] = '__current'" ), 'Default season is applied to the SQL predicate.' );
$assert( false !== strpos( $admin, "esc_html__( 'Saison terminée'" ) && false !== strpos( $admin, '! $is_historical && $can_manage_licences' ), 'Historical rows have contextual status and no direct mutation actions.' );
$historical_branch = substr( $admin, strpos( $admin, 'if ( $is_historical ) {' ), 3000 );
$assert( false === strpos( $historical_branch, "esc_html__('Paiement'" ) && false === strpos( $historical_branch, "esc_html__( 'Annuler'" ), 'Historical branch contains no payment/cancel action.' );
$assert( false !== strpos( $front, "ufsc_get_licence_season_context_status( \$licence" ) && false !== strpos( $front, "'blocked' === ( \$season_context['renewal_state']" ), 'Front archives use the shared contextual state.' );

$payload_start = strpos( $service, 'public static function renewal_payload' );
$payload_end = strpos( $service, 'public static function create_target_draft', $payload_start );
$renewal_payload = false !== $payload_start && false !== $payload_end ? substr( $service, $payload_start, $payload_end - $payload_start ) : '';
$assert(
    false !== strpos( $service, "'previous_licence_id'" )
    && '' !== $renewal_payload
    && false === strpos( $renewal_payload, "'numero_licence_asptt'" )
    && false !== strpos( $service, 'legacy_partner_fields' ),
    'Renewal creates annual lineage while retaining ASPTT only as an explicit non-copy historical field.'
);
$assert( false === strpos( $service, 'UPDATE' ), 'Context and renewal service never update the historical source row.' );
echo "Licence season context static safeguards passed.\n";
