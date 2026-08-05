<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_Diagnostics_Admin {
    public static function register_menu() {
        add_submenu_page(
            'ufsc-dashboard',
            __( 'Diagnostic stockage UFSC', 'ufsc-clubs' ),
            __( 'Diagnostic stockage', 'ufsc-clubs' ),
            'manage_options',
            'ufsc-diagnostics',
            array( __CLASS__, 'render' )
        );
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) ); }
        $diagnostic = function_exists( 'ufsc_get_configuration_diagnostic' ) ? ufsc_get_configuration_diagnostic() : array();
        echo '<div class="wrap"><h1>' . esc_html__( 'Diagnostic stockage UFSC', 'ufsc-clubs' ) . '</h1>';
        echo '<p>' . esc_html__( 'Diagnostic en lecture seule : aucune donnée club, licence, utilisateur ou affiliation n’est modifiée par cette page.', 'ufsc-clubs' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'Mode de stockage', 'ufsc-clubs' ) . ':</strong> ' . esc_html( $diagnostic['schema_mode'] ?? 'unknown' ) . '</p>';
        echo '<p><strong>' . esc_html__( 'État', 'ufsc-clubs' ) . ':</strong> ' . esc_html( $diagnostic['message'] ?? '' ) . '</p>';

        echo '<h2>' . esc_html__( 'Tables attendues / trouvées', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Type', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Table trouvée', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Compatibilité', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Lignes', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Source', 'ufsc-clubs' ) . '</th><th>' . esc_html__( 'Action recommandée', 'ufsc-clubs' ) . '</th></tr></thead><tbody>';
        foreach ( (array) ( $diagnostic['diagnostic_details'] ?? array() ) as $type => $info ) {
            $exists = ! empty( $info['exists'] );
            echo '<tr><td>' . esc_html( $type ) . '</td><td><code>' . esc_html( $info['table'] ?? '' ) . '</code></td><td>' . esc_html( $info['compatibility'] ?? ( $exists ? 'present' : 'missing' ) ) . '</td><td>' . esc_html( (string) ( $info['rows'] ?? 0 ) ) . '</td><td>' . esc_html( $info['source'] ?? '' ) . '</td><td>' . esc_html( $exists ? __( 'Exploiter en lecture / migration additive optionnelle', 'ufsc-clubs' ) : __( 'Ne pas bloquer si optionnelle ; analyser avant migration', 'ufsc-clubs' ) ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Inventaire réel des tables UFSC', 'ufsc-clubs' ) . '</h2>';
        echo '<table class="widefat striped"><thead><tr><th>Table</th><th>Moteur</th><th>Lignes</th><th>PK</th><th>Uniques</th><th>club_id</th><th>user_id</th><th>Saison</th><th>Statut</th><th>Suppression</th></tr></thead><tbody>';
        foreach ( (array) ( $diagnostic['inventory'] ?? array() ) as $row ) {
            echo '<tr><td><code>' . esc_html( $row['table'] ?? '' ) . '</code></td><td>' . esc_html( $row['engine'] ?? '' ) . '</td><td>' . esc_html( (string) ( $row['rows'] ?? 0 ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['primary_key'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', array_keys( (array) ( $row['unique_indexes'] ?? array() ) ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['club_columns'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['user_columns'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['season_columns'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['status_columns'] ?? array() ) ) ) . '</td><td>' . esc_html( implode( ', ', (array) ( $row['deleted_columns'] ?? array() ) ) ) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<h2>' . esc_html__( 'Actions sécurisées', 'ufsc-clubs' ) . '</h2>';
        echo '<p><button class="button" disabled>' . esc_html__( 'Simuler la migration (à implémenter après recette)', 'ufsc-clubs' ) . '</button> ';
        echo '<button class="button" disabled>' . esc_html__( 'Exécuter la migration (désactivé)', 'ufsc-clubs' ) . '</button> ';
        echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=ufsc-dashboard&ufsc_refresh_cache=1' ), 'ufsc_refresh_cache' ) ) . '">' . esc_html__( 'Purger les caches UFSC', 'ufsc-clubs' ) . '</a></p>';
        echo '</div>';
    }
}
