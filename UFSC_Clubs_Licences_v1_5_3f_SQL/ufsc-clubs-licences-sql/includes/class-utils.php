<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class UFSC_CL_Utils {
    public static function esc_badge( $label, $type='info' ){
        $type = sanitize_html_class($type);
        return '<span class="ufsc-badge ufsc-badge-'.$type.'">'.esc_html($label).'</span>';
    }
    public static function sanitize_text_arr( $arr ){
        $out = array();
        foreach( (array) $arr as $k=>$v ){ $out[$k] = is_array($v) ? self::sanitize_text_arr($v) : sanitize_text_field($v); }
        return $out;
    }
    public static function kpi_cards( $cards ){
        $html = '<div class="ufsc-cards">';
        foreach( $cards as $c ){
            $html .= '<div class="ufsc-card"><div class="ufsc-card-kpi">'.esc_html($c['value']).'</div><div class="ufsc-card-label">'.esc_html($c['label']).'</div></div>';
        }
        $html .= '</div>';
        return $html;
    }
    public static function regions(){
        $default = array(
            'UFSC AUVERGNE-RHONE-ALPES',
            'UFSC BOURGOGNE-FRANCHE-COMTE',
            'UFSC BRETAGNE',
            'UFSC CENTRE-VAL DE LOIRE',
            'UFSC GRAND EST',
            'UFSC HAUTS-DE-FRANCE',
            'UFSC ILE-DE-FRANCE',
            'UFSC NORMANDIE',
            'UFSC NOUVELLE-AQUITAINE',
            'UFSC OCCITANIE',
            'UFSC PAYS DE LA LOIRE',
            'UFSC PROVENCE-ALPES-COTE D\'AZUR',
            'UFSC DROM-COM'
        );
        return apply_filters( 'ufsc_regions_list', $default );
    }
}
