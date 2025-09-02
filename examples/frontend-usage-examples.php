<?php
/**
 * Exemples d'utilisation des nouveaux shortcodes UFSC
 * 
 * Ce fichier montre comment intégrer les shortcodes frontend dans vos pages/posts
 * et comment personnaliser leur comportement via des hooks et filtres.
 */

// Ne pas exécuter directement
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * EXEMPLES D'UTILISATION DES SHORTCODES
 * 
 * Copiez ces exemples dans vos pages WordPress ou templates
 */

// =======================
// 1. TABLEAU DE BORD COMPLET
// =======================

/*
Dans une page WordPress, ajoutez simplement:

[ufsc_club_dashboard]

Ou pour afficher seulement certaines sections:

[ufsc_club_dashboard show_sections="licences,stats"]
*/

// =======================
// 2. SHORTCODES INDIVIDUELS
// =======================

/*
Vous pouvez utiliser chaque section séparément:

[ufsc_club_licences]     // Liste des licences avec filtres
[ufsc_club_stats]        // Statistiques du club
[ufsc_club_profile]      // Profil du club
[ufsc_add_licence]       // Formulaire d'ajout de licence
*/

/**
 * EXEMPLES DE PERSONNALISATION VIA HOOKS
 */

// Personnaliser les champs éditables après validation du club
add_filter( 'ufsc_club_editable_fields_after_validation', function( $fields ) {
    // Ajouter le champ site web comme éditable après validation
    $fields[] = 'site_web';
    
    return $fields;
} );

// Personnaliser les données d'export
add_filter( 'ufsc_export_licence_data', function( $data, $licence_id, $club_id ) {
    // Ajouter des données personnalisées à l'export
    $data['custom_field'] = get_post_meta( $licence_id, 'custom_field', true );
    
    return $data;
}, 10, 3 );

// Personnaliser les notifications email
add_filter( 'ufsc_email_subject', function( $subject, $type, $context ) {
    if ( $type === 'licence_created' ) {
        $subject = '[MON CLUB] ' . $subject;
    }
    
    return $subject;
}, 10, 3 );

// Ajouter des KPI personnalisés aux statistiques
add_filter( 'ufsc_club_stats', function( $stats, $club_id, $season ) {
    // Ajouter un KPI personnalisé
    $stats['custom_metric'] = calculate_custom_metric( $club_id, $season );
    
    return $stats;
}, 10, 3 );

/**
 * EXEMPLES D'INTÉGRATION DANS DES TEMPLATES
 */

// Dans un template de page personnalisé
function render_club_dashboard_page() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Veuillez vous connecter pour accéder au tableau de bord.</p>';
        return;
    }
    
    // Vérifier si l'utilisateur a un club
    $user_id = get_current_user_id();
    $club_id = ufsc_get_user_club_id( $user_id );
    
    if ( ! $club_id ) {
        echo '<p>Aucun club associé à votre compte. Contactez l\'administration.</p>';
        return;
    }
    
    // Afficher le tableau de bord
    echo do_shortcode( '[ufsc_club_dashboard]' );
}

// Dans un widget personnalisé
class UFSC_Club_Stats_Widget extends WP_Widget {
    
    public function widget( $args, $instance ) {
        if ( ! is_user_logged_in() ) {
            return;
        }
        
        echo $args['before_widget'];
        echo $args['before_title'] . 'Statistiques de mon club' . $args['after_title'];
        
        // Afficher seulement les statistiques
        echo do_shortcode( '[ufsc_club_stats]' );
        
        echo $args['after_widget'];
    }
}

/**
 * EXEMPLES D'UTILISATION VIA CODE
 */

