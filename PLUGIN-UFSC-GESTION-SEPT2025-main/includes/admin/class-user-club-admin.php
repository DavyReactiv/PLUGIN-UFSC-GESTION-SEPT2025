<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC User Club Admin Interface
 * Handles admin interface for user-club mapping and region assignment
 */
class UFSC_User_Club_Admin {

    /**
     * Initialize admin interface
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'admin_post_ufsc_associate_user_club', array( __CLASS__, 'handle_user_club_association' ) );
        add_action( 'admin_post_ufsc_update_club_region', array( __CLASS__, 'handle_club_region_update' ) );
        add_action( 'wp_ajax_ufsc_search_users', array( __CLASS__, 'ajax_search_users' ) );
        add_action( 'wp_ajax_ufsc_search_clubs', array( __CLASS__, 'ajax_search_clubs' ) );
        add_action('admin_enqueue_scripts', function($hook){
            wp_enqueue_style(
                'ufsc-admin-user-club',
                plugins_url('assets/admin/css/user-club-admin.css', __FILE__),
                [],
                '1.0'
            );
        });
    }

    /**
     * Add admin menu page
     */
    public static function add_admin_menu() {
        add_submenu_page(
            'ufsc-gestion',
            __( 'Associations Utilisateurs', 'ufsc-clubs' ),
            __( 'Associations', 'ufsc-clubs' ),
            'manage_options',
            'ufsc-user-club-mapping',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    /**
     * Render admin page
     */
    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Vous n\'avez pas les permissions pour accéder à cette page.', 'ufsc-clubs' ) );
        }

        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'associations';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Gestion des Associations Utilisateurs-Clubs', 'ufsc-clubs' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=ufsc-user-club-mapping&tab=associations" 
                   class="nav-tab <?php echo $tab === 'associations' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Associations', 'ufsc-clubs' ); ?>
                </a>
                <a href="?page=ufsc-user-club-mapping&tab=regions" 
                   class="nav-tab <?php echo $tab === 'regions' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Régions', 'ufsc-clubs' ); ?>
                </a>
                <a href="?page=ufsc-user-club-mapping&tab=orphans" 
                   class="nav-tab <?php echo $tab === 'orphans' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__( 'Clubs sans responsable', 'ufsc-clubs' ); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ( $tab ) {
                    case 'regions':
                        self::render_regions_tab();
                        break;
                    case 'orphans':
                        self::render_orphans_tab();
                        break;
                    default:
                        self::render_associations_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render associations tab
     */
    private static function render_associations_tab() {
        $managers = UFSC_User_Club_Mapping::get_club_managers();
        ?>
        <div class="ufsc-admin-section">
            <h2><?php echo esc_html__( 'Nouvelle Association', 'ufsc-clubs' ); ?></h2>
            
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" class="ufsc-form-table">
                <?php wp_nonce_field( 'ufsc_associate_user_club', 'ufsc_nonce' ); ?>
                <input type="hidden" name="action" value="ufsc_associate_user_club" />
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="user_search"><?php echo esc_html__( 'Utilisateur', 'ufsc-clubs' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="user_search" class="ufsc-search-input" 
                                   placeholder="<?php echo esc_attr__( 'Rechercher un utilisateur...', 'ufsc-clubs' ); ?>" />
                            <div id="user_results" class="ufsc-results-list"></div>
                            <input type="hidden" id="selected_user_id" name="user_id" />
                            <div id="selected_user_display"></div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="club_search"><?php echo esc_html__( 'Club', 'ufsc-clubs' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="club_search" class="ufsc-search-input" 
                                   placeholder="<?php echo esc_attr__( 'Rechercher un club...', 'ufsc-clubs' ); ?>" />
                            <div id="club_results" class="ufsc-results-list"></div>
                            <input type="hidden" id="selected_club_id" name="club_id" />
                            <div id="selected_club_display"></div>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button-primary" 
                           value="<?php echo esc_attr__( 'Associer Utilisateur et Club', 'ufsc-clubs' ); ?>" />
                </p>
            </form>
        </div>

        <div class="ufsc-admin-section">
            <h2><?php echo esc_html__( 'Associations Existantes', 'ufsc-clubs' ); ?></h2>
            
            <table class="ufsc-associations-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Utilisateur', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Email', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Club', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Région', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Actions', 'ufsc-clubs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $managers ) ): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">
                                <?php echo esc_html__( 'Aucune association trouvée.', 'ufsc-clubs' ); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $managers as $manager ): ?>
                            <tr>
                                <td><strong><?php echo esc_html( $manager['display_name'] ); ?></strong><br>
                                    <small><?php echo esc_html( $manager['user_login'] ); ?></small></td>
                                <td><?php echo esc_html( $manager['user_email'] ); ?></td>
                                <td><?php echo esc_html( $manager['club_name'] ); ?></td>
                                <td><?php echo UFSC_Badge_Helper::render_region_badge( $manager['region'] ); ?></td>
                                <td>
                                    <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=ufsc_remove_user_club&user_id=' . $manager['user_id'] ), 'ufsc_remove_user_club' ); ?>" 
                                       class="button button-small" 
                                       onclick="return confirm('<?php echo esc_js( __( 'Êtes-vous sûr de vouloir supprimer cette association ?', 'ufsc-clubs' ) ); ?>')">
                                        <?php echo esc_html__( 'Supprimer', 'ufsc-clubs' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // User search functionality
            $('#user_search').on('input', function() {
                var search = $(this).val();
                if (search.length >= 2) {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'ufsc_search_users',
                            search: search,
                            nonce: '<?php echo wp_create_nonce( 'ufsc_search_users' ); ?>'
                        },
                        success: function(response) {
                            var results = $('#user_results');
                            results.empty();
                            if (response.data && response.data.length > 0) {
                                $.each(response.data, function(i, user) {
                                    results.append('<div class="ufsc-result-item" data-id="' + user.id + '">' + 
                                                 user.display_name + ' (' + user.user_email + ')</div>');
                                });
                                results.show();
                            } else {
                                results.hide();
                            }
                        }
                    });
                } else {
                    $('#user_results').hide();
                }
            });

            // Club search functionality
            $('#club_search').on('input', function() {
                var search = $(this).val();
                if (search.length >= 2) {
                    $.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'ufsc_search_clubs',
                            search: search,
                            nonce: '<?php echo wp_create_nonce( 'ufsc_search_clubs' ); ?>'
                        },
                        success: function(response) {
                            var results = $('#club_results');
                            results.empty();
                            if (response.data && response.data.length > 0) {
                                $.each(response.data, function(i, club) {
                                    results.append('<div class="ufsc-result-item" data-id="' + club.id + '">' + 
                                                 club.nom + ' (' + club.region + ')</div>');
                                });
                                results.show();
                            } else {
                                results.hide();
                            }
                        }
                    });
                } else {
                    $('#club_results').hide();
                }
            });

            // Handle result selection
            $(document).on('click', '.ufsc-result-item', function() {
                var id = $(this).data('id');
                var text = $(this).text();
                var parent = $(this).parent();
                
                if (parent.attr('id') === 'user_results') {
                    $('#selected_user_id').val(id);
                    $('#selected_user_display').html('<strong>Sélectionné:</strong> ' + text);
                    $('#user_search').val('');
                } else if (parent.attr('id') === 'club_results') {
                    $('#selected_club_id').val(id);
                    $('#selected_club_display').html('<strong>Sélectionné:</strong> ' + text);
                    $('#club_search').val('');
                }
                
                parent.hide();
            });

            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.ufsc-search-input, .ufsc-results-list').length) {
                    $('.ufsc-results-list').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render regions tab
     */
    private static function render_regions_tab() {
        $managers = UFSC_User_Club_Mapping::get_club_managers();
        $regions = ufsc_get_regions_list();
        ?>
        <div class="ufsc-admin-section">
            <h2><?php echo esc_html__( 'Gestion des Régions', 'ufsc-clubs' ); ?></h2>
            
            <table class="ufsc-associations-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Club', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Région Actuelle', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Nouvelle Région', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Actions', 'ufsc-clubs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $managers as $manager ): ?>
                        <tr>
                            <td><?php echo esc_html( $manager['club_name'] ); ?></td>
                            <td><?php echo UFSC_Badge_Helper::render_region_badge( $manager['region'] ); ?></td>
                            <td>
                                <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline;">
                                    <?php wp_nonce_field( 'ufsc_update_club_region', 'ufsc_nonce' ); ?>
                                    <input type="hidden" name="action" value="ufsc_update_club_region" />
                                    <input type="hidden" name="club_id" value="<?php echo esc_attr( $manager['club_id'] ); ?>" />
                                    
                                    <select name="region" style="margin-right: 10px;">
                                        <?php foreach ( $regions as $region ): ?>
                                            <option value="<?php echo esc_attr( $region ); ?>" 
                                                    <?php selected( $manager['region'], $region ); ?>>
                                                <?php echo esc_html( $region ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                    <input type="submit" class="button button-small" 
                                           value="<?php echo esc_attr__( 'Mettre à jour', 'ufsc-clubs' ); ?>" />
                                </form>
                            </td>
                            <td>
                                <small><?php echo esc_html__( 'Responsable:', 'ufsc-clubs' ); ?> 
                                <?php echo esc_html( $manager['display_name'] ); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render orphans tab
     */
    private static function render_orphans_tab() {
        $orphan_clubs = UFSC_User_Club_Mapping::get_clubs_without_managers();
        ?>
        <div class="ufsc-admin-section">
            <h2><?php echo esc_html__( 'Clubs sans Responsable', 'ufsc-clubs' ); ?></h2>
            <p><?php echo esc_html__( 'Ces clubs n\'ont pas de responsable associé. Vous pouvez leur en attribuer un depuis l\'onglet Associations.', 'ufsc-clubs' ); ?></p>
            
            <table class="ufsc-associations-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__( 'Club', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Région', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Email', 'ufsc-clubs' ); ?></th>
                        <th><?php echo esc_html__( 'Actions', 'ufsc-clubs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $orphan_clubs ) ): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #666;">
                                <?php echo esc_html__( 'Tous les clubs ont un responsable associé.', 'ufsc-clubs' ); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $orphan_clubs as $club ): ?>
                            <tr>
                                <td><?php echo esc_html( $club->nom ); ?></td>
                                <td><?php echo UFSC_Badge_Helper::render_region_badge( $club->region ); ?></td>
                                <td><?php echo esc_html( $club->email ); ?></td>
                                <td>
                                    <a href="?page=ufsc-user-club-mapping&tab=associations&club_id=<?php echo esc_attr( $club->id ); ?>" 
                                       class="button button-small">
                                        <?php echo esc_html__( 'Attribuer un responsable', 'ufsc-clubs' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle user-club association
     */
    public static function handle_user_club_association() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_associate_user_club', 'ufsc_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Sécurité échouée', 'ufsc-clubs' ) );
        }

        $user_id = (int) $_POST['user_id'];
        $club_id = (int) $_POST['club_id'];

        if ( $user_id && $club_id ) {
            $success = UFSC_User_Club_Mapping::associate_user_with_club( $user_id, $club_id );
            
            if ( $success ) {
                wp_redirect( add_query_arg( 'message', 'associated', admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
            } else {
                wp_redirect( add_query_arg( 'message', 'error', admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
            }
        } else {
            wp_redirect( add_query_arg( 'message', 'missing_data', admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
        }
        
        exit;
    }

    /**
     * Handle club region update
     */
    public static function handle_club_region_update() {
        if ( ! current_user_can( 'read' ) ) {
            wp_die( __( 'Accès refusé.', 'ufsc-clubs' ) );
        }

        check_admin_referer( 'ufsc_update_club_region', 'ufsc_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Sécurité échouée', 'ufsc-clubs' ) );
        }

        $club_id = (int) $_POST['club_id'];
        $region = sanitize_text_field( $_POST['region'] );

        if ( $club_id && $region ) {
            $success = UFSC_User_Club_Mapping::update_club_region( $club_id, $region );
            
            if ( $success ) {
                wp_redirect( add_query_arg( array( 'tab' => 'regions', 'message' => 'region_updated' ), admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
            } else {
                wp_redirect( add_query_arg( array( 'tab' => 'regions', 'message' => 'error' ), admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
            }
        } else {
            wp_redirect( add_query_arg( array( 'tab' => 'regions', 'message' => 'missing_data' ), admin_url( 'admin.php?page=ufsc-user-club-mapping' ) ) );
        }
        
        exit;
    }

    /**
     * AJAX search users
     */
    public static function ajax_search_users() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'ufsc_search_users' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        $search = sanitize_text_field( $_REQUEST['search'] );
        
        $users = get_users( array(
            'search' => "*{$search}*",
            'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
            'number' => 10
        ) );

        $results = array();
        foreach ( $users as $user ) {
            // Don't include users who already have clubs
            if ( ! ufsc_get_user_club_id( $user->ID ) ) {
                $results[] = array(
                    'id' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'display_name' => $user->display_name
                );
            }
        }

        wp_send_json_success( $results );
    }

    /**
     * AJAX search clubs
     */
    public static function ajax_search_clubs() {
        if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'ufsc_search_clubs' ) || ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }

        $search = sanitize_text_field( $_REQUEST['search'] );
        
        global $wpdb;
        $settings = UFSC_SQL::get_settings();
        $clubs_table = $settings['table_clubs'];

        $clubs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, nom, region FROM {$clubs_table} 
             WHERE (responsable_id IS NULL OR responsable_id = 0) 
             AND nom LIKE %s 
             ORDER BY nom LIMIT 10",
            '%' . $wpdb->esc_like( $search ) . '%'
        ) );

        $results = array();
        foreach ( $clubs as $club ) {
            $results[] = array(
                'id' => $club->id,
                'nom' => $club->nom,
                'region' => $club->region
            );
        }

        wp_send_json_success( $results );
    }
}

// Initialize admin interface
add_action( 'init', array( 'UFSC_User_Club_Admin', 'init' ) );