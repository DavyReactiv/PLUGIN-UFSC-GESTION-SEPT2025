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

/** Read season-scoped honorability records from the canonical options. */
function ufsc_get_honorability_admin_records( $season = '', $status = '' ) {
    global $wpdb;
    $season = $season ?: ( function_exists( 'ufsc_get_honorability_current_season' ) ? ufsc_get_honorability_current_season() : '' );
    $status = sanitize_key( (string) $status );
    $prefix = $wpdb->esc_like( 'ufsc_honorability_attestation_' ) . '%';
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix ) );
    $records = array();

    foreach ( (array) $rows as $row ) {
        $record = maybe_unserialize( $row->option_value );
        if ( ! is_array( $record ) || empty( $record['licence_id'] ) || empty( $record['attachment_id'] ) ) { continue; }
        if ( $season && (string) ( $record['season'] ?? '' ) !== (string) $season ) { continue; }
        if ( $status && (string) ( $record['status'] ?? '' ) !== $status ) { continue; }
        $records[] = $record;
    }

    usort( $records, static function( $a, $b ) {
        return strcmp( (string) ( $b['uploaded_at'] ?? '' ), (string) ( $a['uploaded_at'] ?? '' ) );
    } );
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
        $lic = $wpdb->get_row( $wpdb->prepare( "SELECT nom, prenom, role, club_id FROM `{$licences}` WHERE id = %d LIMIT 1", absint( $record['licence_id'] ?? 0 ) ) );
        if ( $lic ) {
            $context['person'] = trim( (string) $lic->prenom . ' ' . (string) $lic->nom );
            $context['role']   = (string) $lic->role;
            if ( empty( $record['club_id'] ) ) { $record['club_id'] = absint( $lic->club_id ); }
        }
    }
    if ( $clubs && ! empty( $record['club_id'] ) ) {
        $club = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$clubs}` WHERE id = %d LIMIT 1", absint( $record['club_id'] ) ) );
        if ( $club && function_exists( 'ufsc_get_club_profile_value' ) ) {
            $context['club'] = (string) ufsc_get_club_profile_value( $club, 'name' );
        }
    }
    return $context;
}

function ufsc_render_honorability_admin_page() {
    $cap = class_exists( 'UFSC_Permissions' ) ? UFSC_Permissions::CAP_LICENCES_MANAGE : 'manage_options';
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Accès refusé.', 'ufsc-clubs' ) ); }

    $season = isset( $_GET['season'] ) ? sanitize_text_field( wp_unslash( $_GET['season'] ) ) : ( function_exists( 'ufsc_get_honorability_current_season' ) ? ufsc_get_honorability_current_season() : '' );
    $filter = isset( $_GET['document_status'] ) ? sanitize_key( wp_unslash( $_GET['document_status'] ) ) : 'pending';
    $records = ufsc_get_honorability_admin_records( $season, 'all' === $filter ? '' : $filter );
    $statuses = array( 'pending' => 'À vérifier', 'validated' => 'Validées', 'correction_required' => 'À corriger', 'rejected' => 'Refusées', 'expired' => 'À renouveler', 'all' => 'Toutes' );
    ?>
    <div class="wrap ufsc-honorability-admin">
        <h1><?php esc_html_e( 'Contrôle des attestations d’honorabilité', 'ufsc-clubs' ); ?></h1>
        <p><?php esc_html_e( 'Chaque document déposé par un club reste en attente jusqu’à une décision explicite de l’UFSC.', 'ufsc-clubs' ); ?></p>

        <form method="get" style="display:flex;gap:10px;align-items:end;margin:18px 0 22px;flex-wrap:wrap">
            <input type="hidden" name="page" value="ufsc-honorability">
            <label><strong><?php esc_html_e( 'Saison', 'ufsc-clubs' ); ?></strong><br><input type="text" name="season" value="<?php echo esc_attr( $season ); ?>" placeholder="2026-2027"></label>
            <label><strong><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></strong><br><select name="document_status">
                <?php foreach ( $statuses as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filter, $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
            </select></label>
            <button class="button button-primary"><?php esc_html_e( 'Filtrer', 'ufsc-clubs' ); ?></button>
        </form>

        <?php if ( empty( $records ) ) : ?>
            <div class="notice notice-info inline"><p><?php esc_html_e( 'Aucune attestation dans ce filtre.', 'ufsc-clubs' ); ?></p></div>
        <?php else : ?>
            <div class="ufsc-honorability-admin-list">
            <?php foreach ( $records as $record ) :
                $ctx = ufsc_get_honorability_admin_context( $record );
                $licence_id = absint( $record['licence_id'] ?? 0 );
                $attachment_id = absint( $record['attachment_id'] ?? 0 );
                $file_url = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
                $meta = function_exists( 'ufsc_get_honorability_status_meta' ) ? ufsc_get_honorability_status_meta( $record['status'] ?? 'pending' ) : array( 'label' => $record['status'] ?? 'pending' );
            ?>
                <section class="ufsc-honorability-admin-card">
                    <div class="ufsc-honorability-admin-card__head">
                        <div><h2><?php echo esc_html( $ctx['person'] ?: sprintf( 'Licence #%d', $licence_id ) ); ?></h2><p><?php echo esc_html( ( $ctx['club'] ?: 'Club #' . absint( $record['club_id'] ?? 0 ) ) . ' — ' . ( function_exists( 'ufsc_get_honorability_role_label' ) ? ufsc_get_honorability_role_label( $ctx['role'] ) : $ctx['role'] ) . ' — ' . ( $record['season'] ?? '' ) ); ?></p></div>
                        <span class="ufsc-honorability-admin-status"><?php echo esc_html( $meta['label'] ?? '' ); ?></span>
                    </div>
                    <p><?php echo esc_html( sprintf( 'Déposé le %s', $record['uploaded_at'] ?? '—' ) ); ?></p>
                    <?php if ( $file_url ) : ?><p><a class="button" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir le document', 'ufsc-clubs' ); ?></a></p><?php endif; ?>

                    <form class="ufsc-honorability-admin-decision" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="ufsc_decide_honorability_attestation">
                        <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence_id ); ?>">
                        <input type="hidden" name="season" value="<?php echo esc_attr( $record['season'] ?? $season ); ?>">
                        <?php wp_nonce_field( 'ufsc_decide_honorability_' . $licence_id ); ?>
                        <label class="ufsc-honorability-admin-reason"><strong><?php esc_html_e( 'Motif / commentaire', 'ufsc-clubs' ); ?></strong><textarea name="reason" rows="3" placeholder="Obligatoire en cas de correction ou de refus."><?php echo esc_textarea( $record['reason'] ?? '' ); ?></textarea></label>
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
    </div>
    <style>
    .ufsc-honorability-admin-list{display:grid;gap:16px;max-width:1100px}.ufsc-honorability-admin-card{padding:20px;border:1px solid #dcdcde;border-radius:12px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.04)}.ufsc-honorability-admin-card__head{display:flex;justify-content:space-between;gap:18px}.ufsc-honorability-admin-card h2{margin:0 0 4px}.ufsc-honorability-admin-card__head p{margin:0;color:#646970}.ufsc-honorability-admin-status{align-self:flex-start;padding:5px 9px;border-radius:999px;background:#eef2ff;color:#292668;font-weight:700}.ufsc-honorability-admin-decision{margin-top:15px;padding-top:15px;border-top:1px solid #eee}.ufsc-honorability-admin-reason{display:grid;gap:6px;max-width:760px}.ufsc-honorability-admin-reason textarea{width:100%}.ufsc-honorability-admin-actions{display:flex;gap:10px;align-items:center;margin-top:12px;flex-wrap:wrap}@media(max-width:700px){.ufsc-honorability-admin-card__head{flex-direction:column}}
    </style>
    <script>
    document.addEventListener('click',function(e){var b=e.target.closest('[data-require-reason="1"]');if(!b)return;var f=b.closest('form'),t=f&&f.querySelector('textarea[name="reason"]');if(t&&!t.value.trim()){e.preventDefault();alert('Merci d’indiquer le motif de la correction ou du refus.');t.focus();}});
    </script>
    <?php
}