// Récupérer des statistiques directement
function get_club_stats_for_display( $club_id ) {
    if ( ! class_exists( 'UFSC_Frontend_Shortcodes' ) ) {
        return array();
    }
    
    // Utiliser la méthode privée via réflexion ou créer une méthode publique
    $season = ufsc_get_woocommerce_settings()['season'];
    $cache_key = "ufsc_stats_{$club_id}_{$season}";
    
    $stats = get_transient( $cache_key );
    if ( false === $stats ) {
        // Calculer les stats...
        $stats = array(
            'total_licences' => 0,
            'paid_licences' => 0,
            'validated_licences' => 0,
            'quota_remaining' => 10
        );
        
        set_transient( $cache_key, $stats, HOUR_IN_SECONDS );
    }
    
    return $stats;
}

// Déclencher un export programmatiquement
function trigger_club_export( $club_id, $format = 'csv' ) {
    if ( ! class_exists( 'UFSC_Import_Export' ) ) {
        return false;
    }
    
    if ( $format === 'csv' ) {
        return UFSC_Import_Export::export_licences_csv( $club_id );
    } elseif ( $format === 'xlsx' ) {
        return UFSC_Import_Export::export_licences_xlsx( $club_id );
    }
    
    return false;
}

// Créer une licence programmatiquement
function create_licence_for_club( $club_id, $licence_data ) {
    // Vérifier les permissions
    if ( ! current_user_can( 'edit_posts' ) ) {
        return new WP_Error( 'permission_denied', 'Permissions insuffisantes' );
    }
    
    // Vérifier le quota
    $quota_info = get_club_quota_info( $club_id );
    
    if ( $quota_info['remaining'] <= 0 ) {
        // Créer une commande de paiement
        $order_id = ufsc_create_additional_license_order( $club_id, array(), get_current_user_id() );
        
        return array(
            'success' => false,
            'payment_required' => true,
            'order_id' => $order_id
        );
    }
    
    // Créer la licence
    $licence_id = create_licence_record( $club_id, $licence_data );
    
    if ( $licence_id ) {
        // Logger l'action
        ufsc_audit_log( 'licence_created_programmatically', array(
            'licence_id' => $licence_id,
            'club_id' => $club_id
        ) );
        
        // Envoyer notification
        do_action( 'ufsc_licence_created', $licence_id, $club_id );
        
        return array(
            'success' => true,
            'licence_id' => $licence_id
        );
    }
    
    return new WP_Error( 'creation_failed', 'Échec de création de la licence' );
}

/**
 * EXEMPLES DE STYLES CSS PERSONNALISÉS
 */

// Ajouter des styles personnalisés
function add_custom_ufsc_styles() {
    if ( ! wp_script_is( 'ufsc-frontend', 'enqueued' ) ) {
        return;
    }
    
    $custom_css = '
        /* Personnaliser les couleurs du tableau de bord */
        .ufsc-dashboard-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
        }
        
        /* Personnaliser les boutons */
        .ufsc-btn-primary {
            background: #059669;
            border-radius: 8px;
        }
        
        .ufsc-btn-primary:hover {
            background: #047857;
        }
        
        /* Personnaliser les KPI */
        .ufsc-kpi-card {
            border-left: 4px solid #3b82f6;
        }
        
        /* Mode sombre (exemple) */
        @media (prefers-color-scheme: dark) {
            .ufsc-club-dashboard {
                background: #1f2937;
                color: #f9fafb;
            }
            
            .ufsc-dashboard-content {
                background: #374151;
                border-color: #4b5563;
            }
        }
    ';
    
    wp_add_inline_style( 'ufsc-frontend', $custom_css );
}
add_action( 'wp_enqueue_scripts', 'add_custom_ufsc_styles' );

/**
 * EXEMPLES D'UTILISATION DE L'API REST
 */

