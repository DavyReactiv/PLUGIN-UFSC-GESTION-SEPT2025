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
            <?php
            $status_raw  = $licence->statut ?? ( $licence->status ?? '' );
            $status_norm = function_exists( 'ufsc_normalize_licence_status' ) ? ufsc_normalize_licence_status( $status_raw ) : $status_raw;
            $status_class = $status_norm ? sanitize_html_class( $status_norm ) : 'pending';
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
                </div>
                <div class="ufsc-licence-actions">
                    <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                    <?php if ( function_exists( 'ufsc_is_editable_licence_status' ) ? ufsc_is_editable_licence_status( $status_raw ) : ( 'pending' === $status_norm ) ) : ?>
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>

<table class="ufsc-table ufsc-licences-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></th>
            <th><?php esc_html_e( 'R\u00f4le', 'ufsc-clubs' ); ?></th>
            <th><?php esc_html_e( 'Statut', 'ufsc-clubs' ); ?></th>
            <th><?php esc_html_e( 'Actions', 'ufsc-clubs' ); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php if ( ! empty( $licences ) ) : ?>
        <?php foreach ( $licences as $licence ) : ?>
            <?php
            $status_raw  = $licence->statut ?? ( $licence->status ?? '' );
            $status_norm = function_exists( 'ufsc_normalize_licence_status' ) ? ufsc_normalize_licence_status( $status_raw ) : $status_raw;
            ?>
            <tr>
                <td><?php echo esc_html( trim( ( $licence->prenom ?? '' ) . ' ' . ( $licence->nom ?? '' ) ) ); ?></td>
                <td><?php echo esc_html( $licence->role ?? '' ); ?></td>
                <td>
                    <?php echo UFSC_Badges::render_licence_badge( $status_norm, array( 'custom_class' => 'ufsc-badge' ) ); ?>
                </td>
                <td>
                    <div class="ufsc-actions">
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                        <?php if ( empty( $status_norm ) || ! UFSC_Badges::is_active_licence_status( $status_norm ) ) : ?>
                            <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Modifier', 'ufsc-clubs' ); ?></a>
                            <?php if ( current_user_can( 'manage_options' ) ) : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-inline-form">
                                    <input type="hidden" name="action" value="ufsc_delete_licence" />
                                    <input type="hidden" name="licence_id" value="<?php echo intval( $licence->id ); ?>" />
                                    <?php wp_nonce_field( 'ufsc_delete_licence' ); ?>
                                    <button type="submit" class="ufsc-action ufsc-delete">
                                        <?php esc_html_e( 'Supprimer', 'ufsc-clubs' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

        <?php endforeach; ?>
    <?php else : ?>
        <p class="ufsc-no-items"><?php esc_html_e( 'Aucune licence trouvée.', 'ufsc-clubs' ); ?></p>
    <?php endif; ?>
</div>
