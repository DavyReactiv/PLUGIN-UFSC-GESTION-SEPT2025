<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Front-office statistics presentation for the club dashboard.
 *
 * Statistics are intentionally restricted to licences officially validated by
 * UFSC. Drafts, pending requests, corrections and unpaid dossiers are excluded
 * from demographic and sporting distributions.
 *
 * This module is read-only and does not mutate licence, quota or payment data.
 */

/** Return current UFSC season. */
function ufsc_stats_v2_current_season() {
    if ( class_exists( 'UFSC_Season_Service' ) ) {
        return (string) UFSC_Season_Service::get_current_season();
    }
    return function_exists( 'ufsc_get_current_season' ) ? (string) ufsc_get_current_season() : '';
}

/** Fetch the exact current-season licence rows for one club. */
function ufsc_stats_v2_rows( $club_id, $season ) {
    global $wpdb;

    $club_id = absint( $club_id );
    if ( $club_id < 1 || ! function_exists( 'ufsc_get_licences_table' ) ) {
        return array();
    }

    $table   = ufsc_get_licences_table();
    $columns = function_exists( 'ufsc_table_columns' ) ? (array) ufsc_table_columns( $table ) : (array) $wpdb->get_col( "DESCRIBE `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $where   = $wpdb->prepare( 'club_id = %d', $club_id );

    if ( in_array( 'deleted_at', $columns, true ) ) {
        $where .= " AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')";
    }

    $season_column = function_exists( 'ufsc_get_detected_season_column' ) ? ufsc_get_detected_season_column( $table ) : '';
    if ( '' === $season_column ) {
        foreach ( array( 'paid_season', 'season', 'saison', 'season_end_year' ) as $candidate ) {
            if ( in_array( $candidate, $columns, true ) ) {
                $season_column = $candidate;
                break;
            }
        }
    }

    if ( '' !== $season ) {
        if ( 'season_end_year' === $season_column && preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ) {
            $where .= $wpdb->prepare( ' AND season_end_year = %d', (int) $matches[1] );
        } elseif ( $season_column ) {
            $where .= $wpdb->prepare( " AND REPLACE(TRIM(`{$season_column}`), '/', '-') = %s", str_replace( '/', '-', $season ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            return array();
        }
    }

    return (array) $wpdb->get_results( "SELECT * FROM `{$table}` WHERE {$where} ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

/** Determine whether a licence is officially validated using the canonical state. */
function ufsc_stats_v2_is_validated( $row ) {
    if ( function_exists( 'ufsc_resolve_licence_business_state' ) ) {
        $business = (array) ufsc_resolve_licence_business_state( $row );
        return ! empty( $business['official'] );
    }

    $raw = function_exists( 'ufsc_get_licence_status_raw' )
        ? ufsc_get_licence_status_raw( $row )
        : ( $row->statut ?? ( $row->status ?? '' ) );
    $status = function_exists( 'ufsc_get_licence_status_norm' )
        ? ufsc_get_licence_status_norm( $raw )
        : sanitize_key( (string) $raw );

    return in_array( $status, array( 'valide', 'validated', 'active' ), true );
}

/** Build presentation statistics from validated licences only. */
function ufsc_stats_v2_aggregate( array $rows ) {
    $stats = array(
        'total'       => 0,
        'included'    => 0,
        'supplement'  => 0,
        'gender'      => array( 'M' => 0, 'F' => 0, 'unknown' => 0 ),
        'practice'    => array( 'leisure' => 0, 'competition' => 0, 'unknown' => 0 ),
        'age_range'   => array( 'under_12' => 0, '12_17' => 0, '18_40' => 0, '41_plus' => 0, 'unknown' => 0 ),
        'birth_years' => array(),
    );

    $today     = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );
    $reference = new DateTimeImmutable( $today );

    foreach ( $rows as $row ) {
        $official = ufsc_stats_v2_is_validated( $row );
        if ( ! $official ) {
            continue;
        }

        $stats['total']++;
        if ( ! empty( $row->is_included ) ) {
            $stats['included']++;
        } else {
            $stats['supplement']++;
        }

        $gender = strtoupper( trim( (string) ( $row->sexe ?? '' ) ) );
        if ( in_array( $gender, array( 'M', 'H', 'HOMME' ), true ) ) {
            $stats['gender']['M']++;
        } elseif ( in_array( $gender, array( 'F', 'FEMME' ), true ) ) {
            $stats['gender']['F']++;
        } else {
            $stats['gender']['unknown']++;
        }

        if ( ! isset( $row->competition ) || null === $row->competition || '' === (string) $row->competition ) {
            $stats['practice']['unknown']++;
        } elseif ( 1 === (int) $row->competition ) {
            $stats['practice']['competition']++;
        } else {
            $stats['practice']['leisure']++;
        }

        $birth_raw = trim( (string) ( $row->date_naissance ?? '' ) );
        $birth     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $birth_raw ) ? DateTimeImmutable::createFromFormat( '!Y-m-d', $birth_raw ) : false;
        if ( ! $birth || $birth->format( 'Y-m-d' ) !== $birth_raw || $birth > $reference ) {
            $stats['age_range']['unknown']++;
            continue;
        }

        $age = $birth->diff( $reference )->y;
        if ( $age < 12 ) {
            $stats['age_range']['under_12']++;
        } elseif ( $age < 18 ) {
            $stats['age_range']['12_17']++;
        } elseif ( $age <= 40 ) {
            $stats['age_range']['18_40']++;
        } else {
            $stats['age_range']['41_plus']++;
        }

        $year = $birth->format( 'Y' );
        $stats['birth_years'][ $year ] = ( $stats['birth_years'][ $year ] ?? 0 ) + 1;
    }

    ksort( $stats['birth_years'], SORT_NUMERIC );
    return $stats;
}

/** Render one small metric card. */
function ufsc_stats_v2_metric( $label, $value, $help = '' ) {
    return '<div class="ufsc-stats-v2-card"><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $label ) . '</span>'
        . ( '' !== $help ? '<small>' . esc_html( $help ) . '</small>' : '' ) . '</div>';
}

/** Render one compact distribution row. */
function ufsc_stats_v2_bar( $label, $value, $total ) {
    $percent = $total > 0 ? min( 100, round( ( $value / $total ) * 100, 1 ) ) : 0;
    return '<div class="ufsc-stats-v2-bar"><div class="ufsc-stats-v2-bar-head"><span>' . esc_html( $label ) . '</span><strong>'
        . esc_html( (string) $value ) . '</strong></div><div class="ufsc-stats-v2-track"><span style="width:' . esc_attr( (string) $percent ) . '%"></span></div></div>';
}

/** Build the replacement statistics panel. */
function ufsc_stats_v2_panel( $club_id, $season ) {
    $rows  = ufsc_stats_v2_rows( $club_id, $season );
    $stats = ufsc_stats_v2_aggregate( $rows );
    $total = max( 0, (int) $stats['total'] );

    $html  = '<section class="ufsc-stats-v2" aria-label="' . esc_attr__( 'Statistiques des licences validées', 'ufsc-clubs' ) . '">';
    $html .= '<div class="ufsc-stats-v2-heading"><div><h3>' . esc_html__( 'Profil des licenciés validés', 'ufsc-clubs' ) . '</h3><p>';
    $html .= esc_html( sprintf( __( 'Saison %s — seules les licences officiellement validées par l’UFSC sont prises en compte.', 'ufsc-clubs' ), $season ) );
    $html .= '</p></div><span class="ufsc-stats-v2-season">' . esc_html( $season ) . '</span></div>';

    $html .= '<div class="ufsc-stats-v2-grid">';
    $html .= ufsc_stats_v2_metric( __( 'Licences validées', 'ufsc-clubs' ), $stats['total'], __( 'Brouillons et dossiers en attente exclus', 'ufsc-clubs' ) );
    $html .= ufsc_stats_v2_metric( __( 'Validées incluses dans le pack', 'ufsc-clubs' ), $stats['included'], __( 'Parmi les licences validées', 'ufsc-clubs' ) );
    $html .= ufsc_stats_v2_metric( __( 'Validées supplémentaires', 'ufsc-clubs' ), $stats['supplement'], __( 'Au-delà du quota inclus', 'ufsc-clubs' ) );
    $html .= '</div>';

    if ( 0 === $total ) {
        $html .= '<div class="ufsc-stats-v2-empty"><strong>' . esc_html__( 'Aucune licence validée pour cette saison.', 'ufsc-clubs' ) . '</strong><span>'
            . esc_html__( 'Les brouillons, licences en attente de validation ou à corriger ne sont volontairement pas comptés dans ces statistiques.', 'ufsc-clubs' )
            . '</span></div>';
        $html .= '</section>';
        return $html;
    }

    $html .= '<div class="ufsc-stats-v2-columns">';
    $html .= '<div class="ufsc-stats-v2-box"><h4>' . esc_html__( 'Répartition des licences validées', 'ufsc-clubs' ) . '</h4>';
    $html .= '<h5>' . esc_html__( 'Sexe', 'ufsc-clubs' ) . '</h5>';
    $html .= ufsc_stats_v2_bar( __( 'Hommes', 'ufsc-clubs' ), $stats['gender']['M'], $total );
    $html .= ufsc_stats_v2_bar( __( 'Femmes', 'ufsc-clubs' ), $stats['gender']['F'], $total );
    if ( $stats['gender']['unknown'] > 0 ) {
        $html .= ufsc_stats_v2_bar( __( 'Non renseigné', 'ufsc-clubs' ), $stats['gender']['unknown'], $total );
    }
    $html .= '<h5>' . esc_html__( 'Pratique', 'ufsc-clubs' ) . '</h5>';
    $html .= ufsc_stats_v2_bar( __( 'Loisir', 'ufsc-clubs' ), $stats['practice']['leisure'], $total );
    $html .= ufsc_stats_v2_bar( __( 'Compétition', 'ufsc-clubs' ), $stats['practice']['competition'], $total );
    if ( $stats['practice']['unknown'] > 0 ) {
        $html .= ufsc_stats_v2_bar( __( 'Non renseigné', 'ufsc-clubs' ), $stats['practice']['unknown'], $total );
    }
    $html .= '</div>';

    $html .= '<div class="ufsc-stats-v2-box"><h4>' . esc_html__( 'Âges et années de naissance', 'ufsc-clubs' ) . '</h4>';
    $html .= ufsc_stats_v2_bar( __( 'Moins de 12 ans', 'ufsc-clubs' ), $stats['age_range']['under_12'], $total );
    $html .= ufsc_stats_v2_bar( __( '12–17 ans', 'ufsc-clubs' ), $stats['age_range']['12_17'], $total );
    $html .= ufsc_stats_v2_bar( __( '18–40 ans', 'ufsc-clubs' ), $stats['age_range']['18_40'], $total );
    $html .= ufsc_stats_v2_bar( __( '41 ans et +', 'ufsc-clubs' ), $stats['age_range']['41_plus'], $total );
    if ( $stats['age_range']['unknown'] > 0 ) {
        $html .= ufsc_stats_v2_bar( __( 'Date non renseignée', 'ufsc-clubs' ), $stats['age_range']['unknown'], $total );
    }

    if ( ! empty( $stats['birth_years'] ) ) {
        $max_year_count = max( $stats['birth_years'] );
        $html .= '<div class="ufsc-stats-v2-years"><h5>' . esc_html__( 'Détail par année de naissance', 'ufsc-clubs' ) . '</h5>';
        foreach ( $stats['birth_years'] as $year => $count ) {
            $html .= ufsc_stats_v2_bar( (string) $year, $count, max( 1, $max_year_count ) );
        }
        $html .= '</div>';
    }
    $html .= '</div></div>';

    $html .= '<p class="ufsc-stats-v2-note"><strong>' . esc_html__( 'Règle de calcul :', 'ufsc-clubs' ) . '</strong> '
        . esc_html__( 'Une licence apparaît dans ces statistiques uniquement après validation officielle UFSC. Les brouillons et dossiers en attente restent visibles dans le suivi des licences, mais pas dans le profil statistique.', 'ufsc-clubs' )
        . '</p>';
    $html .= '</section>';

    return $html;
}

/** Replace legacy charts with the validated-only compact current-season view. */
function ufsc_stats_v2_filter_dashboard( $output, $tag, $attr, $m ) {
    unset( $attr, $m );
    if ( 'ufsc_club_dashboard' !== $tag || ! is_user_logged_in() ) {
        return $output;
    }
    if ( false === strpos( $output, 'ufsc-licence-chart' ) || ! function_exists( 'ufsc_get_user_club_id' ) ) {
        return $output;
    }

    $club_id = absint( ufsc_get_user_club_id( get_current_user_id() ) );
    if ( $club_id < 1 ) {
        return $output;
    }

    $season = ufsc_stats_v2_current_season();
    $panel  = ufsc_stats_v2_panel( $club_id, $season );
    $style  = '<style id="ufsc-stats-v2-css">'
        . '.ufsc-stats-chart{display:none!important}.ufsc-stats-v2{margin:18px 0;padding:18px;border:1px solid #dce4ee;border-radius:14px;background:#fff}.ufsc-stats-v2-heading{display:flex;gap:16px;justify-content:space-between;align-items:flex-start;margin-bottom:16px}.ufsc-stats-v2-heading h3{margin:0 0 5px;font-size:20px}.ufsc-stats-v2-heading p{margin:0;color:#475569}.ufsc-stats-v2-season{white-space:nowrap;border:1px solid #cbd5e1;border-radius:999px;padding:5px 10px;font-weight:600}.ufsc-stats-v2-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.ufsc-stats-v2-card{padding:14px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;min-width:0}.ufsc-stats-v2-card strong{display:block;font-size:24px;line-height:1.1}.ufsc-stats-v2-card span{display:block;margin-top:5px;font-weight:700}.ufsc-stats-v2-card small{display:block;margin-top:4px;color:#64748b;line-height:1.3}.ufsc-stats-v2-columns{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}.ufsc-stats-v2-box{border:1px solid #e2e8f0;border-radius:10px;padding:15px}.ufsc-stats-v2-box h4{margin:0 0 12px}.ufsc-stats-v2-box h5{margin:14px 0 8px}.ufsc-stats-v2-bar{margin:9px 0}.ufsc-stats-v2-bar-head{display:flex;justify-content:space-between;gap:10px;font-size:13px}.ufsc-stats-v2-track{height:8px;margin-top:4px;background:#e8edf3;border-radius:999px;overflow:hidden}.ufsc-stats-v2-track span{display:block;height:100%;background:#0b4f7c;border-radius:999px}.ufsc-stats-v2-years{max-height:260px;overflow:auto;padding-right:5px}.ufsc-stats-v2-note,.ufsc-stats-v2-empty{margin:14px 0 0;padding:12px;background:#f1f5f9;border-radius:8px;color:#334155}.ufsc-stats-v2-empty strong,.ufsc-stats-v2-empty span{display:block}.ufsc-stats-v2-empty span{margin-top:4px}.ufsc-dashboard .ufsc-stat-card:nth-child(2) .ufsc-stat-label{text-transform:none}@media(max-width:900px){.ufsc-stats-v2-grid{grid-template-columns:1fr}.ufsc-stats-v2-columns{grid-template-columns:1fr}}@media(max-width:720px){.ufsc-stats-v2-heading{display:block}.ufsc-stats-v2-season{display:inline-block;margin-top:10px}}'
        . '</style>';

    $output = str_replace( 'Licences payées', 'Paiements licence reçus', $output );
    $output = str_replace( 'Payées', 'Paiements reçus', $output );
    $output = str_replace( 'TOTAL LICENCES', 'DOSSIERS LICENCE', $output );
    $output = str_replace( 'Total licences', 'Dossiers licence', $output );

    $marker = '<div class="ufsc-stats-chart">';
    if ( false !== strpos( $output, $marker ) ) {
        $output = str_replace( $marker, $style . $panel . $marker, $output );
    } else {
        $output .= $style . $panel;
    }

    return $output;
}
add_filter( 'do_shortcode_tag', 'ufsc_stats_v2_filter_dashboard', 170, 4 );