// JavaScript côté frontend pour interagir avec l'API
function add_ufsc_api_examples() {
    if ( ! wp_script_is( 'ufsc-frontend', 'enqueued' ) ) {
        return;
    }
    
    $js_code = "
        // Récupérer les statistiques via API
        function loadClubStats() {
            fetch(ufsc_frontend_vars.rest_url + 'stats', {
                headers: {
                    'X-WP-Nonce': ufsc_frontend_vars.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Stats:', data);
                updateStatsDisplay(data);
            })
            .catch(error => console.error('Erreur:', error));
        }
        
        // Créer une licence via API
        function createLicence(licenceData) {
            fetch(ufsc_frontend_vars.rest_url + 'licences', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': ufsc_frontend_vars.nonce
                },
                body: JSON.stringify(licenceData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.payment_required) {
                    window.location.href = data.payment_url;
                } else {
                    alert('Licence créée avec succès!');
                    location.reload();
                }
            })
            .catch(error => console.error('Erreur:', error));
        }
        
        // Mettre à jour le profil du club
        function updateClubProfile(profileData) {
            fetch(ufsc_frontend_vars.rest_url + 'club', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': ufsc_frontend_vars.nonce
                },
                body: JSON.stringify(profileData)
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            })
            .catch(error => console.error('Erreur:', error));
        }
    ";
    
    wp_add_inline_script( 'ufsc-frontend', $js_code );
}
add_action( 'wp_enqueue_scripts', 'add_ufsc_api_examples' );

/**
 * EXEMPLE DE PAGE COMPLÈTE
 * 
 * Template: page-club-dashboard.php
 */

/*
<?php
get_header();

// Vérifier si l'utilisateur est connecté
if ( ! is_user_logged_in() ) {
    echo '<div class="container">';
    echo '<p>Veuillez vous <a href="' . wp_login_url( get_permalink() ) . '">connecter</a> pour accéder au tableau de bord.</p>';
    echo '</div>';
    get_footer();
    return;
}

// Vérifier si l'utilisateur a un club
$user_id = get_current_user_id();
$club_id = ufsc_get_user_club_id( $user_id );

if ( ! $club_id ) {
    echo '<div class="container">';
    echo '<p>Aucun club associé à votre compte. Contactez l\'administration.</p>';
    echo '</div>';
    get_footer();
    return;
}
?>

<div class="container">
    <h1>Tableau de bord - Mon Club</h1>
    
    <!-- Affichage du tableau de bord complet -->
    <?php echo do_shortcode( '[ufsc_club_dashboard]' ); ?>
    
    <!-- Section supplémentaire personnalisée -->
    <div class="custom-section" style="margin-top: 3rem;">
        <h2>Liens utiles</h2>
        <ul>
            <li><a href="/reglement-ufsc/">Règlement UFSC</a></li>
            <li><a href="/formations/">Formations disponibles</a></li>
            <li><a href="/contact/">Contacter l'administration</a></li>
        </ul>
    </div>
</div>

<?php
get_footer();
?>
*/

/**
 * HELPER FUNCTIONS PERSONNALISÉES
 */

// Fonction helper pour récupérer les informations de club
function get_current_user_club_info() {
    if ( ! is_user_logged_in() ) {
        return null;
    }
    
    $user_id = get_current_user_id();
    $club_id = ufsc_get_user_club_id( $user_id );
    
    if ( ! $club_id ) {
        return null;
    }
    
    // Récupérer les informations du club
    return array(
        'id' => $club_id,
        'name' => get_club_name( $club_id ),
        'is_validated' => ufsc_is_validated_club( $club_id ),
        'quota_info' => get_club_quota_info( $club_id )
    );
}

// Fonction helper pour afficher un message si pas de club
function require_club_access( $message = null ) {
    if ( ! $message ) {
        $message = 'Vous devez être responsable d\'un club pour accéder à cette page.';
    }
    
    $club_info = get_current_user_club_info();
    
    if ( ! $club_info ) {
        echo '<div class="ufsc-message ufsc-error">' . esc_html( $message ) . '</div>';
        return false;
    }
    
    return $club_info;
}

// Exemple d'utilisation dans un template
/*
$club_info = require_club_access();
if ( ! $club_info ) {
    get_footer();
    return;
}

echo do_shortcode( '[ufsc_club_dashboard]' );
*/

?>