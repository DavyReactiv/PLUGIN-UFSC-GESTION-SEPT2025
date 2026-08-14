from pathlib import Path
import re

css_path = Path('assets/css/ufsc-front.css')
php_path = Path('includes/frontend/class-frontend-shortcodes.php')

css = css_path.read_text(encoding='utf-8')
php = php_path.read_text(encoding='utf-8')

css_marker = '/* Final portal layout precedence.'
css_restore = r'''

/* Final portal layout precedence.
 * Keep these rules last: this stylesheet contains older tablet breakpoints
 * for the same selectors, while the dashboard and account navigation now
 * use their own component layout contracts. */
.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-dashboard-header--premium {
 display: block;
 grid-template-columns: none;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-dashboard-hero-layout {
 display: grid;
 grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr);
 width: 100%;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 :is(.ufsc-hero-left, .ufsc-hero-right) {
 grid-column: auto;
 min-width: 0;
}

@media (min-width: 900px) {
 .ufsc-club-portal .ufsc-club-account__nav {
  grid-template-columns: repeat(6, minmax(0, 1fr));
 }
}

@media (min-width: 768px) and (max-width: 899px) {
 .ufsc-club-portal .ufsc-club-account__nav {
  grid-template-columns: repeat(3, minmax(0, 1fr));
 }
}

@media (max-width: 767px) {
 .ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-dashboard-hero-layout {
  grid-template-columns: minmax(0, 1fr);
 }

 .ufsc-club-portal .ufsc-club-account__nav {
  grid-template-columns: repeat(2, minmax(0, 1fr));
 }
}

/* Dashboard demographics: a compact, high-contrast panel inside the blue hero. */
.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-dashboard-header--premium {
 height: auto !important;
 min-height: 0;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-summary {
 background: #ffffff;
 border: 1px solid #cbdff3;
 border-radius: 18px;
 box-shadow: 0 14px 32px rgba(3, 27, 49, .18);
 color: #0f172a;
 margin: 0;
 padding: 18px;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-summary h3 {
 color: #0b3b66;
 font-size: 1.15rem;
 margin: 0 0 14px;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-grid {
 gap: 12px;
 grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-card {
 align-items: center;
 background: #f6f9fd !important;
 border: 1px solid #d8e7f6;
 border-radius: 14px;
 color: #173b67 !important;
 display: grid;
 gap: 8px;
 grid-template-columns: minmax(0, 1fr) auto;
 line-height: 1.25;
 min-block-size: 78px;
 padding: 12px 14px;
 text-align: left;
 text-decoration: none !important;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-card :is(span, small, p) {
 color: #173b67 !important;
 font-size: .9rem;
 font-weight: 750;
 line-height: 1.25;
 margin: 0;
 overflow-wrap: normal;
 text-decoration: none !important;
 word-break: normal;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-card strong {
 color: #0b5fa5 !important;
 font-size: 2rem;
 line-height: 1;
 text-decoration: none !important;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-card:is(:hover, :focus-visible) {
 background: #eaf4ff !important;
 border-color: #0b5fa5;
 box-shadow: 0 0 0 3px rgba(11, 95, 165, .16);
 color: #073b69 !important;
}

@media (max-width: 600px) {
 .ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-grid {
  grid-template-columns: minmax(0, 1fr);
 }
}

/* Meaningful demographic breakdown: sex, age and practice. */
.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-groups {
 display: grid;
 gap: 14px;
 grid-template-columns: repeat(3, minmax(0, 1fr));
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-group {
 background: #f8fbff;
 border: 1px solid #d8e7f6;
 border-radius: 14px;
 padding: 12px;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-group h4 {
 color: #0b3b66;
 font-size: .82rem;
 letter-spacing: .02em;
 margin: 0 0 10px;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-group .ufsc-demographic-grid {
 display: grid;
 gap: 8px;
 grid-template-columns: minmax(0, 1fr);
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-group .ufsc-demographic-card {
 background: #ffffff !important;
 min-block-size: 0;
 padding: 10px 12px;
}

.ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-card small {
 color: #5b7089 !important;
 font-size: .76rem;
 font-weight: 700;
 grid-column: 1 / -1;
}

@media (max-width: 900px) {
 .ufsc-club-account.ufsc-club-dashboard.ufsc-premium-v3 .ufsc-demographic-groups {
  grid-template-columns: minmax(0, 1fr);
 }
}
'''

if css_marker not in css:
    css = css.rstrip() + css_restore + '\n'

kpi_pattern = re.compile(r"(?P<indent>\s*)<\?php \$kpis = array\(.*?\); foreach \( \$kpis as \$kpi \) : \?><a class=\"ufsc-card ufsc-kpi-tile ufsc-hero-kpi-card\".*?</a><\?php endforeach; \?>", re.S)

