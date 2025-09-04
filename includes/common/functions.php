<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function ufsc_redirect_with_notice( $url, $notice_slug ) {
    return add_query_arg( 'ufsc_notice', sanitize_key( $notice_slug ), $url );
}
