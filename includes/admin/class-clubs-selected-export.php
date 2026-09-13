<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Export CSV/XLSX limité aux clubs cochés dans la liste admin. */
final class UFSC_Clubs_Selected_Export {
    const NONCE_ACTION = 'ufsc_selected_clubs_export';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'maybe_export' ), 1 );
        add_action( 'admin_footer', array( __CLASS__, 'render_selection_script' ), 50 );
    }

    private static function can_manage() {
        return class_exists( 'UFSC_Permissions' )
            ? current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE )
            : current_user_can( 'manage_options' );
    }

    public static function render_selection_script() {
        if ( ! is_admin() || ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $page, array( 'ufsc-sql-clubs', 'ufsc-clubs', 'ufsc-gestion-clubs' ), true ) ) { return; }
        $nonce = wp_create_nonce( self::NONCE_ACTION );
        ?>
        <script>
        (function(){
            function selectedIds(){
                return Array.prototype.slice.call(document.querySelectorAll('input[name="club_ids[]"]:checked'))
                    .map(function(el){return parseInt(el.value,10)||0;}).filter(function(v){return v>0;});
            }
            document.addEventListener('click',function(e){
                var link=e.target.closest('a[href*="export=csv"],a[href*="export=xlsx"]');
                if(!link)return;
                var ids=selectedIds();
                e.preventDefault();
                if(!ids.length){window.alert('Sélectionnez au moins un club avant de lancer l’export.');return;}
                var u=new URL(link.href,window.location.href);
                u.searchParams.set('club_ids',ids.join(','));
                u.searchParams.set('_ufsc_selected_export_nonce','<?php echo esc_js( $nonce ); ?>');
                window.location.href=u.toString();
            });
        })();
        </script>
        <?php
    }

    public static function maybe_export() {
        if ( ! self::can_manage() ) { return; }
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $format = isset( $_GET['export'] ) ? sanitize_key( wp_unslash( $_GET['export'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $raw_ids = isset( $_GET['club_ids'] ) ? sanitize_text_field( wp_unslash( $_GET['club_ids'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! in_array( $page, array( 'ufsc-sql-clubs', 'ufsc-clubs', 'ufsc-gestion-clubs' ), true ) || ! in_array( $format, array( 'csv', 'xlsx' ), true ) || '' === $raw_ids ) { return; }

        $nonce = isset( $_GET['_ufsc_selected_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_ufsc_selected_export_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            wp_die( esc_html__( 'Lien d’export invalide ou expiré.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }

        $ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) ) );
        if ( ! $ids ) { return; }
        $rows = self::get_rows( $ids );
        if ( ! $rows ) {
            wp_die( esc_html__( 'Aucun club autorisé dans la sélection.', 'ufsc-clubs' ), '', array( 'response' => 404 ) );
        }

        'xlsx' === $format ? self::send_xlsx( $rows ) : self::send_csv( $rows );
        exit;
    }

    private static function get_rows( array $ids ) {
        global $wpdb;
        if ( ! class_exists( 'UFSC_SQL' ) ) { return array(); }
        $settings = UFSC_SQL::get_settings();
        $table = $settings['table_clubs'];
        $id_sql = implode( ',', array_map( 'absint', $ids ) );
        if ( '' === $id_sql ) { return array(); }

        $where = "id IN ({$id_sql})";
        if ( function_exists( 'ufsc_user_has_all_regions_access' ) && ! ufsc_user_has_all_regions_access() && function_exists( 'ufsc_current_user_allowed_regions' ) ) {
            $regions = array_values( array_filter( array_map( 'sanitize_text_field', (array) ufsc_current_user_allowed_regions() ) ) );
            if ( ! $regions ) { return array(); }
            $placeholders = implode( ',', array_fill( 0, count( $regions ), '%s' ) );
            $region_sql = $wpdb->prepare( "region IN ({$placeholders})", $regions );
            $where .= ' AND ' . $region_sql;
        }

        $rows = (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE {$where}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $by_id = array();
        foreach ( $rows as $row ) { $by_id[ (int) $row->id ] = $row; }
        $ordered = array();
        foreach ( $ids as $id ) { if ( isset( $by_id[ $id ] ) ) { $ordered[] = $by_id[ $id ]; } }
        return $ordered;
    }

    private static function columns() {
        return array(
            'id' => 'ID', 'nom' => 'Nom du club', 'region' => 'Région', 'adresse' => 'Adresse',
            'code_postal' => 'Code postal', 'ville' => 'Ville', 'email' => 'E-mail', 'telephone' => 'Téléphone',
            'num_affiliation' => 'N° affiliation', 'numero_affiliation_ufsc' => 'N° affiliation UFSC',
            'numero_affiliation_ffst' => 'N° affiliation FFST', 'statut' => 'Statut', 'date_creation' => 'Créé le',
        );
    }

    private static function row_values( $row ) {
        $values = array();
        foreach ( self::columns() as $key => $label ) { $values[] = isset( $row->{$key} ) ? (string) $row->{$key} : ''; }
        return $values;
    }

    private static function send_csv( array $rows ) {
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="clubs-ufsc-selection-' . gmdate( 'Ymd-His' ) . '.csv"' );
        $out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
        fputcsv( $out, array_values( self::columns() ), ';' );
        foreach ( $rows as $row ) { fputcsv( $out, self::row_values( $row ), ';' ); }
        fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
    }

    private static function send_xlsx( array $rows ) {
        if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Spreadsheet' ) || ! class_exists( '\PhpOffice\PhpSpreadsheet\Writer\Xlsx' ) ) {
            wp_die( esc_html__( 'Le moteur XLSX n’est pas disponible sur ce serveur.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }
        $sheetbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $sheetbook->getActiveSheet();
        $sheet->setTitle( 'Clubs sélectionnés' );
        $sheet->fromArray( array_values( self::columns() ), null, 'A1' );
        $line = 2;
        foreach ( $rows as $row ) { $sheet->fromArray( self::row_values( $row ), null, 'A' . $line++ ); }
        foreach ( range( 'A', 'M' ) as $column ) { $sheet->getColumnDimension( $column )->setAutoSize( true ); }
        nocache_headers();
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="clubs-ufsc-selection-' . gmdate( 'Ymd-His' ) . '.xlsx"' );
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $sheetbook );
        $writer->save( 'php://output' );
        $sheetbook->disconnectWorksheets();
    }
}
