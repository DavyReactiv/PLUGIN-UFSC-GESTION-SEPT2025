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
            $status_norm  = function_exists( 'UFSC_Licence_Status' ) ? UFSC_Licence_Status::display_status( $status_raw ) : ( function_exists( 'ufsc_get_licence_status_norm' ) ? ufsc_get_licence_status_norm( $status_raw ) : $status_raw );
            $status_class = $status_norm ? sanitize_html_class( $status_norm ) : 'en_attente';
            $season_label = function_exists( 'ufsc_get_licence_season' ) ? ufsc_get_licence_season( $licence ) : '';
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
                    <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                    <?php if ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $status_norm ) : ( 'en_attente' === $status_norm ) ) : ?>
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?></a>
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
