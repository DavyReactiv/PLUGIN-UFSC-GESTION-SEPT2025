<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
include UFSC_CL_DIR . 'templates/partials/notice.php';
$current_role = isset( $licence->role ) && '' !== (string) $licence->role ? (string) $licence->role : 'adherent';
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
				<div class="ufsc-field ufsc-field-role">
					<label for="role"><?php esc_html_e( 'Rôle / fonction au club', 'ufsc-clubs' ); ?></label>
					<select id="role" name="role" required>
						<option value="adherent"<?php selected( $current_role, 'adherent' ); ?>><?php esc_html_e( 'Adhérent / pratiquant', 'ufsc-clubs' ); ?></option>
						<option value="president"<?php selected( $current_role, 'president' ); ?>><?php esc_html_e( 'Président', 'ufsc-clubs' ); ?></option>
						<option value="secretaire"<?php selected( $current_role, 'secretaire' ); ?>><?php esc_html_e( 'Secrétaire', 'ufsc-clubs' ); ?></option>
						<option value="tresorier"<?php selected( $current_role, 'tresorier' ); ?>><?php esc_html_e( 'Trésorier', 'ufsc-clubs' ); ?></option>
						<option value="dirigeant"<?php selected( $current_role, 'dirigeant' ); ?>><?php esc_html_e( 'Dirigeant', 'ufsc-clubs' ); ?></option>
						<option value="entraineur"<?php selected( $current_role, 'entraineur' ); ?>><?php esc_html_e( 'Entraîneur', 'ufsc-clubs' ); ?></option>
						<option value="encadrant"<?php selected( $current_role, 'encadrant' ); ?>><?php esc_html_e( 'Encadrant', 'ufsc-clubs' ); ?></option>
						<option value="responsable_technique"<?php selected( $current_role, 'responsable_technique' ); ?>><?php esc_html_e( 'Responsable technique', 'ufsc-clubs' ); ?></option>
						<option value="instructeur"<?php selected( $current_role, 'instructeur' ); ?>><?php esc_html_e( 'Instructeur', 'ufsc-clubs' ); ?></option>
						<option value="coach"<?php selected( $current_role, 'coach' ); ?>><?php esc_html_e( 'Coach', 'ufsc-clubs' ); ?></option>
						<option value="educateur"<?php selected( $current_role, 'educateur' ); ?>><?php esc_html_e( 'Éducateur', 'ufsc-clubs' ); ?></option>
						<option value="enseignant"<?php selected( $current_role, 'enseignant' ); ?>><?php esc_html_e( 'Enseignant', 'ufsc-clubs' ); ?></option>
					</select>
					<small class="description"><?php esc_html_e( 'Le rôle détermine si les informations complémentaires de naissance sont nécessaires.', 'ufsc-clubs' ); ?></small>
				</div>

				<div class="ufsc-field">
					<label for="prenom"><?php esc_html_e( 'Prénom', 'ufsc-clubs' ); ?></label>
					<input type="text" id="prenom" name="prenom" value="<?php echo esc_attr( $licence->prenom ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field">
					<label for="nom"><?php esc_html_e( 'Nom', 'ufsc-clubs' ); ?></label>
					<input type="text" id="nom" name="nom" value="<?php echo esc_attr( $licence->nom ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field">
					<label for="date_naissance"><?php esc_html_e( 'Date de naissance', 'ufsc-clubs' ); ?></label>
					<input type="date" id="date_naissance" name="date_naissance" value="<?php echo esc_attr( $licence->date_naissance ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field">
					<label for="email"><?php esc_html_e( 'Email', 'ufsc-clubs' ); ?></label>
					<input type="email" id="email" name="email" value="<?php echo esc_attr( $licence->email ?? '' ); ?>" required />
				</div>

				<div class="ufsc-field ufsc-ffst-birthplace-field">
					<label for="ville_naissance"><?php esc_html_e( 'Ville de naissance', 'ufsc-clubs' ); ?></label>
					<input type="text" id="ville_naissance" name="ville_naissance" value="<?php echo esc_attr( $licence->ville_naissance ?? '' ); ?>" autocomplete="off" />
				</div>

				<div class="ufsc-field ufsc-ffst-birthplace-field">
					<label for="departement_naissance"><?php esc_html_e( 'Département de naissance', 'ufsc-clubs' ); ?></label>
					<input type="text" id="departement_naissance" name="departement_naissance" value="<?php echo esc_attr( $licence->departement_naissance ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'Ex. 03 – Allier', 'ufsc-clubs' ); ?>" autocomplete="off" />
				</div>

				<div class="ufsc-field ufsc-ffst-birthplace-field">
					<label for="pays_naissance"><?php esc_html_e( 'Pays de naissance', 'ufsc-clubs' ); ?></label>
					<input type="text" id="pays_naissance" name="pays_naissance" value="<?php echo esc_attr( $licence->pays_naissance ?? '' ); ?>" placeholder="<?php echo esc_attr__( 'France ou pays de naissance', 'ufsc-clubs' ); ?>" autocomplete="off" />
					<small class="description"><?php esc_html_e( 'Demandé uniquement pour les dirigeants, entraîneurs et encadrants concernés par les documents FFST.', 'ufsc-clubs' ); ?></small>
				</div>
			</div>
		</section>

		<section class="ufsc-card">
			<h2 class="ufsc-card-title"><?php esc_html_e( 'Activité et informations complémentaires', 'ufsc-clubs' ); ?></h2>

			<div class="ufsc-grid">
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

<script>
(function(){
	var role = document.getElementById('role');
	if (!role) return;
	var fields = Array.prototype.slice.call(document.querySelectorAll('.ufsc-ffst-birthplace-field'));
	var leaderRoles = ['president','secretaire','tresorier','dirigeant','entraineur','encadrant','responsable_technique','instructeur','coach','educateur','enseignant'];
	function refresh(){
		var required = leaderRoles.indexOf((role.value || '').toLowerCase()) !== -1;
		fields.forEach(function(field){
			field.style.display = required ? '' : 'none';
			field.setAttribute('aria-hidden', required ? 'false' : 'true');
			var input = field.querySelector('input');
			if (input) input.required = required;
		});
	}
	role.addEventListener('change', refresh);
	refresh();
})();
</script>