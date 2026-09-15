<?php
$root = dirname( __DIR__ );
$compliance = file_get_contents( $root . '/inc/common/compliance.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$start = strpos( $compliance, 'function ufsc_pack_row_consumes_slot' );
$end   = false !== $start ? strpos( $compliance, '/** Return effective pack rows', $start ) : false;
$body  = ( false !== $start && false !== $end ) ? substr( $compliance, $start, $end - $start ) : '';

$assert( '' !== $body, 'La fonction canonique de consommation du quota doit exister.' );
$status_pos   = strpos( $body, '$status = ufsc_pack_row_status' );
$included_pos = strpos( $body, "! empty( \$data['is_included'] )" );
$assert( false !== $status_pos, 'Le statut doit être lu avant de décider la consommation du quota.' );
$assert( false !== $included_pos, 'Le marqueur is_included reste pris en charge.' );
$assert( $status_pos < $included_pos, 'Le statut doit primer sur is_included afin qu’un brouillon ne consomme jamais une place.' );
$assert( false !== strpos( $body, "array( 'en_attente', 'valide', 'validee', 'validated' )" ), 'Seuls les statuts soumis/validés reconnus doivent être éligibles au quota.' );
$assert( false === strpos( $body, "'brouillon'" ), 'Le statut brouillon ne doit jamais faire partie des statuts qui consomment le quota.' );

fwrite( STDOUT, "Quota statut brouillon/en attente/valide OK\n" );
