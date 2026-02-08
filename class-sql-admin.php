<?php
/**
 * UFSC - SQL Admin loader
 * (fichier bootstrap/loader : charge la vraie classe depuis /includes/admin/class-sql-admin.php)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Évite tout redeclare si un autre plugin/module charge déjà UFSC_SQL_Admin.
 * (ex: UFSC Licence Competition partage les mêmes tables)
 */
if ( class_exists( 'UFSC_SQL_Admin', false ) ) {
	return;
}

// Charge la classe réelle (définie une seule fois)
require_once __DIR__ . '/includes/admin/class-sql-admin.php';
