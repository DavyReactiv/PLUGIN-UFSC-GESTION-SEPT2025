<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Season-aware affiliation attestation helper.
 */

/**
 * Get affiliation attestation data for a club.
 *
 * @param int        $club_id Club ID.
 * @param object|nil $club    Optional club record kept for API compatibility.
 * @return array{url:string,attachment_id:int,status:string,can_view:bool,season:string}
 */
function ufsc_get_affiliation_attestation_data( $club_id, $club = null ) {
	unset( $club );
    $club_id = (int) $club_id;
	$current_season = class_exists( 'UFSC_Season_Service' ) ? UFSC_Season_Service::get_current_season() : ( function_exists( 'ufsc_get_current_season' ) ? ufsc_get_current_season() : '' );

    $can_view = current_user_can( 'manage_options' );
	if ( ! $can_view && function_exists( 'ufsc_is_club_affiliated_for_season' ) ) {
		$can_view = ufsc_is_club_affiliated_for_season( $club_id, $current_season );
    }

	$url = '';

	// Current attestations are seasonal. Permanent club fields/options are
	// intentionally not promoted into a new season.
	if ( class_exists( 'UFSC_PDF_Attestations' ) && '' !== $current_season ) {
		$url = UFSC_PDF_Attestations::get_attestation_for_club( $club_id, 'affiliation', $current_season );
	}

	return array(
		'url'           => $url ?: '',
		'attachment_id' => 0,
		'status'        => $url ? 'available' : ( $can_view ? 'required' : 'pending_validation' ),
		'can_view'      => (bool) $can_view,
		'season'        => $current_season,
	);

}

/** Return seasonal archives plus labelled legacy references for migration UI. */
function ufsc_get_affiliation_attestation_archives( $club_id ) {
	$club_id = absint( $club_id );
	$archives = class_exists( 'UFSC_PDF_Attestations' ) ? UFSC_PDF_Attestations::get_attestations_for_club( $club_id, 'affiliation' ) : array();
	foreach ( array( 'ufsc_club_doc_attestation_affiliation_', 'ufsc_club_doc_attestation_ufsc_', 'ufsc_attestation_' ) as $prefix ) {
		$value = get_option( $prefix . $club_id );
		$url = is_numeric( $value ) ? wp_get_attachment_url( absint( $value ) ) : ( is_string( $value ) ? esc_url_raw( $value ) : '' );
		if ( $url ) {
			$archives[] = (object) array( 'id' => 0, 'saison' => '', 'status' => 'legacy_unassigned', 'created_at' => '', 'download_url' => $url );
		}
	}
	return $archives;
}

/**
 * -------------------------------------------------------------------------
 * Honorability workflow - club portal
 * -------------------------------------------------------------------------
 *
 * The canonical document storage and decision workflow live in compliance.php
 * and class-unified-handlers.php. This layer only provides a clear club-facing
 * presentation and never creates a second honorability data model.
 */

/** Return the current UFSC season label used by the honorability workflow. */
function ufsc_get_honorability_current_season() {
	if ( class_exists( 'UFSC_Season_Service' ) ) {
		return (string) UFSC_Season_Service::get_current_season();
	}
	return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
}

/**
 * URL of the official fillable honorability template.
 *
 * The PDF is intentionally kept outside the plugin so a new legal/document
 * version can be published without deploying PHP code. Configure the URL with
 * the `ufsc_honorability_template_url` option or the filter of the same name.
 */
function ufsc_get_honorability_template_url() {
	$url = (string) get_option( 'ufsc_honorability_template_url', '' );
	return esc_url_raw( apply_filters( 'ufsc_honorability_template_url', $url ) );
}

