<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Register the UFSC honorability review queue under UFSC Gestion. */
function ufsc_register_honorability_admin_page() {
    $cap = class_exists( 'UFSC_Permissions' ) ? UFSC_Permissions::CAP_LICENCES_MANAGE : 'manage_options';
    add_submenu_page(
        'ufsc-dashboard',
        __( 'Honorabilité', 'ufsc-clubs' ),
        __( 'Honorabilité', 'ufsc-clubs' ),
        $cap,
        'ufsc-honorability',
        'ufsc_render_honorability_admin_page'
    );
}
add_action( 'admin_menu', 'ufsc_register_honorability_admin_page', 30 );

/** Read all canonical honorability option records without mutating them. */
function ufsc_get_honorability_admin_all_records() {
    global $wpdb;
    $prefix = $wpdb->esc_like( 'ufsc_honorability_attestation_' ) . '%';
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) );
    $records = array();

    foreach ( (array) $rows as $row ) {
        $record = maybe_unserialize( $row->option_value );
        if ( ! is_array( $record ) || empty( $record['licence_id'] ) || empty( $record['attachment_id'] ) ) {
            continue;
        }
        $records[] = $record;
    }

    usort(
        $records,
        static function( $a, $b ) {
            return strcmp( (string) ( $b['uploaded_at'] ?? '' ), (string) ( $a['uploaded_at'] ?? '' ) );
        }
    );
    return $records;
}

/** Read season-scoped honorability records from the canonical options. */
function ufsc_get_honorability_admin_records( $season = '', $status = '' ) {
    $season = $season ?: ( function_exists( 'ufsc_get_honorability_current_season' ) ? ufsc_get_honorability_current_season() : '' );
    $status = sanitize_key( (string) $status );
    $records = array();

    foreach ( ufsc_get_honorability_admin_all_records() as $record ) {
        if ( $season && (string) ( $record['season'] ?? '' ) !== (string) $season ) {
            continue;
        }
        if ( $status && (string) ( $record['status'] ?? '' ) !== $status ) {
            continue;
        }
        $records[] = $record;
    }
    return $records;
}

