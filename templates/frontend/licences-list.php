<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
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
            <tr>
                <td><?php echo esc_html( trim( ( $licence->prenom ?? '' ) . ' ' . ( $licence->nom ?? '' ) ) ); ?></td>
                <td><?php echo esc_html( $licence->role ?? '' ); ?></td>
                <td>
                    <?php echo UFSC_Badges::render_licence_badge( $licence->statut ?? '', array( 'custom_class' => 'ufsc-badge' ) ); ?>
                </td>
                <td>
                    <div class="ufsc-actions">
                        <a class="ufsc-action" href="<?php echo esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ); ?>"><?php esc_html_e( 'Consulter', 'ufsc-clubs' ); ?></a>
                        <?php if ( empty( $licence->statut ) || ! UFSC_Badges::is_active_licence_status( $licence->statut ) ) : ?>
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
        <tr>
            <td colspan="4" class="ufsc-no-items"><?php esc_html_e( 'Aucune licence trouv\u00e9e.', 'ufsc-clubs' ); ?></td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>