kpi_replacement = '''                        <?php $kpis = array(
                            array( sprintf( __( 'Licences %s', 'ufsc-clubs' ), $season ), (int) $stats['total_licences'], add_query_arg( 'ufsc_season', $season, self::get_club_portal_url( 'licences' ) ), sprintf( __( 'Dossiers du club appartenant à la saison active %s.', 'ufsc-clubs' ), $season ) ),
                            array( __( 'Licences validées', 'ufsc-clubs' ), (int) $stats['validated_licences'], add_query_arg( array( 'ufsc_status' => 'valide', 'ufsc_season' => $season ), self::get_club_portal_url( 'licences' ) ), __( 'Licences validées du club pour la saison active.', 'ufsc-clubs' ) ),
                            array( __( 'Brouillons / à compléter', 'ufsc-clubs' ), (int) $draft_licences, add_query_arg( array( 'ufsc_status' => 'brouillon', 'ufsc_season' => $season ), self::get_club_portal_url( 'licences' ) ), __( 'Brouillons explicitement enregistrés pour la saison active.', 'ufsc-clubs' ) ),
                            array( __( 'Licences à renouveler', 'ufsc-clubs' ), (int) $renewable_licences, self::get_club_portal_url( 'licences-renouvellement' ), __( 'Licences de la saison précédente encore éligibles au renouvellement.', 'ufsc-clubs' ) ),
                            array( __( 'Paiements à finaliser', 'ufsc-clubs' ), (int) $payable_licences, add_query_arg( 'ufsc_renew_state', 'payable', self::get_club_portal_url( 'licences-renouvellement' ) ), __( 'Demandes du club dont le règlement peut encore être finalisé.', 'ufsc-clubs' ) ),
                            array( sprintf( __( 'Documents manquants %s', 'ufsc-clubs' ), $season ), (int) $honorability_kpis['incomplete'], add_query_arg( 'ufsc_renew_state', 'incomplete', self::get_club_portal_url( 'licences-renouvellement' ) ), sprintf( __( 'Documents manquants uniquement sur les dossiers du club rattachés à %s.', 'ufsc-clubs' ), $season ) ),
                        ); foreach ( $kpis as $kpi ) : ?><a class="ufsc-card ufsc-kpi-tile ufsc-hero-kpi-card" href="<?php echo esc_url( $kpi[2] ); ?>" title="<?php echo esc_attr( $kpi[3] ); ?>" aria-label="<?php echo esc_attr( $kpi[0] . ' — ' . $kpi[1] . '. ' . $kpi[3] ); ?>"><span class="ufsc-kpi-tile-label"><?php echo esc_html( $kpi[0] ); ?></span><strong class="ufsc-kpi-tile-value"><?php echo esc_html( $kpi[1] ); ?></strong></a><?php endforeach; ?>'''

php, kpi_count = kpi_pattern.subn(kpi_replacement, php, count=1)
if kpi_count != 1:
    raise SystemExit(f'KPI block replacement failed: {kpi_count}')

demo_pattern = re.compile(r'\s*<section class="ufsc-demographic-summary" aria-labelledby="ufsc-demographic-title">.*?</section>\s*(?=<section class="ufsc-pack-summary")', re.S)

demo_replacement = r'''
                    <section class="ufsc-demographic-summary" aria-labelledby="ufsc-demographic-title">
                        <h3 id="ufsc-demographic-title"><?php esc_html_e( 'Profil des licenciés', 'ufsc-clubs' ); ?></h3>
                        <?php
                        $profile_total = max( 0, (int) ( $stats['total_licences'] ?? 0 ) );
                        $demographic_groups = array(
                            array(
                                'label' => __( 'Répartition par sexe', 'ufsc-clubs' ),
                                'items' => array(
                                    array( __( 'Femmes', 'ufsc-clubs' ), (int) ( $stats['by_gender']['F'] ?? 0 ), array( 'ufsc_gender' => 'F' ) ),
                                    array( __( 'Hommes', 'ufsc-clubs' ), (int) ( $stats['by_gender']['M'] ?? 0 ), array( 'ufsc_gender' => 'M' ) ),
                                ),
                            ),
                            array(
                                'label' => __( 'Répartition par âge', 'ufsc-clubs' ),
                                'items' => array(
                                    array( __( 'Mineurs', 'ufsc-clubs' ), (int) ( $stats['by_age']['minor'] ?? 0 ), array( 'ufsc_age' => 'minor' ) ),
                                    array( __( 'Majeurs', 'ufsc-clubs' ), (int) ( $stats['by_age']['adult'] ?? 0 ), array( 'ufsc_age' => 'adult' ) ),
                                ),
                            ),
                            array(
                                'label' => __( 'Répartition par pratique', 'ufsc-clubs' ),
                                'items' => array(
                                    array( __( 'Loisirs', 'ufsc-clubs' ), (int) ( $stats['by_practice']['leisure'] ?? 0 ), array( 'ufsc_practice' => 'leisure' ) ),
                                    array( __( 'Compétiteurs', 'ufsc-clubs' ), (int) ( $stats['by_practice']['competition'] ?? 0 ), array( 'ufsc_practice' => 'competition' ) ),
                                ),
                            ),
                        );
                        ?>
                        <div class="ufsc-demographic-groups">
                            <?php foreach ( $demographic_groups as $group ) : ?>
                                <section class="ufsc-demographic-group">
                                    <h4><?php echo esc_html( $group['label'] ); ?></h4>
                                    <div class="ufsc-demographic-grid">
                                        <?php foreach ( $group['items'] as $demographic ) :
                                            $count = max( 0, (int) $demographic[1] );
                                            $percent = $profile_total > 0 ? (int) round( ( $count / $profile_total ) * 100 ) : 0;
                                            $url = add_query_arg( array_merge( array( 'ufsc_season' => $season ), $demographic[2] ), self::get_club_portal_url( 'licences' ) );
                                        ?>
                                            <a class="ufsc-card ufsc-demographic-card" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%1$s : %2$d licencié(s), soit %3$d %% du total.', 'ufsc-clubs' ), $demographic[0], $count, $percent ) ); ?>">
                                                <span class="ufsc-demographic-card__label"><?php echo esc_html( $demographic[0] ); ?></span>
                                                <strong><?php echo esc_html( $count ); ?></strong>
                                                <small><?php echo esc_html( sprintf( __( '%d %% des licenciés', 'ufsc-clubs' ), $percent ) ); ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </section>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    '''

php, demo_count = demo_pattern.subn(demo_replacement, php, count=1)
if demo_count != 1:
    raise SystemExit(f'Demographic block replacement failed: {demo_count}')

css_path.write_text(css, encoding='utf-8')
php_path.write_text(php, encoding='utf-8')
