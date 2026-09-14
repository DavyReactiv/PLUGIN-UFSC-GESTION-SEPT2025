<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pont de compatibilité : l'ancienne action d'affiliation ne doit plus jamais
 * produire le document HTML simplifié. Tous les anciens boutons / liens passent
 * désormais par le générateur du modèle DOCX officiel.
 *
 * Aucun stockage métier n'est modifié.
 */
final class UFSC_FFST_Affiliation_Action_Bridge {
    const LEGACY_ACTION = 'ufsc_ffst_generate_affiliation';

    public static function init() {
        // Le module historique est déjà chargé à ce stade. On remplace uniquement
        // son callback d'affiliation, le bordereau licences reste inchangé.
        remove_action( 'admin_post_' . self::LEGACY_ACTION, array( 'UFSC_FFST_Export_Admin', 'handle_generate_affiliation' ) );
        add_action( 'admin_post_' . self::LEGACY_ACTION, array( __CLASS__, 'handle_legacy_affiliation' ), 1 );
    }

    public static function handle_legacy_affiliation() {
        if ( ! class_exists( 'UFSC_Permissions' ) || ! current_user_can( UFSC_Permissions::CAP_GESTION_MANAGE ) ) {
            wp_die( esc_html__( 'Action non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 403 ) );
        }
        if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '' ) ) {
            wp_die( esc_html__( 'Méthode non autorisée.', 'ufsc-clubs' ), '', array( 'response' => 405 ) );
        }

        $club_id = isset( $_POST['club_id'] ) ? absint( wp_unslash( $_POST['club_id'] ) ) : 0;
        $season  = isset( $_POST['season'] ) && ! is_array( $_POST['season'] ) ? sanitize_text_field( wp_unslash( $_POST['season'] ) ) : '';
        if ( ! $club_id || ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
            wp_die( esc_html__( 'Club ou saison FFST invalide.', 'ufsc-clubs' ), '', array( 'response' => 400 ) );
        }

        // Valide le nonce du bouton historique avant tout pont.
        check_admin_referer( self::LEGACY_ACTION . '_' . $club_id . '_' . $season );

        if ( ! class_exists( 'UFSC_FFST_Official_Template_Admin' ) ) {
            wp_die( esc_html__( 'Le générateur du modèle officiel FFST est indisponible.', 'ufsc-clubs' ), '', array( 'response' => 500 ) );
        }

        // Le générateur officiel conserve sa propre validation. Après validation
        // du nonce historique, on crée un nonce interne pour l'action officielle.
        // check_admin_referer() consulte $_REQUEST : il faut donc synchroniser
        // explicitement $_POST ET $_REQUEST dans cette même requête.
        $official_action = UFSC_FFST_Official_Template_Admin::AFFILIATION_ACTION;
        $official_nonce  = wp_create_nonce( $official_action . '_' . $club_id . '_' . $season );

        $_POST['action']      = $official_action;
        $_POST['_wpnonce']    = $official_nonce;
        $_REQUEST['action']   = $official_action;
        $_REQUEST['_wpnonce'] = $official_nonce;

        UFSC_FFST_Official_Template_Admin::handle_affiliation();
        exit;
    }
}

UFSC_FFST_Affiliation_Action_Bridge::init();
