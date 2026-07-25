<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// UFSC PATCH: Cards-only licence list (stable HTML structure).

$current_season_global = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';
$next_season_global    = function_exists( 'ufsc_get_next_season' ) ? ufsc_get_next_season() : '';
$renew_open_global     = function_exists( 'ufsc_is_renewal_window_open' ) ? ufsc_is_renewal_window_open() : false;
$licence_product_id    = ! empty( $wc_settings['product_license_id'] ) ? absint( $wc_settings['product_license_id'] ) : 0;
$bulk_new_available    = false;

if ( $licence_product_id > 0 && ! empty( $licences ) ) {
    foreach ( $licences as $bulk_candidate ) {
        $candidate_status_raw  = $bulk_candidate->licence_statut ?? ( $bulk_candidate->statut ?? '' );
        $candidate_status_norm = function_exists( 'UFSC_Licence_Status' )
            ? UFSC_Licence_Status::display_status( $candidate_status_raw )
            : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $candidate_status_raw ) : $candidate_status_raw );
        $candidate_locked = function_exists( 'ufsc_is_licence_locked_for_club' )
            ? ufsc_is_licence_locked_for_club( $bulk_candidate )
            : ! ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $candidate_status_norm ) : false );

        if ( ! $candidate_locked ) {
            $bulk_new_available = true;
            break;
        }
    }
}
?>

