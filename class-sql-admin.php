<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( class_exists( 'UFSC_SQL_Admin' ) ) {
    return;
}

require_once __DIR__ . '/includes/admin/class-sql-admin.php';