/** Resolve licence + club presentation without changing any canonical storage. */
function ufsc_get_honorability_admin_context( $record ) {
    global $wpdb;
    $context = array( 'person' => '', 'club' => '', 'role' => (string) ( $record['role'] ?? '' ) );
    if ( ! class_exists( 'UFSC_SQL' ) ) { return $context; }

    $settings = UFSC_SQL::get_settings();
    $licences = $settings['table_licences'] ?? '';
    $clubs    = $settings['table_clubs'] ?? '';

    if ( $licences ) {
        $lic = $wpdb->get_row( $wpdb->prepare( "SELECT nom, prenom, role, club_id FROM `{$licences}` WHERE id = %d LIMIT 1", absint( $record['licence_id'] ?? 0 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $lic ) {
            $context['person'] = trim( (string) $lic->prenom . ' ' . (string) $lic->nom );
            $context['role']   = (string) $lic->role;
            if ( empty( $record['club_id'] ) ) {
                $record['club_id'] = absint( $lic->club_id );
            }
        }
    }

    if ( $clubs && ! empty( $record['club_id'] ) ) {
        $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs}` WHERE id = %d LIMIT 1", absint( $record['club_id'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if ( $club && function_exists( 'ufsc_get_club_profile_value' ) ) {
            $context['club'] = (string) ufsc_get_club_profile_value( $club, 'name' );
        }
    }
    return $context;
}

/** Build the season filter from stored honorability records and canonical UFSC seasons. */
function ufsc_get_honorability_admin_seasons( $selected = '' ) {
    $seasons = array();
    $current = function_exists( 'ufsc_get_honorability_current_season' )
        ? (string) ufsc_get_honorability_current_season()
        : ( class_exists( 'UFSC_Season_Service' ) ? (string) UFSC_Season_Service::get_current_season() : '' );

    if ( $current ) {
        $seasons[] = $current;
    }
    if ( class_exists( 'UFSC_Season_Service' ) ) {
        $seasons = array_merge(
            $seasons,
            (array) UFSC_Season_Service::get_available_seasons(),
            array( UFSC_Season_Service::get_previous_season() )
        );
    }
    foreach ( ufsc_get_honorability_admin_all_records() as $record ) {
        if ( ! empty( $record['season'] ) ) {
            $seasons[] = (string) $record['season'];
        }
    }
    if ( $selected ) {
        $seasons[] = (string) $selected;
    }

    $seasons = array_values(
        array_unique(
            array_filter(
                array_map(
                    static function( $season ) {
                        if ( class_exists( 'UFSC_Season_Service' ) ) {
                            return (string) UFSC_Season_Service::normalize_season( $season );
                        }
                        $season = trim( str_replace( '/', '-', (string) $season ) );
                        return preg_match( '/^\d{4}-\d{4}$/', $season ) ? $season : '';
                    },
                    $seasons
                )
            )
        )
    );
    rsort( $seasons, SORT_STRING );
    return $seasons;
}

/** Normalize text for the read-only honorability search. */
function ufsc_honorability_admin_search_text( $value ) {
    return strtolower( remove_accents( trim( (string) $value ) ) );
}

function ufsc_render_honorability_admin_page() {
    $cap = class_exists( 'UFSC_Permissions' ) ? UFSC_Permissions::CAP_LICENCES_MANAGE : 'manage_options';
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( $cap ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) );
    }

    $default_season = function_exists( 'ufsc_get_honorability_current_season' ) ? ufsc_get_honorability_current_season() : '';
    $season = isset( $_GET['season'] ) && ! is_array( $_GET['season'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtres lecture seule.
        ? sanitize_text_field( wp_unslash( $_GET['season'] ) )
        : $default_season;
    if ( class_exists( 'UFSC_Season_Service' ) ) {
        $season = UFSC_Season_Service::normalize_season( $season ) ?: $default_season;
    }

    $filter = isset( $_GET['document_status'] ) && ! is_array( $_GET['document_status'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtres lecture seule.
        ? sanitize_key( wp_unslash( $_GET['document_status'] ) )
        : 'pending';
    $search = isset( $_GET['s'] ) && ! is_array( $_GET['s'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtres lecture seule.
        ? sanitize_text_field( wp_unslash( $_GET['s'] ) )
        : '';
    $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination lecture seule.
    $per_page = isset( $_GET['per_page'] ) ? absint( wp_unslash( $_GET['per_page'] ) ) : 20; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination lecture seule.
    if ( ! in_array( $per_page, array( 10, 20, 50, 100 ), true ) ) {
        $per_page = 20;
    }

    $statuses = array(
        'pending' => 'À vérifier',
        'validated' => 'Validées',
        'correction_required' => 'À corriger',
        'rejected' => 'Refusées',
        'expired' => 'À renouveler',
        'all' => 'Toutes',
    );
    if ( ! isset( $statuses[ $filter ] ) ) {
        $filter = 'pending';
    }

    $records = ufsc_get_honorability_admin_records( $season, 'all' === $filter ? '' : $filter );
    $rows = array();
    $needle = ufsc_honorability_admin_search_text( $search );

    foreach ( $records as $record ) {
        $ctx = ufsc_get_honorability_admin_context( $record );
        if ( '' !== $needle ) {
            $haystack = implode(
                ' ',
                array(
                    $ctx['person'] ?? '',
                    $ctx['club'] ?? '',
                    $ctx['role'] ?? '',
                    $record['season'] ?? '',
                    $record['licence_id'] ?? '',
                    $record['club_id'] ?? '',
                )
            );
            if ( false === strpos( ufsc_honorability_admin_search_text( $haystack ), $needle ) ) {
                continue;
            }
        }
        $rows[] = array( 'record' => $record, 'context' => $ctx );
    }

    $total = count( $rows );
    $pages = max( 1, (int) ceil( $total / $per_page ) );
    if ( $paged > $pages ) {
        $paged = $pages;
    }
    $visible_rows = array_slice( $rows, ( $paged - 1 ) * $per_page, $per_page );
    $seasons = ufsc_get_honorability_admin_seasons( $season );
    ?>
    <div class="wrap ufsc-honorability-admin">
        <?php if ( class_exists( 'UFSC_SQL_Admin' ) ) { UFSC_SQL_Admin::render_admin_quick_nav(); } ?>
        <h1><?php esc_html_e( 'Contrôle des attestations d’honorabilité', 'ufsc-clubs' ); ?></h1>
        <p><?php esc_html_e( 'Chaque document déposé par un club reste en attente jusqu’à une décision explicite de l’UFSC.', 'ufsc-clubs' ); ?></p>

        <form method="get" class="ufsc-honorability-admin-filters">
            <input type="hidden" name="page" value="ufsc-honorability">
            <label>
                <strong><?php esc_html_e( 'Recherche', 'ufsc-clubs' ); ?></strong>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Nom, club, rôle ou ID…', 'ufsc-clubs' ); ?>">
            </label>
            <label>
                <strong><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></strong>
                <select name="season">
                    <?php foreach ( $seasons as $row_season ) : ?>
                        <option value="<?php echo esc_attr( $row_season ); ?>" <?php selected( $season, $row_season ); ?>><?php echo esc_html( $row_season ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <strong><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></strong>
                <select name="document_status">
                    <?php foreach ( $statuses as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <strong><?php esc_html_e( 'Par page', 'ufsc-clubs' ); ?></strong>
                <select name="per_page">
                    <?php foreach ( array( 10, 20, 50, 100 ) as $size ) : ?>
                        <option value="<?php echo esc_attr( $size ); ?>" <?php selected( $per_page, $size ); ?>><?php echo esc_html( $size ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="button button-primary"><?php esc_html_e( 'Filtrer', 'ufsc-clubs' ); ?></button>
            <a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'ufsc-honorability', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Réinitialiser', 'ufsc-clubs' ); ?></a>
        </form>

        <p><strong><?php echo esc_html( sprintf( _n( '%d attestation trouvée', '%d attestations trouvées', $total, 'ufsc-clubs' ), $total ) ); ?></strong></p>

        <?php if ( empty( $visible_rows ) ) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e( 'Aucune attestation dans ce filtre.', 'ufsc-clubs' ); ?></p></div>
        <?php else : ?>
            <div class="ufsc-honorability-admin-list">
            <?php foreach ( $visible_rows as $row ) :
                $record = $row['record'];
                $ctx = $row['context'];
                $licence_id = absint( $record['licence_id'] ?? 0 );
                $attachment_id = absint( $record['attachment_id'] ?? 0 );
                $file_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
                $meta = function_exists( 'ufsc_get_honorability_status_meta' )
                    ? ufsc_get_honorability_status_meta( $record['status'] ?? 'pending' )
                    : array( 'label' => $record['status'] ?? 'pending' );
            ?>
                <section class="ufsc-honorability-admin-card">
                    <div class="ufsc-honorability-admin-card__head">
                        <div>
                            <h2><?php echo esc_html( $ctx['person'] ?: sprintf( 'Licence #%d', $licence_id ) ); ?></h2>
                            <p><?php echo esc_html( ( $ctx['club'] ?: 'Club #' . absint( $record['club_id'] ?? 0 ) ) . ' — ' . ( function_exists( 'ufsc_get_honorability_role_label' ) ? ufsc_get_honorability_role_label( $ctx['role'] ) : $ctx['role'] ) . ' — ' . ( $record['season'] ?? '' ) ); ?></p>
                        </div>
                        <span class="ufsc-honorability-admin-status"><?php echo esc_html( $meta['label'] ?? '' ); ?></span>
                    </div>
                    <p><?php echo esc_html( sprintf( 'Déposé le %s', $record['uploaded_at'] ?? '—' ) ); ?></p>
                    <?php if ( $file_url ) : ?>
                        <p><a class="button" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir le document', 'ufsc-clubs' ); ?></a></p>
                    <?php endif; ?>

                    <form class="ufsc-honorability-admin-decision" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ufsc_decide_honorability_attestation">
                        <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence_id ); ?>">
                        <input type="hidden" name="season" value="<?php echo esc_attr( $record['season'] ?? $season ); ?>">
                        <?php wp_nonce_field( 'ufsc_decide_honorability_' . $licence_id ); ?>
                        <label class="ufsc-honorability-admin-reason">
                            <strong><?php esc_html_e( 'Motif / commentaire', 'ufsc-clubs' ); ?></strong>
                            <textarea name="reason" rows="3" placeholder="<?php esc_attr_e( 'Obligatoire en cas de correction ou de refus.', 'ufsc-clubs' ); ?>"><?php echo esc_textarea( $record['reason'] ?? '' ); ?></textarea>
                        </label>
                        <div class="ufsc-honorability-admin-actions">
                            <button class="button button-primary" type="submit" name="document_status" value="validated"><?php esc_html_e( '✓ Valider', 'ufsc-clubs' ); ?></button>
                            <button class="button" type="submit" name="document_status" value="correction_required" data-require-reason="1"><?php esc_html_e( 'Demander une correction', 'ufsc-clubs' ); ?></button>
                            <button class="button button-link-delete" type="submit" name="document_status" value="rejected" data-require-reason="1"><?php esc_html_e( 'Refuser', 'ufsc-clubs' ); ?></button>
                        </div>
                    </form>
                </section>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        if ( $pages > 1 ) {
            $base_url = remove_query_arg( 'paged' );
            $links = paginate_links(
                array(
                    'base' => add_query_arg( 'paged', '%#%', $base_url ),
                    'format' => '',
                    'current' => $paged,
                    'total' => $pages,
                    'type' => 'array',
                    'prev_text' => '‹',
                    'next_text' => '›',
                )
            );
            if ( $links ) {
                echo '<nav class="ufsc-honorability-admin-pagination" aria-label="' . esc_attr__( 'Pagination des attestations', 'ufsc-clubs' ) . '">';
                foreach ( $links as $link ) {
                    echo wp_kses_post( $link );
                }
                echo '</nav>';
            }
        }
        ?>
    </div>
    <style>
    .ufsc-honorability-admin{max-width:none}
    .ufsc-honorability-admin-filters{display:flex;gap:12px;align-items:end;margin:18px 0 22px;flex-wrap:wrap;padding:16px;background:#fff;border:1px solid #dcdcde;border-radius:10px}
    .ufsc-honorability-admin-filters label{display:grid;gap:5px}
    .ufsc-honorability-admin-filters input[type="search"]{min-width:300px}
    .ufsc-honorability-admin-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(520px,1fr));gap:16px;width:100%}
    .ufsc-honorability-admin-card{padding:20px;border:1px solid #dcdcde;border-radius:12px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.04);box-sizing:border-box}
    .ufsc-honorability-admin-card__head{display:flex;justify-content:space-between;gap:18px}
    .ufsc-honorability-admin-card h2{margin:0 0 4px}
    .ufsc-honorability-admin-card__head p{margin:0;color:#646970}
    .ufsc-honorability-admin-status{align-self:flex-start;padding:5px 9px;border-radius:999px;background:#eef2ff;color:#292668;font-weight:700;white-space:nowrap}
    .ufsc-honorability-admin-decision{margin-top:15px;padding-top:15px;border-top:1px solid #eee}
    .ufsc-honorability-admin-reason{display:grid;gap:6px}
    .ufsc-honorability-admin-reason textarea{width:100%;max-width:100%;box-sizing:border-box}
    .ufsc-honorability-admin-actions{display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap}
    .ufsc-honorability-admin-pagination{display:flex;justify-content:flex-end;margin:18px 0}
    .ufsc-honorability-admin-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;margin-left:4px;border:1px solid #c3c4c7;background:#fff;text-decoration:none;border-radius:4px}
    .ufsc-honorability-admin-pagination .page-numbers.current{background:#2271b1;color:#fff;border-color:#2271b1}
    @media(max-width:782px){
        .ufsc-honorability-admin-filters{align-items:stretch}
        .ufsc-honorability-admin-filters label,.ufsc-honorability-admin-filters input[type="search"],.ufsc-honorability-admin-filters select{width:100%;min-width:0}
        .ufsc-honorability-admin-list{grid-template-columns:1fr}
        .ufsc-honorability-admin-card__head{flex-direction:column}
        .ufsc-honorability-admin-pagination{justify-content:flex-start}
    }
    </style>
    <script>
    document.addEventListener('click',function(e){var b=e.target.closest('[data-require-reason="1"]');if(!b)return;var f=b.closest('form'),t=f&&f.querySelector('textarea[name="reason"]');if(t&&!t.value.trim()){e.preventDefault();alert('Merci d’indiquer le motif de la correction ou du refus.');t.focus();}});
    </script>
    <?php
}