/** Human-readable status metadata for the club portal. */
function ufsc_get_honorability_status_meta( $status ) {
	$status = sanitize_key( (string) $status );
	$map = array(
		'missing'             => array( 'label' => __( 'À fournir', 'ufsc-clubs' ), 'class' => 'missing' ),
		'pending'             => array( 'label' => __( 'Déposée — vérification UFSC', 'ufsc-clubs' ), 'class' => 'pending' ),
		'validated'           => array( 'label' => __( 'Validée', 'ufsc-clubs' ), 'class' => 'validated' ),
		'correction_required' => array( 'label' => __( 'À corriger', 'ufsc-clubs' ), 'class' => 'correction' ),
		'rejected'            => array( 'label' => __( 'Refusée', 'ufsc-clubs' ), 'class' => 'rejected' ),
		'expired'             => array( 'label' => __( 'À renouveler', 'ufsc-clubs' ), 'class' => 'expired' ),
	);
	return $map[ $status ] ?? array( 'label' => ucfirst( str_replace( '_', ' ', $status ?: 'missing' ) ), 'class' => 'missing' );
}

/** Human-readable club role label. */
function ufsc_get_honorability_role_label( $role ) {
	$role = function_exists( 'ufsc_normalize_club_role' ) ? ufsc_normalize_club_role( $role ) : sanitize_key( (string) $role );
	$labels = array(
		'president'              => __( 'Président(e)', 'ufsc-clubs' ),
		'secretaire'             => __( 'Secrétaire', 'ufsc-clubs' ),
		'tresorier'              => __( 'Trésorier(ère)', 'ufsc-clubs' ),
		'dirigeant'              => __( 'Dirigeant(e)', 'ufsc-clubs' ),
		'entraineur'             => __( 'Entraîneur', 'ufsc-clubs' ),
		'coach'                  => __( 'Coach', 'ufsc-clubs' ),
		'educateur'              => __( 'Éducateur / éducatrice', 'ufsc-clubs' ),
		'encadrant'              => __( 'Encadrant(e)', 'ufsc-clubs' ),
		'responsable_technique'  => __( 'Responsable technique', 'ufsc-clubs' ),
		'arbitre'                => __( 'Arbitre', 'ufsc-clubs' ),
		'officiel'               => __( 'Officiel', 'ufsc-clubs' ),
	);
	return $labels[ $role ] ?? ucfirst( str_replace( '_', ' ', $role ) );
}

/**
 * Get current-season licences belonging to the club and requiring honorability.
 * No historical document is promoted to the active season.
 */
function ufsc_get_club_honorability_licences( $club_id, $season = '' ) {
	global $wpdb;

	$club_id = absint( $club_id );
	$season  = $season ?: ufsc_get_honorability_current_season();
	if ( ! $club_id || '' === $season || ! class_exists( 'UFSC_SQL' ) ) {
		return array();
	}

	$settings = UFSC_SQL::get_settings();
	$table    = isset( $settings['table_licences'] ) ? $settings['table_licences'] : '';
	if ( ! $table ) {
		return array();
	}

	$season_context = function_exists( 'ufsc_get_pack_season_storage_context' )
		? ufsc_get_pack_season_storage_context( $table, $season )
		: array( 'column' => '', 'value' => '' );
	if ( empty( $season_context['column'] ) ) {
		return array();
	}

	$season_column = $season_context['column'];
	$season_value  = $season_context['value'];
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, club_id, nom, prenom, role FROM `{$table}` WHERE club_id = %d AND `{$season_column}` = %s ORDER BY nom ASC, prenom ASC",
			$club_id,
			$season_value
		)
	);

	return array_values( array_filter( (array) $rows, function( $row ) {
		return function_exists( 'ufsc_role_requires_honorability' ) && ufsc_role_requires_honorability( $row->role ?? '' );
	} ) );
}

