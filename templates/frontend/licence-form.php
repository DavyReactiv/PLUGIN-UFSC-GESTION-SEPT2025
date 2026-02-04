<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
include UFSC_CL_DIR . 'templates/partials/notice.php';
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ufsc-licence-form">
	<input type="hidden" name="action" value="ufsc_save_licence" />
	<?php wp_nonce_field( 'ufsc_save_licence' ); ?>
	<input type="hidden" name="licence_id" value="<?php echo isset( $licence->id ) ? intval( $licence->id ) : 0; ?>" />

	<header class="ufsc-form-header">
		<h1 class="ufsc-form-title">
			<?php echo isset( $licence->id ) && $licence->id ? esc_html__( 'Modifier une licence', 'ufsc-clubs' ) : esc_html__( 'Ajouter une licence', 'ufsc-clubs' ); ?>
		</h1>
		<p class="ufsc-form-subtitle">
			<?php esc_html_e( 'Renseignez les informations du licencié puis validez.', 'ufsc-clubs' ); ?>
		</p>
	</header>

	<div class="ufsc-form-layout">
		<section class="ufsc-card">
			<h2 class="ufsc-card-title"><?php esc_html_e( 'Informations personnelles', 'ufsc-clubs' ); ?></h2>

			<div class="ufsc-grid">
				<div class="ufsc-field">
					<label for="prenom"><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></label>
					<input type="text" id="prenom" name="prenom" value="<?php echo esc_attr( $licence->prenom ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field">
					<label for="nom"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
					<input type="text" id="nom" name="nom" value="<?php echo esc_attr( $licence->nom ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field">
					<label for="email"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
					<input type="email" id="email" name="email" value="<?php echo esc_attr( $licence->email ?? '' ); ?>" />
				</div>

				<div class="ufsc-field">
					<label for="date_naissance"><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></label>
					<input type="date" id="date_naissance" name="date_naissance" value="<?php echo esc_attr( $licence->date_naissance ?? '' ); ?>" />
				</div>
			</div>
		</section>

		<section class="ufsc-card">
			<h2 class="ufsc-card-title"><?php esc_html_e( 'Rôle et activité', 'ufsc-clubs' ); ?></h2>

			<div class="ufsc-grid">
				<div class="ufsc-field">
					<label for="role"><?php esc_html_e( 'Rôle', 'ufsc-clubs' ); ?></label>
					<select id="role" name="role">
						<option value=""<?php selected( $licence->role ?? '', '' ); ?>><?php esc_html_e( 'Sélectionner', 'ufsc-clubs' ); ?></option>
						<option value="president"<?php selected( $licence->role ?? '', 'president' ); ?>><?php esc_html_e( 'Président', 'ufsc-clubs' ); ?></option>
						<option value="secretaire"<?php selected( $licence->role ?? '', 'secretaire' ); ?>><?php esc_html_e( 'Secrétaire', 'ufsc-clubs' ); ?></option>
						<option value="tresorier"<?php selected( $licence->role ?? '', 'tresorier' ); ?>><?php esc_html_e( 'Trésorier', 'ufsc-clubs' ); ?></option>
						<option value="entraineur"<?php selected( $licence->role ?? '', 'entraineur' ); ?>><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></option>
						<option value="adherent"<?php selected( $licence->role ?? '', 'adherent' ); ?>><?php esc_html_e( 'Adhérent', 'ufsc-clubs' ); ?></option>
					</select>
				</div>

				<div class="ufsc-field">
					<label class="ufsc-checkbox">
						<input type="checkbox" id="reduction_postier" name="reduction_postier" value="1" <?php checked( $licence->reduction_postier ?? 0, 1 ); ?> />
						<?php esc_html_e( 'Réduction postier', 'ufsc-clubs' ); ?>
					</label>
				</div>

				<div class="ufsc-field ufsc-field-identifiant-laposte" style="display:none;">
					<label for="identifiant_laposte"><?php esc_html_e( 'Identifiant La Poste', 'ufsc-clubs' ); ?></label>
					<input type="text" id="identifiant_laposte" name="identifiant_laposte" value="<?php echo esc_attr( $licence->identifiant_laposte ?? '' ); ?>" />
				</div>

				<div class="ufsc-field">
					<label class="ufsc-checkbox">
						<input type="checkbox" id="reduction_benevole" name="reduction_benevole" value="1" <?php checked( $licence->reduction_benevole ?? 0, 1 ); ?> />
						<?php esc_html_e( 'Réduction bénévole', 'ufsc-clubs' ); ?>
					</label>
				</div>

				<div class="ufsc-field">
					<label class="ufsc-checkbox">
						<input type="checkbox" id="licence_delegataire" name="licence_delegataire" value="1" <?php checked( $licence->licence_delegataire ?? 0, 1 ); ?> />
						<?php esc_html_e( 'Licence délégataire', 'ufsc-clubs' ); ?>
					</label>
				</div>

				<div class="ufsc-field ufsc-field-numero-delegataire" style="display:none;">
					<label for="numero_licence_delegataire"><?php esc_html_e( 'Numéro de licence délégataire', 'ufsc-clubs' ); ?></label>
					<input type="text" id="numero_licence_delegataire" name="numero_licence_delegataire" value="<?php echo esc_attr( $licence->numero_licence_delegataire ?? '' ); ?>" />
				</div>

				<div class="ufsc-field">
					<label for="note"><?php esc_html_e( 'Note', 'ufsc-clubs' ); ?></label>
					<textarea id="note" name="note" rows="3"><?php echo esc_textarea( $licence->note ?? '' ); ?></textarea>
				</div>
			</div>
		</section>
	</div>

	<div class="ufsc-form-actions">
		<button type="submit" class="ufsc-btn ufsc-btn-primary">
			<?php echo isset( $licence->id ) && $licence->id ? esc_html__( 'Mettre à jour', 'ufsc-clubs' ) : esc_html__( 'Créer', 'ufsc-clubs' ); ?>
		</button>
	</div>
</form>
