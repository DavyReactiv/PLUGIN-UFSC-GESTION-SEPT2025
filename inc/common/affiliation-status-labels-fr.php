<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * French presentation labels for annual affiliation states.
 * Stored database values remain unchanged.
 */
final class UFSC_Affiliation_Status_Labels_FR {
	public static function init() {
		add_filter( 'gettext', array( __CLASS__, 'frontend_labels' ), 40, 3 );
		add_action( 'admin_footer', array( __CLASS__, 'admin_labels' ), 99 );
	}

	public static function frontend_labels( $translation, $text, $domain ) {
		if ( is_admin() || 'ufsc-clubs' !== $domain ) {
			return $translation;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		if ( false === strpos( $request_uri, '/compte-club/' ) && false === strpos( $request_uri, '/tableau-de-bord-club/' ) ) {
			return $translation;
		}
		$labels = array(
			'Paiement en attente'      => 'Règlement transmis — vérification UFSC en cours',
			'En attente de validation' => 'Règlement validé — affiliation à valider',
		);
		$source = (string) $text;
		return isset( $labels[ $source ] ) ? $labels[ $source ] : $translation;
	}

	/** Replace only visible technical affiliation keys on the UFSC clubs admin page. */
	public static function admin_labels() {
		if ( ! is_admin() || ! current_user_can( 'read' ) ) {
			return;
		}
		$page = isset( $_GET['page'] ) && ! is_array( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'ufsc-sql-clubs' !== $page ) {
			return;
		}
		?>
		<script>
		(function () {
			var root = document.querySelector('.ufsc-clubs-admin-page');
			if (!root || !document.createTreeWalker) return;
			var replacements = {
				'pending_payment': 'Règlement à vérifier',
				'pending_validation': 'Règlement validé — affiliation à valider',
				'a_renouveler': 'À renouveler',
				'correction_required': 'À corriger'
			};
			var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
			var nodes = [];
			while (walker.nextNode()) nodes.push(walker.currentNode);
			nodes.forEach(function (node) {
				var value = node.nodeValue || '';
				Object.keys(replacements).forEach(function (key) {
					if (value.indexOf(key) !== -1) value = value.split(key).join(replacements[key]);
				});
				node.nodeValue = value;
			});
		}());
		</script>
		<?php
	}
}

UFSC_Affiliation_Status_Labels_FR::init();
