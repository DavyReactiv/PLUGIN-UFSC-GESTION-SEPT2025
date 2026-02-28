<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
// UFSC PATCH: Cards-only licence list (stable HTML structure).
?>

<div class="ufsc-licence-grid">
    <?php if ( ! empty( $licences ) ) : ?>
        <?php foreach ( $licences as $licence ) :
            $full_name   = trim( ( $licence->prenom ?? '' ) . ' ' . ( $licence->nom ?? '' ) );
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

            $status_raw   = $licence->licence_statut ?? ( $licence->statut ?? '' );
            $status_norm  = function_exists( 'UFSC_Licence_Status' )
                ? UFSC_Licence_Status::display_status( $status_raw )
                : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : $status_raw );

            $is_locked    = function_exists( 'ufsc_is_licence_locked_for_club' )
                ? ufsc_is_licence_locked_for_club( $licence )
                : ! ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $status_norm ) : false );

            $lock_reason  = '';
            if ( 'valide' === $status_norm ) {
                $lock_reason = __( 'Validée', 'ufsc-clubs' );
            } elseif ( function_exists( 'ufsc_is_licence_paid' ) && ufsc_is_licence_paid( $licence ) ) {
                $lock_reason = __( 'Paiement / Commande', 'ufsc-clubs' );
            } elseif ( $is_locked ) {
                $lock_reason = __( 'Verrouillage', 'ufsc-clubs' );
            }

            $status_class  = $status_norm ? sanitize_html_class( $status_norm ) : 'en_attente';
            $season_label  = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : '';
            $current_season = function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '';
            $next_season    = function_exists( 'ufsc_get_next_season' ) ? ufsc_get_next_season() : '';
            $renew_open     = function_exists( 'ufsc_is_renewal_window_open' ) ? ufsc_is_renewal_window_open() : false;
            $renew_done     = function_exists( 'ufsc_get_renewed_licence_marker' )
                ? (bool) ufsc_get_renewed_licence_marker( (int) ( $licence->id ?? 0 ), $next_season )
                : false;

            $renew_start_ts   = function_exists( 'ufsc_get_renewal_window_start_ts' ) ? (int) ufsc_get_renewal_window_start_ts() : 0;
            $renew_open_label = $renew_start_ts > 0 ? wp_date( 'd/m/Y', $renew_start_ts ) : __( '30/07', 'ufsc-clubs' );

            $can_renew = $renew_open && ! $renew_done && ! $is_locked && ! empty( $current_season ) && $season_label === $current_season;
            ?>
            <div class="ufsc-card ufsc-licence-card">
                <div class="ufsc-licence-card-header">
                    <h4 class="ufsc-licence-name"><?php echo esc_html( $full_name ); ?></h4>
                    <?php echo UFSC_Badges::render_licence_badge( $status_norm, array( 'custom_class' => 'ufsc-badge ufsc-badge-' . $status_class ) ); ?>
                </div>

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

                    <?php if ( $can_renew && ! empty( $wc_settings['product_license_id'] ) ) : ?>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                            <?php wp_nonce_field( 'ufsc_add_to_cart_action', '_ufsc_nonce' ); ?>
                            <input type="hidden" name="action" value="ufsc_add_to_cart">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr( (int) $wc_settings['product_license_id'] ); ?>">
                            <input type="hidden" name="ufsc_action" value="renew_licence">
                            <input type="hidden" name="ufsc_renew_from_licence_id" value="<?php echo esc_attr( (int) ( $licence->id ?? 0 ) ); ?>">
                            <input type="hidden" name="ufsc_target_season" value="<?php echo esc_attr( $next_season ); ?>">
                            <button type="submit" class="ufsc-action"><?php esc_html_e( 'Renouveler', 'ufsc-clubs' ); ?></button>
                        </form>
                    <?php elseif ( ! $renew_open ) : ?>
                        <span class="ufsc-text-muted" title="<?php esc_attr_e( 'Le renouvellement n\'est pas encore ouvert.', 'ufsc-clubs' ); ?>">
                            <?php echo esc_html( sprintf( __( 'Renouvellement %1$s ouvert à partir du %2$s', 'ufsc-clubs' ), $next_season, $renew_open_label ) ); ?>
                        </span>
                    <?php endif; ?>

                    <?php if ( ! $is_locked ) : ?>
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>">
                            <?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?>
                        </a>

                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"
                              onsubmit="return confirm('<?php echo esc_js( __( 'Confirmer la suppression de cette licence ?', 'ufsc-clubs' ) ); ?>');">
                            <?php wp_nonce_field( 'ufsc_delete_licence' ); ?>
                            <input type="hidden" name="action" value="ufsc_delete_licence">
                            <input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence->id ?? 0 ); ?>">
                            <button type="submit" class="ufsc-action ufsc-action-danger" style="background:none;border:none;padding:0;cursor:pointer;">
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