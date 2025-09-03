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
            'class' => 'ufsc-badge-success'
        ),
        'a_regler' => array(
            'label' => 'À régler',
            'class' => 'ufsc-badge-warning'
        ),
        'en_attente' => array(
            'label' => 'En attente',
            'class' => 'ufsc-badge-info'
        ),
        'desactive' => array(
            'label' => 'Désactivé',
            'class' => 'ufsc-badge-danger'
        )
    );

    /**
     * Region badge configurations
     */
    private static $region_badges = array(
        'default' => array(
            'class' => 'ufsc-badge-region'
        )
    );

    /**
     * Document badge configurations
     */
    private static $document_badges = array(
        'complete' => array(
            'label' => 'Complet',
            'class' => 'ufsc-badge-doc-complete'
        ),
        'partial' => array(
            'label' => 'Partiel',
            'class' => 'ufsc-badge-doc-partial'
        ),
        'missing' => array(
            'label' => 'Manquant',
            'class' => 'ufsc-badge-doc-missing'
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

}
