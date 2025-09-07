<?php if ( ! empty( $_GET['ufsc_notice'] ) ) :
    $ufsc_notice = sanitize_text_field( wp_unslash( $_GET['ufsc_notice'] ) );
    ?>
  <div class="notice notice-info ufsc-notice">
    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $ufsc_notice ) ) ); ?>
  </div>
<?php endif; ?>
