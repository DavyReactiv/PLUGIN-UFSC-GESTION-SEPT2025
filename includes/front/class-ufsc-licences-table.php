<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Render a responsive licences table with filters.
 */
class UFSC_Licences_Table {
    /**
     * Output licences table with optional filters.
     *
     * @param array $licences Array of licence objects.
     * @param array $args     Optional arguments.
     */
    public static function render( $licences, $args = array() ) {
        $status = isset( $_GET['ufsc_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ufsc_status'] ) ) : '';

        $status = isset( $args['status'] ) ? $args['status'] : $status;

        // Filter licences in-memory if a status filter is provided.
        if ( $status ) {
            $licences = array_filter( $licences, function( $licence ) use ( $status ) {
                $licence_status = $licence->statut ?? ( $licence->status ?? '' );
                return $licence_status === $status;
            } );
        }

        // Filters form.
        echo '<form method="get" class="ufsc-licences-filters">';
        echo '<div class="ufsc-filter-group">';
        echo '<label for="ufsc_status">' . esc_html__( 'Statut:', 'ufsc-clubs' ) . '</label>';
        echo '<select id="ufsc_status" name="ufsc_status">';
        echo '<option value="">' . esc_html__( 'Tous', 'ufsc-clubs' ) . '</option>';
        $status_options = array(
            'valide'     => __( 'Validée', 'ufsc-clubs' ),
            'en_attente' => __( 'En attente', 'ufsc-clubs' ),
            'rejete'     => __( 'Rejetée', 'ufsc-clubs' ),
            'paye'       => __( 'Payée', 'ufsc-clubs' ),
            'refuse'     => __( 'Refusée', 'ufsc-clubs' ),
        );
        foreach ( $status_options as $value => $label ) {
            printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $status, $value, false ), esc_html( $label ) );
        }
        echo '</select>';
        echo '</div>';

        echo '<button type="submit" class="ufsc-btn ufsc-btn-primary">' . esc_html__( 'Filtrer', 'ufsc-clubs' ) . '</button>';
        echo '</form>';

        // Table start.
        echo '<table class="ufsc-table ufsc-licences-table">';
        echo '<thead><tr>';
        $headers = array(
            'ID'          => __( 'ID', 'ufsc-clubs' ),
            'holder'      => __( 'Titulaire', 'ufsc-clubs' ),
            'gender'      => __( 'Sexe', 'ufsc-clubs' ),
            'practice'    => __( 'Pratique', 'ufsc-clubs' ),
            'age'         => __( 'Âge', 'ufsc-clubs' ),
            'status'      => __( 'Statut', 'ufsc-clubs' ),
            'expiration'  => __( 'Expiration', 'ufsc-clubs' ),
            'included'    => __( 'Incluse', 'ufsc-clubs' ),
            'actions'     => __( 'Actions', 'ufsc-clubs' ),
        );
        foreach ( $headers as $key => $label ) {
            echo '<th>' . esc_html( $label ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        if ( empty( $licences ) ) {
            echo '<tr><td colspan="9" class="ufsc-no-items">' . esc_html__( 'Aucune licence trouvée.', 'ufsc-clubs' ) . '</td></tr>';
        } else {
            foreach ( $licences as $licence ) {
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
                $practice = ( isset( $licence->competition ) && $licence->competition ) ? __( 'Compétition', 'ufsc-clubs' ) : __( 'Loisir', 'ufsc-clubs' );
                $age = '';
                if ( ! empty( $licence->date_naissance ) ) {
                    $birth = strtotime( $licence->date_naissance );
                    if ( $birth ) {
                        $age = floor( ( current_time( 'timestamp' ) - $birth ) / YEAR_IN_SECONDS );
                    }
                }
                $licence_status = $licence->statut ?? ( $licence->status ?? '' );
                $badge_options  = array( 'custom_class' => 'ufsc-badge' );
                if ( isset( $status_options[ $licence_status ] ) ) {
                    $badge_options['custom_label'] = $status_options[ $licence_status ];
                }
                $status_badge = UFSC_Badges::render_licence_badge( $licence_status, $badge_options );
                $expiration = '';
                if ( ! empty( $licence->certificat_expiration ) ) {
                    $expiration = mysql2date( get_option( 'date_format' ), $licence->certificat_expiration );
                } elseif ( ! empty( $licence->date_expiration ) ) {
                    $expiration = mysql2date( get_option( 'date_format' ), $licence->date_expiration );
                }
                echo '<tr>';
                echo '<td>' . intval( $licence->id ?? 0 ) . '</td>';
                echo '<td>' . esc_html( $full_name ) . '</td>';
                echo '<td>' . esc_html( $gender ) . '</td>';
                echo '<td>' . esc_html( $practice ) . '</td>';
                echo '<td>' . ( '' !== $age ? intval( $age ) : '' ) . '</td>';
                echo '<td>' . $status_badge . '</td>';
                echo '<td>' . esc_html( $expiration ) . '</td>';
                echo '<td>';
                if ( ! empty( $licence->is_included ) ) {
                    echo '<span class="ufsc-badge badge-success ufsc-badge-included">' . esc_html__( 'Incluse', 'ufsc-clubs' ) . '</span>';
                }
                echo '</td>';
                echo '<td>';
                echo '<div class="ufsc-actions">';
                echo '<a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'view', 'licence_id' => $licence->id ) ) ) . '">' . esc_html__( 'Consulter', 'ufsc-clubs' ) . '</a>';
                if ( empty( $licence->statut ) || ! UFSC_Badges::is_active_licence_status( $licence->statut ) ) {
                    echo ' <a class="ufsc-action" href="' . esc_url( add_query_arg( array( 'ufsc_action' => 'edit', 'licence_id' => $licence->id ) ) ) . '">' . esc_html__( 'Modifier', 'ufsc-clubs' ) . '</a>';
                    if ( current_user_can( 'manage_options' ) ) {
                        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ufsc-inline-form">';
                        echo '<input type="hidden" name="action" value="ufsc_delete_licence" />';
                        echo '<input type="hidden" name="licence_id" value="' . intval( $licence->id ) . '" />';
                        wp_nonce_field( 'ufsc_delete_licence' );
                        echo '<button type="submit" class="ufsc-action ufsc-delete">' . esc_html__( 'Supprimer', 'ufsc-clubs' ) . '</button>';
                        echo '</form>';
                    }
                }
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }
}
