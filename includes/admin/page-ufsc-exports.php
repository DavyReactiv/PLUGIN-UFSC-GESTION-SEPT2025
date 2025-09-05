<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="wrap">
    <h1><?php echo esc_html__( 'Exports', 'ufsc-clubs' ); ?></h1>
    <div class="ufsc-export-section">
        <h2><?php echo esc_html__( 'Export Clubs', 'ufsc-clubs' ); ?></h2>
        <?php UFSC_Export_Clubs::render_form(); ?>
    </div>
    <hr/>
    <div class="ufsc-export-section">
        <h2><?php echo esc_html__( 'Export Licences', 'ufsc-clubs' ); ?></h2>
        <?php UFSC_Export_Licences::render_form(); ?>
    </div>
</div>
