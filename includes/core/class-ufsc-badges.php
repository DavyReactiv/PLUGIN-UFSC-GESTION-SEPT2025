<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * UFSC Badge Helper Class
 * Provides common badge rendering with consistent styling
 */
class UFSC_Badges {

    /**
     * Status mappings for normalized badge display
     */
    const STATUS_MAPPINGS = array(
        // Club statuses
        'club' => array(
            'valide' => array('type' => 'success', 'label' => 'Actif'),
            'a_regler' => array('type' => 'warning', 'label' => 'À régler'),
            'en_attente' => array('type' => 'info', 'label' => 'En attente'),
            'desactive' => array('type' => 'danger', 'label' => 'Désactivé'),
        ),
        // Licence statuses  
        'licence' => array(
            'valide' => array('type' => 'success', 'label' => 'Validée'),
            'a_regler' => array('type' => 'warning', 'label' => 'À régler'),
            'en_attente' => array('type' => 'info', 'label' => 'En attente'),
            'desactive' => array('type' => 'danger', 'label' => 'Désactivée'),
            'pending' => array('type' => 'info', 'label' => 'En attente'),
            'validated' => array('type' => 'success', 'label' => 'Validée'),
            'active' => array('type' => 'success', 'label' => 'Active'),
            'expired' => array('type' => 'warning', 'label' => 'Expirée'),
            'rejected' => array('type' => 'danger', 'label' => 'Rejetée'),
        ),
        // Generic statuses
        'generic' => array(
            'active' => array('type' => 'success', 'label' => 'Actif'),
            'inactive' => array('type' => 'danger', 'label' => 'Inactif'), 
            'pending' => array('type' => 'info', 'label' => 'En attente'),
            'warning' => array('type' => 'warning', 'label' => 'Attention'),
            'danger' => array('type' => 'danger', 'label' => 'Danger'),
        )
    );

    /**
     * Badge CSS classes mapping
     */
    const BADGE_CLASSES = array(
        'success' => 'u-badge u-badge--success',
        'warning' => 'u-badge u-badge--warning', 
        'danger' => 'u-badge u-badge--danger',
        'info' => 'u-badge u-badge--info',
        'neutral' => 'u-badge u-badge--neutral',
    );

    /**
     * Render a status badge with consistent styling
     * 
     * @param string $status The status value
     * @param string $context Context (club, licence, generic)
     * @param array $options Additional options (custom_label, custom_class)
     * @return string HTML badge
     */
    public static function render_status_badge( $status, $context = 'generic', $options = array() ) {
        // Get status mapping
        $mappings = isset( self::STATUS_MAPPINGS[$context] ) ? self::STATUS_MAPPINGS[$context] : self::STATUS_MAPPINGS['generic'];
        
        // Default values
        $type = 'neutral';
        $label = $status;
        
        // Get mapped values if available
        if ( isset( $mappings[$status] ) ) {
            $type = $mappings[$status]['type'];
            $label = $mappings[$status]['label'];
        }
        
        // Allow custom label override
        if ( isset( $options['custom_label'] ) ) {
            $label = $options['custom_label'];
        }
        
        // Get CSS class
        $css_class = isset( self::BADGE_CLASSES[$type] ) ? self::BADGE_CLASSES[$type] : self::BADGE_CLASSES['neutral'];
        
        // Allow custom class override
        if ( isset( $options['custom_class'] ) ) {
            $css_class = $options['custom_class'];
        }
        
        return '<span class="' . esc_attr( $css_class ) . '" data-status="' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Render a club status badge
     * 
     * @param string $status Club status
     * @param array $options Additional options
     * @return string HTML badge
     */
    public static function render_club_badge( $status, $options = array() ) {
        return self::render_status_badge( $status, 'club', $options );
    }

    /**
     * Render a licence status badge
     * 
     * @param string $status Licence status  
     * @param array $options Additional options
     * @return string HTML badge
     */
    public static function render_licence_badge( $status, $options = array() ) {
        return self::render_status_badge( $status, 'licence', $options );
    }

    /**
     * Get active status values for clubs
     * 
     * @return array Array of status values considered "active"
     */
    public static function get_active_club_statuses() {
        $active_statuses = array();
        foreach ( self::STATUS_MAPPINGS['club'] as $status => $config ) {
            if ( $config['type'] === 'success' ) {
                $active_statuses[] = $status;
            }
        }
        return $active_statuses;
    }

    /**
     * Get active status values for licences
     * 
     * @return array Array of status values considered "active"
     */
    public static function get_active_licence_statuses() {
        $active_statuses = array();
        foreach ( self::STATUS_MAPPINGS['licence'] as $status => $config ) {
            if ( $config['type'] === 'success' ) {
                $active_statuses[] = $status;
            }
        }
        return $active_statuses;
    }

    /**
     * Check if a status is considered "active" for clubs
     * 
     * @param string $status Status to check
     * @return bool True if status is active
     */
    public static function is_active_club_status( $status ) {
        return in_array( $status, self::get_active_club_statuses() );
    }

    /**
     * Check if a status is considered "active" for licences
     * 
     * @param string $status Status to check
     * @return bool True if status is active
     */
    public static function is_active_licence_status( $status ) {
        return in_array( $status, self::get_active_licence_statuses() );
    }

    /**
     * Initialize badge styles
     */
    public static function init_styles() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_styles' ) );
    }

    /**
     * Enqueue admin badge styles
     */
    public static function enqueue_admin_styles() {
        wp_add_inline_style( 'admin-styles', self::get_badge_css() );
    }

    /**
     * Enqueue frontend badge styles  
     */
    public static function enqueue_frontend_styles() {
        wp_add_inline_style( 'wp-block-library', self::get_badge_css() );
    }

    /**
     * Get badge CSS
     * 
     * @return string CSS styles
     */
    private static function get_badge_css() {
        return '
        .u-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            line-height: 1.6;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        
        .u-badge--success {
            background: #e7f7ed;
            color: #137d3b;
            border-color: #b8e6ca;
        }
        
        .u-badge--warning {
            background: #fff7e6;
            color: #8a5a00;
            border-color: #ffd699;
        }
        
        .u-badge--danger {
            background: #f6e6e6;
            color: #7d0013;
            border-color: #e6b3b3;
        }
        
        .u-badge--info {
            background: #e6f1ff;
            color: #1257a0;
            border-color: #cfe1ff;
        }
        
        .u-badge--neutral {
            background: #f6f7f7;
            color: #4a5568;
            border-color: #e2e8f0;
        }
        
        /* Legacy compatibility */
        .ufsc-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 12px;
            line-height: 1.6;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid transparent;
        }
        
        .ufsc-badge.badge-success, .badge-success {
            background: #e7f7ed;
            color: #137d3b;
            border-color: #b8e6ca;
        }
        
        .ufsc-badge.badge-warning, .badge-warning {
            background: #fff7e6;
            color: #8a5a00;
            border-color: #ffd699;
        }
        
        .ufsc-badge.badge-danger, .badge-danger {
            background: #f6e6e6;
            color: #7d0013;
            border-color: #e6b3b3;
        }
        
        .ufsc-badge.badge-info, .badge-info {
            background: #e6f1ff;
            color: #1257a0;
            border-color: #cfe1ff;
        }
        
        .ufsc-badge.badge-secondary, .badge-secondary {
            background: #f6f7f7;
            color: #4a5568;
            border-color: #e2e8f0;
        }
        ';
    }
}

// Initialize badge styles
UFSC_Badges::init_styles();