<?php if ( $renew_open_global && $licence_product_id > 0 && ! empty( $licences ) ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ufsc-bulk-renewal-form" class="ufsc-bulk-renewal-form" style="margin:0 0 18px;padding:14px;border:1px solid #dcdcde;background:#fff;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <?php wp_nonce_field( 'ufsc_add_bulk_licence_renewals', '_ufsc_bulk_nonce' ); ?>
        <input type="hidden" name="action" value="ufsc_add_bulk_licence_renewals">
        <input type="hidden" name="product_id" value="<?php echo esc_attr( $licence_product_id ); ?>">
        <input type="hidden" name="ufsc_target_season" value="<?php echo esc_attr( $next_season_global ); ?>">
        <input type="hidden" name="ufsc_renew_licence_ids" id="ufsc-renew-licence-ids" value="">
        <strong><?php esc_html_e( 'Renouvellement groupé nominatif', 'ufsc-clubs' ); ?></strong>
        <span id="ufsc-renew-selection-count" aria-live="polite"><?php esc_html_e( '0 licence sélectionnée', 'ufsc-clubs' ); ?></span>
        <button type="submit" id="ufsc-bulk-renew-submit" class="button button-primary" disabled>
            <?php esc_html_e( 'Ajouter les licences sélectionnées au panier', 'ufsc-clubs' ); ?>
        </button>
        <small style="flex-basis:100%">
            <?php esc_html_e( 'Chaque personne sera ajoutée sur une ligne distincte du panier avec son identité et sa saison.', 'ufsc-clubs' ); ?>
        </small>
    </form>
<?php endif; ?>

<?php if ( $bulk_new_available ) : ?>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ufsc-bulk-new-licence-form" class="ufsc-bulk-new-licence-form" style="margin:0 0 18px;padding:14px;border:1px solid #dcdcde;background:#fff;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
        <input type="hidden" name="action" value="ufsc_add_to_cart">
        <input type="hidden" name="product_id" value="<?php echo esc_attr( $licence_product_id ); ?>">
        <input type="hidden" name="ufsc_license_ids" id="ufsc-new-licence-ids" value="">
        <strong><?php esc_html_e( 'Paiement groupé des nouvelles licences', 'ufsc-clubs' ); ?></strong>
        <span id="ufsc-new-selection-count" aria-live="polite"><?php esc_html_e( '0 licence sélectionnée', 'ufsc-clubs' ); ?></span>
        <button type="submit" id="ufsc-bulk-new-submit" class="button button-primary" disabled>
            <?php esc_html_e( 'Ajouter les dossiers sélectionnés au panier', 'ufsc-clubs' ); ?>
        </button>
        <small style="flex-basis:100%">
            <?php esc_html_e( 'Chaque nouvelle licence conservera sa propre ligne nominative dans le panier et la commande WooCommerce.', 'ufsc-clubs' ); ?>
        </small>
    </form>
<?php endif; ?>

<div class="ufsc-licence-grid">
    <?php if ( ! empty( $licences ) ) : ?>
        <?php foreach ( $licences as $licence ) :
            $last_name   = isset( $licence->nom_licence ) && '' !== trim( (string) $licence->nom_licence ) ? $licence->nom_licence : ( $licence->nom ?? '' );
            $full_name   = trim( ( $licence->prenom ?? '' ) . ' ' . $last_name );
            $gender_code = strtolower( $licence->sexe ?? '' );
            switch ( $gender_code ) {
                case 'm':
                case 'h':
                    $gender = __( 'Homme', 'ufsc-clubs' );
                    break;
                case 'f':
                    $gender = __( 'Femme', 'ufsc-clubs' );
                    break;
                default:
                    $gender = $licence->sexe ?? '';
            }

            $practice = isset( $licence->competition ) && $licence->competition
                ? __( 'Compétition', 'ufsc-clubs' )
                : __( 'Loisir', 'ufsc-clubs' );

            $age = '';
            if ( ! empty( $licence->date_naissance ) ) {
                $birth = strtotime( $licence->date_naissance );
                if ( $birth ) {
                    $age = floor( ( current_time( 'timestamp' ) - $birth ) / YEAR_IN_SECONDS );
                }
            }

            $status_raw  = $licence->licence_statut ?? ( $licence->statut ?? '' );
            $status_norm = function_exists( 'UFSC_Licence_Status' )
                ? UFSC_Licence_Status::display_status( $status_raw )
                : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : $status_raw );

            $is_locked = function_exists( 'ufsc_is_licence_locked_for_club' )
                ? ufsc_is_licence_locked_for_club( $licence )
                : ! ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $status_norm ) : false );

            $lock_reason = '';
            if ( 'valide' === $status_norm ) {
                $lock_reason = __( 'Validée', 'ufsc-clubs' );
            } elseif ( function_exists( 'ufsc_is_licence_paid' ) && ufsc_is_licence_paid( $licence ) ) {
                $lock_reason = __( 'Paiement / Commande', 'ufsc-clubs' );
            } elseif ( $is_locked ) {
                $lock_reason = __( 'Verrouillage', 'ufsc-clubs' );
            }

            $status_class   = $status_norm ? sanitize_html_class( $status_norm ) : 'en_attente';
            $season_label   = function_exists( 'ufsc_get_licence_season_label' ) ? ufsc_get_licence_season_label( $licence ) : ( function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : '' );
            $current_season = $current_season_global;
            $next_season    = $next_season_global;
            $renew_open     = $renew_open_global;
            $renew_done     = function_exists( 'ufsc_get_renewed_licence_marker' )
                ? (bool) ufsc_get_renewed_licence_marker( (int) ( $licence->id ?? 0 ), $next_season )
                : false;

            $renew_start_ts   = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
            $renew_open_label = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );

            $can_renew = $renew_open && ! $renew_done && ! $is_locked && ! empty( $current_season ) && $season_label === $current_season;
            $is_in_cart = false;
            if ( function_exists( 'WC' ) && WC() && WC()->cart ) {
                foreach ( WC()->cart->get_cart() as $cart_item ) {
                    $item_ids = array();
                    if ( function_exists( 'ufsc_extract_licence_ids_from_cart_item' ) ) {
                        $item_ids = ufsc_extract_licence_ids_from_cart_item( $cart_item );
                    } elseif ( isset( $cart_item['ufsc_licence_id'] ) ) {
                        $item_ids[] = absint( $cart_item['ufsc_licence_id'] );
                    }

                    if ( in_array( absint( $licence->id ?? 0 ), array_map( 'absint', (array) $item_ids ), true ) ) {
                        $is_in_cart = true;
                        break;
                    }
                }
            }
            $can_bulk_new = ! $is_locked && ! $is_in_cart && $licence_product_id > 0;
            ?>
            <div class="ufsc-card ufsc-licence-card">
                <div class="ufsc-licence-card-header">
                    <h4 class="ufsc-licence-name"><?php echo esc_html( $full_name ); ?></h4>
                    <?php echo UFSC_Badges::render_licence_badge( $status_norm, array( 'custom_class' => 'ufsc-badge ufsc-badge-' . $status_class ) ); ?>
                </div>

                <?php if ( $can_renew && $licence_product_id > 0 ) : ?>
                    <label style="display:flex;gap:8px;align-items:center;margin:8px 0;font-weight:600">
                        <input type="checkbox" class="ufsc-renew-licence-checkbox" value="<?php echo esc_attr( absint( $licence->id ?? 0 ) ); ?>" data-name="<?php echo esc_attr( $full_name ); ?>">
                        <?php esc_html_e( 'Sélectionner pour le renouvellement groupé', 'ufsc-clubs' ); ?>
                    </label>
                <?php endif; ?>

                <?php if ( $can_bulk_new ) : ?>
                    <label style="display:flex;gap:8px;align-items:center;margin:8px 0;font-weight:600">
                        <input type="checkbox" class="ufsc-new-licence-checkbox" value="<?php echo esc_attr( absint( $licence->id ?? 0 ) ); ?>" data-name="<?php echo esc_attr( $full_name ); ?>">
                        <?php esc_html_e( 'Sélectionner pour le paiement groupé', 'ufsc-clubs' ); ?>
                    </label>
                <?php endif; ?>

                <div class="ufsc-licence-meta">
                    <?php if ( $gender ) : ?><span><?php echo esc_html( $gender ); ?></span><?php endif; ?>
                    <span><?php echo esc_html( $practice ); ?></span>
                    <?php if ( '' !== $age ) : ?><span><?php echo intval( $age ); ?> <?php esc_html_e( 'ans', 'ufsc-clubs' ); ?></span><?php endif; ?>
                    <?php if ( $season_label ) : ?><span><?php echo esc_html( $season_label ); ?></span><?php endif; ?>
                </div>

                <div class="ufsc-licence-actions">
                    <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>">
                        <?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?>
                    </a>

                    <?php if ( $can_renew && $licence_product_id > 0 ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-action-form">
                            <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
                            <input type="hidden" name="action" value="ufsc_add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr( $licence_product_id ); ?>">
                            <input type="hidden" name="ufsc_action" value="renew_licence">
                            <input type="hidden" name="ufsc_renew_from_licence_id" value="<?php echo esc_attr( (int) ( $licence->id ?? 0 ) ); ?>">
                            <input type="hidden" name="ufsc_target_season" value="<?php echo esc_attr( $next_season ); ?>">
                            <button type="submit" class="ufsc-action ufsc-action-primary"><?php esc_html_e( 'Renouveler', 'ufsc-clubs' ); ?></button>
                        </form>
                    <?php elseif ( ! $renew_open ) : ?>
                        <span class="ufsc-text-muted" title="<?php esc_attr_e( 'Le renouvellement n\'est pas encore ouvert.', 'ufsc-clubs' ); ?>">
                            <?php echo esc_html( sprintf( __( 'Renouvellement %1$s ouvert à partir du %2$s', 'ufsc-clubs' ), $next_season, $renew_open_label ) ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( ! $is_locked ) : ?>
                        <?php if ( $licence_product_id > 0 ) : ?>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-action-form">
                                <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
                                <input type="hidden" name="action" value="ufsc_add_to_cart">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr( $licence_product_id ); ?>">
                                <input type="hidden" name="ufsc_club_id" value="<?php echo esc_attr( (int) ( $licence->club_id ?? 0 ) ); ?>">
                                <input type="hidden" name="ufsc_license_ids" value="<?php echo esc_attr( (int) ( $licence->id ?? 0 ) ); ?>">
                                <button type="submit" class="ufsc-action ufsc-action-primary">
                                    <?php echo $is_in_cart ? esc_html__( 'Payer maintenant / Voir panier', 'ufsc-clubs' ) : esc_html__( 'Ajouter au panier', 'ufsc-clubs' ); ?>
                                </button>
                            </form>
                        <?php endif; ?>

                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>">
                            <?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?>
                        </a>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-action-form"
                              onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la suppression de cette licence ?', 'ufsc-clubs' ) ); ?>');">
                            <?php wp_nonce_field( 'ufsc_delete_licence' ); ?>
                            <input type="hidden" name="action" value="ufsc_delete_licence">
                            <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                            <button type="submit" class="ufsc-action ufsc-action-danger">
                                <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                            </button>
                        </form>
                    <?php else : ?>
                        <span class="ufsc-text-muted">
                            <?php echo esc_html( '🔒 ' . sprintf( __( 'Verrouillée (%s)', 'ufsc-clubs' ), $lock_reason ) ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="ufsc-message ufsc-info">
            <?php esc_html_e( 'Aucune licence trouvée.', 'ufsc-clubs' ); ?>
        </div>
    <?php endif; ?>
</div>

<?php if ( ( $renew_open_global || $bulk_new_available ) && $licence_product_id > 0 && ! empty( $licences ) ) : ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function bindBulkForm(formId, checkboxSelector, idsFieldId, countId, submitId) {
        const form = document.getElementById(formId);
        if (!form) return;

        const checkboxes = Array.from(document.querySelectorAll(checkboxSelector));
        const idsField = document.getElementById(idsFieldId);
        const countLabel = document.getElementById(countId);
        const submitButton = document.getElementById(submitId);
        if (!idsField || !countLabel || !submitButton) return;

        function refreshSelection() {
            const selected = checkboxes.filter(function (checkbox) { return checkbox.checked; });
            idsField.value = selected.map(function (checkbox) { return checkbox.value; }).join(',');
            countLabel.textContent = selected.length + (selected.length > 1 ? ' licences sélectionnées' : ' licence sélectionnée');
            submitButton.disabled = selected.length === 0;
        }

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', refreshSelection);
        });

        form.addEventListener('submit', function (event) {
            refreshSelection();
            if (!idsField.value) {
                event.preventDefault();
            }
        });
    }

    bindBulkForm('ufsc-bulk-renewal-form', '.ufsc-renew-licence-checkbox', 'ufsc-renew-licence-ids', 'ufsc-renew-selection-count', 'ufsc-bulk-renew-submit');
    bindBulkForm('ufsc-bulk-new-licence-form', '.ufsc-new-licence-checkbox', 'ufsc-new-licence-ids', 'ufsc-new-selection-count', 'ufsc-bulk-new-submit');
});
</script>
<?php endif; ?>
