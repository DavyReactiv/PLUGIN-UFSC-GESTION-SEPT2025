<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Feature flags for UFSC Gestion.
 */

/**
 * UFSC PATCH: Quotas disabled by default (feature flag).
 *
 * @return bool
 */
function ufsc_quotas_enabled() {
    return (bool) apply_filters( 'ufsc_quotas_enabled', false );
}