/** Render the concise instructions shown once above all persons concerned. */
function ufsc_render_honorability_instructions() {
	$template_url = ufsc_get_honorability_template_url();
	ob_start();
	?>
	<div class="ufsc-honorability-help">
		<div class="ufsc-honorability-help__step"><strong>1</strong><span><?php esc_html_e( 'Téléchargez l’attestation UFSC.', 'ufsc-clubs' ); ?></span></div>
		<div class="ufsc-honorability-help__step"><strong>2</strong><span><?php esc_html_e( 'Remplissez-la sur ordinateur et signez-la. Vous pouvez aussi l’imprimer et la signer à la main.', 'ufsc-clubs' ); ?></span></div>
		<div class="ufsc-honorability-help__step"><strong>3</strong><span><?php esc_html_e( 'Enregistrez le document signé en PDF, JPG ou PNG.', 'ufsc-clubs' ); ?></span></div>
		<div class="ufsc-honorability-help__step"><strong>4</strong><span><?php esc_html_e( 'Déposez-le ci-dessous sur le profil de la personne concernée.', 'ufsc-clubs' ); ?></span></div>
		<?php if ( $template_url ) : ?>
			<p class="ufsc-honorability-template"><a class="ufsc-honorability-btn ufsc-honorability-btn--primary" href="<?php echo esc_url( $template_url ); ?>" target="_blank" rel="noopener" download><?php esc_html_e( 'Télécharger l’attestation remplissable', 'ufsc-clubs' ); ?></a></p>
		<?php else : ?>
			<p class="ufsc-honorability-template ufsc-honorability-template--missing"><?php esc_html_e( 'Le modèle officiel doit encore être publié dans les réglages UFSC avant l’ouverture aux clubs.', 'ufsc-clubs' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** Render one person/document card. */
function ufsc_render_honorability_person_card( $licence, $season ) {
	$licence_id = absint( $licence->id ?? 0 );
	$club_id    = absint( $licence->club_id ?? 0 );
	$record     = function_exists( 'ufsc_get_honorability_document' ) ? ufsc_get_honorability_document( $licence_id, $season ) : array( 'status' => 'missing' );
	$status     = ufsc_get_honorability_status_meta( $record['status'] ?? 'missing' );
	$name       = trim( (string) ( $licence->prenom ?? '' ) . ' ' . (string) ( $licence->nom ?? '' ) );
	$role       = ufsc_get_honorability_role_label( $licence->role ?? '' );
	$attachment = absint( $record['attachment_id'] ?? 0 );
	$file_url   = $attachment ? wp_get_attachment_url( $attachment ) : '';
	$can_upload = in_array( sanitize_key( (string) ( $record['status'] ?? 'missing' ) ), array( 'missing', 'correction_required', 'rejected', 'expired' ), true );

	ob_start();
	?>
	<article class="ufsc-honorability-person">
		<div class="ufsc-honorability-person__heading">
			<div>
				<h4><?php echo esc_html( $name ?: sprintf( __( 'Licence #%d', 'ufsc-clubs' ), $licence_id ) ); ?></h4>
				<p><?php echo esc_html( $role ); ?> · <?php echo esc_html( $season ); ?></p>
			</div>
			<span class="ufsc-honorability-status ufsc-honorability-status--<?php echo esc_attr( $status['class'] ); ?>"><?php echo esc_html( $status['label'] ); ?></span>
		</div>

		<?php if ( ! empty( $record['reason'] ) && in_array( $record['status'], array( 'correction_required', 'rejected' ), true ) ) : ?>
			<div class="ufsc-honorability-reason"><strong><?php esc_html_e( 'Retour UFSC :', 'ufsc-clubs' ); ?></strong> <?php echo esc_html( $record['reason'] ); ?></div>
		<?php endif; ?>

		<div class="ufsc-honorability-person__actions">
			<?php if ( $file_url ) : ?>
				<a class="ufsc-honorability-btn" href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Voir le document déposé', 'ufsc-clubs' ); ?></a>
			<?php endif; ?>

			<?php if ( $can_upload ) : ?>
				<form class="ufsc-honorability-upload" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="ufsc_upload_honorability_attestation">
					<input type="hidden" name="licence_id" value="<?php echo esc_attr( $licence_id ); ?>">
					<input type="hidden" name="club_id" value="<?php echo esc_attr( $club_id ); ?>">
					<?php wp_nonce_field( 'ufsc_honorability_attestation_' . $licence_id ); ?>
					<label>
						<span><?php echo 'missing' === ( $record['status'] ?? 'missing' ) ? esc_html__( 'Déposer l’attestation signée', 'ufsc-clubs' ) : esc_html__( 'Remplacer par un document corrigé', 'ufsc-clubs' ); ?></span>
						<input type="file" name="honorability_attestation" accept="application/pdf,image/jpeg,image/png" required>
					</label>
					<button class="ufsc-honorability-btn ufsc-honorability-btn--primary" type="submit"><?php esc_html_e( 'Envoyer à l’UFSC', 'ufsc-clubs' ); ?></button>
				</form>
			<?php elseif ( 'pending' === ( $record['status'] ?? '' ) ) : ?>
				<p class="ufsc-honorability-note"><?php esc_html_e( 'Aucune action nécessaire pour le moment. L’UFSC vérifie le document.', 'ufsc-clubs' ); ?></p>
			<?php elseif ( 'validated' === ( $record['status'] ?? '' ) ) : ?>
				<p class="ufsc-honorability-note ufsc-honorability-note--ok"><?php esc_html_e( 'Document conforme pour cette saison. Il reste archivé sur ce profil.', 'ufsc-clubs' ); ?></p>
			<?php endif; ?>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/** Main club-facing honorability panel. */
function ufsc_render_club_honorability_panel() {
	if ( ! is_user_logged_in() || ! function_exists( 'ufsc_get_user_club_id' ) ) {
		return '';
	}

	$club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
	if ( ! $club_id ) {
		return '';
	}

	$season   = ufsc_get_honorability_current_season();
	$licences = ufsc_get_club_honorability_licences( $club_id, $season );
	if ( empty( $licences ) ) {
		return '';
	}

	$complete = 0;
	foreach ( $licences as $licence ) {
		$record = ufsc_get_honorability_document( $licence->id, $season );
		if ( 'validated' === ( $record['status'] ?? '' ) ) {
			$complete++;
		}
	}

	ob_start();
	?>
	<section id="ufsc-honorability-documents" class="ufsc-honorability-panel" aria-labelledby="ufsc-honorability-title">
		<div class="ufsc-honorability-panel__header">
			<div>
				<p class="ufsc-honorability-eyebrow"><?php esc_html_e( 'Documents réglementaires', 'ufsc-clubs' ); ?></p>
				<h3 id="ufsc-honorability-title"><?php esc_html_e( 'Attestations d’honorabilité', 'ufsc-clubs' ); ?></h3>
				<p><?php esc_html_e( 'Une attestation signée est suivie séparément pour chaque personne concernée et pour chaque saison.', 'ufsc-clubs' ); ?></p>
			</div>
			<div class="ufsc-honorability-progress"><strong><?php echo esc_html( $complete ); ?>/<?php echo esc_html( count( $licences ) ); ?></strong><span><?php esc_html_e( 'validée(s)', 'ufsc-clubs' ); ?></span></div>
		</div>
		<?php echo ufsc_render_honorability_instructions(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<div class="ufsc-honorability-list">
			<?php foreach ( $licences as $licence ) : ?>
				<?php echo ufsc_render_honorability_person_card( $licence, $season ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endforeach; ?>
		</div>
	</section>
	<style>
	.ufsc-honorability-panel{margin:28px 0;padding:24px;border:1px solid #e6e1da;border-radius:16px;background:#fff;box-shadow:0 8px 28px rgba(25,31,60,.06)}
	.ufsc-honorability-panel *{box-sizing:border-box}.ufsc-honorability-panel__header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.ufsc-honorability-panel h3{margin:2px 0 6px;color:#292668;font-size:1.45rem}.ufsc-honorability-panel__header p{margin:0;color:#5f6470}.ufsc-honorability-eyebrow{font-size:.78rem!important;text-transform:uppercase;letter-spacing:.08em;color:#b48755!important;font-weight:700}.ufsc-honorability-progress{min-width:86px;padding:10px 14px;text-align:center;border-radius:12px;background:#f4f2f8;color:#292668}.ufsc-honorability-progress strong{display:block;font-size:1.15rem}.ufsc-honorability-progress span{display:block;font-size:.75rem}.ufsc-honorability-help{margin:20px 0;padding:16px;border-radius:12px;background:#f8f6f3;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px 16px}.ufsc-honorability-help__step{display:flex;gap:9px;align-items:flex-start;color:#353946;font-size:.9rem}.ufsc-honorability-help__step strong{display:inline-flex;align-items:center;justify-content:center;flex:0 0 24px;height:24px;border-radius:50%;background:#b48755;color:#fff}.ufsc-honorability-template{grid-column:1/-1;margin:5px 0 0}.ufsc-honorability-template--missing{padding:9px 11px;border-left:3px solid #b48755;background:#fff;color:#6c553e;font-size:.85rem}.ufsc-honorability-list{display:grid;gap:12px}.ufsc-honorability-person{padding:16px;border:1px solid #e7e9ef;border-radius:12px}.ufsc-honorability-person__heading{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.ufsc-honorability-person h4{margin:0;color:#242452;font-size:1rem}.ufsc-honorability-person__heading p{margin:3px 0 0;color:#6a6f7c;font-size:.84rem}.ufsc-honorability-status{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:.76rem;font-weight:700;white-space:nowrap}.ufsc-honorability-status--missing,.ufsc-honorability-status--expired{background:#fff3d8;color:#755412}.ufsc-honorability-status--pending{background:#e9efff;color:#294a96}.ufsc-honorability-status--validated{background:#e8f7ec;color:#236436}.ufsc-honorability-status--correction,.ufsc-honorability-status--rejected{background:#fde9ea;color:#8b2430}.ufsc-honorability-person__actions{margin-top:13px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}.ufsc-honorability-upload{display:flex;gap:10px;align-items:end;flex-wrap:wrap;width:100%}.ufsc-honorability-upload label{display:grid;gap:5px;flex:1 1 300px;color:#343846;font-size:.82rem;font-weight:600}.ufsc-honorability-upload input[type=file]{width:100%;padding:8px;border:1px solid #d9dce5;border-radius:8px;background:#fff}.ufsc-honorability-btn{display:inline-flex;align-items:center;justify-content:center;min-height:38px;padding:8px 13px;border:1px solid #292668;border-radius:8px;background:#fff;color:#292668!important;text-decoration:none!important;font-size:.84rem;font-weight:700;cursor:pointer}.ufsc-honorability-btn--primary{background:#292668;color:#fff!important}.ufsc-honorability-reason{margin-top:12px;padding:10px 12px;border-radius:8px;background:#fff1f2;color:#7b2731;font-size:.85rem}.ufsc-honorability-note{margin:0;color:#5f6470;font-size:.86rem}.ufsc-honorability-note--ok{color:#236436}@media(max-width:720px){.ufsc-honorability-panel{padding:16px}.ufsc-honorability-panel__header,.ufsc-honorability-person__heading{align-items:stretch;flex-direction:column}.ufsc-honorability-progress{align-self:flex-start}.ufsc-honorability-help{grid-template-columns:1fr}.ufsc-honorability-upload{align-items:stretch}.ufsc-honorability-btn{width:100%}}
	</style>
	<?php
	return ob_get_clean();
}

/** Dedicated shortcode, useful on a future Documents clubs page if desired. */
function ufsc_register_honorability_documents_shortcode() {
	add_shortcode( 'ufsc_honorability_documents', 'ufsc_render_club_honorability_panel' );
}
add_action( 'init', 'ufsc_register_honorability_documents_shortcode', 30 );

/**
 * Keep the current Compte Club page practical without requiring an Elementor
 * edit: append the panel once after the existing club-profile shortcode.
 */
function ufsc_append_honorability_to_club_profile_shortcode( $output, $tag, $attr, $m ) {
	unset( $attr, $m );
	if ( 'ufsc_club_profile' !== $tag || false !== strpos( $output, 'id="ufsc-honorability-documents"' ) ) {
		return $output;
	}
	return $output . ufsc_render_club_honorability_panel();
}
add_filter( 'do_shortcode_tag', 'ufsc_append_honorability_to_club_profile_shortcode', 30, 4 );
