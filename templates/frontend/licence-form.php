<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
include UFSC_CL_DIR . 'templates/partials/notice.php';
?>
<div class="ufsc-front ufsc-full">
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-form">
    <input type="hidden" name="action" value="ufsc_save_licence" />
    <?php wp_nonce_field( 'ufsc_save_licence' ); ?>
    <input type="hidden" name="licence_id" value="<?php echo isset( $licence->id ) ? intval( $licence->id ) : 0; ?>" />
    <?php
    $wc_settings    = ufsc_get_woocommerce_settings();
    $included_limit = isset( $wc_settings['included_licenses'] ) ? (int) $wc_settings['included_licenses'] : 10;
    $included_count = UFSC_Licence_Form::get_included_count();
    ?>
    <input type="hidden" id="included_count" value="<?php echo esc_attr( $included_count ); ?>" />
    <input type="hidden" id="included_limit" value="<?php echo esc_attr( $included_limit ); ?>" />
    <input type="hidden" name="ufsc_submit_action" id="ufsc_submit_action" value="save" />

    <div class="ufsc-grid">
        <div class="ufsc-field">
            <label for="prenom"><?php esc_html_e( 'Pr\u00e9nom', 'ufsc-clubs' ); ?></label>
            <input type="text" id="prenom" name="prenom" value="<?php echo esc_attr( $licence->prenom ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="nom"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
            <input type="text" id="nom" name="nom" value="<?php echo esc_attr( $licence->nom ?? '' ); ?>" />
        </div>
        <div class="ufsc-field ufsc-field-full">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?php echo esc_attr( $licence->email ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="date_naissance"><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></label>
            <input type="date" id="date_naissance" name="date_naissance" value="<?php echo esc_attr( $licence->date_naissance ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label for="role"><?php esc_html_e( 'R\u00f4le', 'ufsc-clubs' ); ?></label>
            <select id="role" name="role">
                <option value=""<?php selected( $licence->role ?? '', '' ); ?>><?php esc_html_e( 'S\u00e9lectionner', 'ufsc-clubs' ); ?></option>
                <option value="president"<?php selected( $licence->role ?? '', 'president' ); ?>><?php esc_html_e( 'Pr\u00e9sident', 'ufsc-clubs' ); ?></option>
                <option value="secretaire"<?php selected( $licence->role ?? '', 'secretaire' ); ?>><?php esc_html_e( 'Secr\u00e9taire', 'ufsc-clubs' ); ?></option>
                <option value="tresorier"<?php selected( $licence->role ?? '', 'tresorier' ); ?>><?php esc_html_e( 'Tr\u00e9sorier', 'ufsc-clubs' ); ?></option>
                <option value="entraineur"<?php selected( $licence->role ?? '', 'entraineur' ); ?>><?php esc_html_e( 'Entra\u00eeneur', 'ufsc-clubs' ); ?></option>
                <option value="adherent"<?php selected( $licence->role ?? '', 'adherent' ); ?>><?php esc_html_e( 'Adh\u00e9rent', 'ufsc-clubs' ); ?></option>
            </select>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="reduction_postier" name="reduction_postier" value="1" <?php checked( $licence->reduction_postier ?? 0, 1 ); ?> /> <?php esc_html_e( 'R\u00e9duction postier', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field ufsc-field-identifiant-laposte ufsc-field-full" style="display:none;">
            <label for="identifiant_laposte"><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?></label>
            <input type="text" id="identifiant_laposte" name="identifiant_laposte" value="<?php echo esc_attr( $licence->identifiant_laposte ?? '' ); ?>" />
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="reduction_benevole" name="reduction_benevole" value="1" <?php checked( $licence->reduction_benevole ?? 0, 1 ); ?> /> <?php esc_html_e( 'R\u00e9duction b\u00e9n\u00e9vole', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="licence_delegataire" name="licence_delegataire" value="1" <?php checked( $licence->licence_delegataire ?? 0, 1 ); ?> /> <?php esc_html_e( 'Licence d\u00e9l\u00e9gataire', 'ufsc-clubs' ); ?></label>
        </div>
        <div class="ufsc-field ufsc-field-numero-delegataire ufsc-field-full" style="display:none;">
            <label for="numero_licence_delegataire"><?php esc_html_e( 'Num\u00e9ro de licence d\u00e9l\u00e9gataire', 'ufsc-clubs' ); ?></label>
            <input type="text" id="numero_licence_delegataire" name="numero_licence_delegataire" value="<?php echo esc_attr( $licence->numero_licence_delegataire ?? '' ); ?>" />
        </div>
        <div class="ufsc-field ufsc-field-full">
            <label for="note"><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?></label>
            <textarea id="note" name="note" rows="3"><?php echo esc_textarea( $licence->note ?? '' ); ?></textarea>
        </div>
        <div class="ufsc-field">
            <label class="ufsc-checkbox"><input type="checkbox" id="is_included" name="is_included" value="1" <?php checked( $licence->is_included ?? 0, 1 ); ?> /> <?php esc_html_e( 'Inclure dans quota', 'ufsc-clubs' ); ?></label>
        </div>
    </div>
    <div class="ufsc-form-actions">
        <button type="submit" class="ufsc-btn ufsc-btn-primary" onclick="document.getElementById('ufsc_submit_action').value='save';">
            <?php esc_html_e( 'Enregistrer', 'ufsc-clubs' ); ?>
        </button>
        <button type="submit" class="ufsc-btn ufsc-btn-secondary" onclick="document.getElementById('ufsc_submit_action').value='add_to_cart';">
            <?php esc_html_e( 'Ajouter au panier', 'ufsc-clubs' ); ?>
        </button>
    </div>
</form>
</div>
