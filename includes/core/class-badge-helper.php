<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Badge Helper
 * Centralized badge rendering with consistent styling
 */
class UFSC_Badge_Helper {

    /**
     * Status badge configurations
     */
    private static $status_badges = array(
        'valide' => array(
            'label' => 'Validé',
            'class' => 'ufsc-badge-success',
            'color' => '#28a745',
            'text_color' => '#fff'
        ),
        'a_regler' => array(
            'label' => 'À régler',
            'class' => 'ufsc-badge-warning',
            'color' => '#ffc107',
            'text_color' => '#212529'
        ),
        'en_attente' => array(
            'label' => 'En attente',
            'class' => 'ufsc-badge-info',
            'color' => '#17a2b8',
            'text_color' => '#fff'
        ),
        'desactive' => array(
            'label' => 'Désactivé',
            'class' => 'ufsc-badge-danger',
            'color' => '#dc3545',
            'text_color' => '#fff'
        )
    );

    /**
     * Region badge configurations
     */
    private static $region_badges = array(
        'default' => array(
            'class' => 'ufsc-badge-region',
            'color' => '#0073aa',
            'text_color' => '#fff'
        )
    );

    /**
     * Document badge configurations
     */
    private static $document_badges = array(
        'complete' => array(
            'label' => 'Complet',
            'class' => 'ufsc-badge-doc-complete',
            'color' => '#28a745',
            'text_color' => '#fff'
        ),
        'partial' => array(
            'label' => 'Partiel',
            'class' => 'ufsc-badge-doc-partial',
            'color' => '#ffc107',
            'text_color' => '#212529'
        ),
        'missing' => array(
            'label' => 'Manquant',
            'class' => 'ufsc-badge-doc-missing',
            'color' => '#dc3545',
            'text_color' => '#fff'
        )
    );

    /**
     * Render status badge
     * 
     * @param string $status Status key
     * @param array $options Additional options
     * @return string Badge HTML
     */
    public static function render_status_badge( $status, $options = array() ) {
        $config = isset( self::$status_badges[ $status ] ) 
            ? self::$status_badges[ $status ] 
            : self::$status_badges['en_attente'];

        $label = isset( $options['label'] ) ? $options['label'] : $config['label'];
        $class = isset( $options['class'] ) ? $options['class'] : $config['class'];
        
        return sprintf(
            '<span class="ufsc-badge %s" data-status="%s">%s</span>',
            esc_attr( $class ),
            esc_attr( $status ),
            esc_html( $label )
        );
    }

    /**
     * Render region badge
     * 
     * @param string $region Region name
     * @param array $options Additional options
     * @return string Badge HTML
     */
    public static function render_region_badge( $region, $options = array() ) {
        $config = self::$region_badges['default'];
        $class = isset( $options['class'] ) ? $options['class'] : $config['class'];
        
        return sprintf(
            '<span class="ufsc-badge %s" data-region="%s">%s</span>',
            esc_attr( $class ),
            esc_attr( $region ),
            esc_html( $region )
        );
    }

    /**
     * Render document badge
     * 
     * @param string $status Document status (complete, partial, missing)
     * @param array $options Additional options
     * @return string Badge HTML
     */
    public static function render_document_badge( $status, $options = array() ) {
        $config = isset( self::$document_badges[ $status ] ) 
            ? self::$document_badges[ $status ] 
            : self::$document_badges['missing'];

        $label = isset( $options['label'] ) ? $options['label'] : $config['label'];
        $class = isset( $options['class'] ) ? $options['class'] : $config['class'];
        
        return sprintf(
            '<span class="ufsc-badge %s" data-doc-status="%s">%s</span>',
            esc_attr( $class ),
            esc_attr( $status ),
            esc_html( $label )
        );
    }

    /**
     * Get CSS for badges
     * 
     * @return string CSS code
     */
    public static function get_badge_css() {
        ob_start();
        ?>
        <style>
        .ufsc-badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 0.75em;
            font-weight: 600;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            border: 1px solid transparent;
            text-decoration: none;
        }
        
        /* Status badges */
        .ufsc-badge-success {
            background-color: <?php echo self::$status_badges['valide']['color']; ?>;
            color: <?php echo self::$status_badges['valide']['text_color']; ?>;
        }
        
        .ufsc-badge-warning {
            background-color: <?php echo self::$status_badges['a_regler']['color']; ?>;
            color: <?php echo self::$status_badges['a_regler']['text_color']; ?>;
        }
        
        .ufsc-badge-info {
            background-color: <?php echo self::$status_badges['en_attente']['color']; ?>;
            color: <?php echo self::$status_badges['en_attente']['text_color']; ?>;
        }
        
        .ufsc-badge-danger {
            background-color: <?php echo self::$status_badges['desactive']['color']; ?>;
            color: <?php echo self::$status_badges['desactive']['text_color']; ?>;
        }
        
        /* Region badges */
        .ufsc-badge-region {
            background-color: <?php echo self::$region_badges['default']['color']; ?>;
            color: <?php echo self::$region_badges['default']['text_color']; ?>;
        }
        
        /* Document badges */
        .ufsc-badge-doc-complete {
            background-color: <?php echo self::$document_badges['complete']['color']; ?>;
            color: <?php echo self::$document_badges['complete']['text_color']; ?>;
        }
        
        .ufsc-badge-doc-partial {
            background-color: <?php echo self::$document_badges['partial']['color']; ?>;
            color: <?php echo self::$document_badges['partial']['text_color']; ?>;
        }
        
        .ufsc-badge-doc-missing {
            background-color: <?php echo self::$document_badges['missing']['color']; ?>;
            color: <?php echo self::$document_badges['missing']['text_color']; ?>;
        }
        
        /* Hover effects */
        .ufsc-badge:hover {
            opacity: 0.8;
            text-decoration: none;
        }
        
        /* Size variations */
        .ufsc-badge-sm {
            padding: 0.2em 0.5em;
            font-size: 0.65em;
        }
        
        .ufsc-badge-lg {
            padding: 0.35em 0.8em;
            font-size: 0.85em;
        }
        </style>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue badge styles
     */
    public static function enqueue_styles() {
        add_action( 'wp_head', function() {
            echo self::get_badge_css();
        } );
        
        add_action( 'admin_head', function() {
            echo self::get_badge_css();
        } );
    }
}

// Initialize badge styles
add_action( 'init', array( 'UFSC_Badge_Helper', 'enqueue_styles' ) );