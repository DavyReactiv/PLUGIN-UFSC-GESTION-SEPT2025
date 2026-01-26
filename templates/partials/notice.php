<?php if ( ! empty( $_GET['ufsc_notice'] ) ): ?>
  <div class="notice notice-info ufsc-notice">
    <?php echo esc_html( ucfirst( str_replace( '_', ' ', $_GET['ufsc_notice'] ) ) ); ?>
  </div>
<?php endif; ?>
