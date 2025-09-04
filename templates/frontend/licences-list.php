<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="ufsc-licence-grid">
    <?php if ( ! empty( $licences ) ) : ?>
        <?php foreach ( $licences as $licence ) :
            $full_name = trim( ( $licence->prenom ?? '' ) . ' ' . ( $licence->nom ?? '' ) );
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
            ?>
            <div class="ufsc-card ufsc-licence-card">
                <div class="ufsc-licence-card-header">
                    <h4 class="ufsc-licence-name"><?php echo esc_html( $full_name ); ?></h4>
                    <?php echo UFSC_Badges::render_licence_badge( $licence->statut ?? '', array( 'custom_class' => 'ufsc-badge ufsc-badge-' . ( $licence->statut ?? 'pending' ) ) ); ?>
                </div>
                <div class="ufsc-licence-meta">
                    <?php if ( $gender ) : ?><span><?php echo esc_html( $gender ); ?></span><?php endif; ?>
                    <span><?php echo esc_html( $practice ); ?></span>
                    <?php if ( '' !== $age ) : ?><span><?php echo intval( $age ); ?> <?php esc_html_e( 'ans', 'ufsc-clubs' ); ?></span><?php endif; ?>
                </div>
                <div class="ufsc-licence-actions">
                    <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                    <?php if ( 'pending' === ( $licence->statut ?? '' ) ) : ?>
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p class="ufsc-no-items"><?php esc_html_e( 'Aucune licence trouvée.', 'ufsc-clubs' ); ?></p>
    <?php endif; ?>
</div>

