<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Compatibility wrapper: season logic now lives in inc/common/season.php.
 */
require_once __DIR__ . '/season.php';

if ( ! function_exists( 'ufsc_get_current_season_label_legacy' ) ) {
    /**
     * @deprecated 1.5.8 Use ufsc_get_current_season_label().
     */
    function ufsc_get_current_season_label_legacy() {
        _deprecated_function( __FUNCTION__, '1.5.8', 'ufsc_get_current_season_label' );
        return ufsc_get_current_season_label();
    }
}
