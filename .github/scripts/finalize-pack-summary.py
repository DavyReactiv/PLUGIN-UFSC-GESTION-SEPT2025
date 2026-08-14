from pathlib import Path
p=Path('includes/frontend/class-frontend-shortcodes.php')
s=p.read_text(encoding='utf-8')
start=s.index('                    <section class="ufsc-pack-summary" aria-labelledby="ufsc-pack-title">')
end=s.index('\n\n\n                    </div>', start)
new='''                    <section class="ufsc-pack-summary" aria-labelledby="ufsc-pack-title">
                        <?php
                        $pack_limit = function_exists( 'ufsc_get_pack_included_limit' ) ? ufsc_get_pack_included_limit() : 10;
                        $pack_used = min( $pack_limit, max( 0, (int) $pack_usage['total'] ) );
                        $pack_remaining = max( 0, $pack_limit - $pack_used );
                        ?>
                        <div class="ufsc-pack-summary__heading">
                            <h3 id="ufsc-pack-title"><?php esc_html_e( 'Licences incluses dans votre affiliation', 'ufsc-clubs' ); ?></h3>
                            <span><?php echo esc_html( sprintf( __( '%1$d/%2$d utilisées — %3$d restante(s)', 'ufsc-clubs' ), $pack_used, $pack_limit, $pack_remaining ) ); ?></span>
                        </div>
                        <a class="ufsc-pack-card" href="<?php echo esc_url( self::get_club_portal_url( 'licences' ) ); ?>">
                            <span class="ufsc-pack-card__label"><?php esc_html_e( 'Quota inclus', 'ufsc-clubs' ); ?></span>
                            <strong class="ufsc-pack-card__value"><?php echo esc_html( sprintf( __( '%1$d/%2$d', 'ufsc-clubs' ), $pack_used, $pack_limit ) ); ?></strong>
                            <span class="ufsc-pack-card__detail"><?php echo esc_html( $pack_remaining > 0
                                ? sprintf( _n( 'Encore %d licence incluse sans paiement.', 'Encore %d licences incluses sans paiement.', $pack_remaining, 'ufsc-clubs' ), $pack_remaining )
                                : __( 'Quota atteint : toute nouvelle licence ou tout renouvellement supplémentaire sera ajouté au panier.', 'ufsc-clubs' ) ); ?></span>
                        </a>
                        <div class="ufsc-pack-card" aria-label="<?php esc_attr_e( 'Dirigeants renseignés dans le pack', 'ufsc-clubs' ); ?>">
                            <span class="ufsc-pack-card__label"><?php esc_html_e( 'Dirigeants', 'ufsc-clubs' ); ?></span>
                            <strong class="ufsc-pack-card__value"><?php echo esc_html( (int) $pack_usage['bureau'] ); ?></strong>
                            <span class="ufsc-pack-card__detail"><?php foreach ( array( 'president' => __( 'Président', 'ufsc-clubs' ), 'secretaire' => __( 'Secrétaire', 'ufsc-clubs' ), 'tresorier' => __( 'Trésorier', 'ufsc-clubs' ) ) as $role_key => $role_label ) { echo esc_html( $role_label . ' : ' . ( ! empty( $pack_usage['roles'][ $role_key ] ) ? __( 'renseigné', 'ufsc-clubs' ) : __( 'manquant', 'ufsc-clubs' ) ) . '. ' ); } ?></span>
                        </div>
                        <a class="ufsc-pack-card" href="<?php echo esc_url( add_query_arg( 'ufsc_pack', 'payante', self::get_club_portal_url( 'licences' ) ) ); ?>">
                            <span class="ufsc-pack-card__label"><?php esc_html_e( 'Licences supplémentaires payantes', 'ufsc-clubs' ); ?></span>
                            <strong class="ufsc-pack-card__value"><?php echo esc_html( (int) $pack_usage['payantes'] ); ?></strong>
                            <span class="ufsc-pack-card__detail"><?php esc_html_e( 'Uniquement au-delà des dix licences incluses dans l’affiliation.', 'ufsc-clubs' ); ?></span>
                        </a>
                    </section>'''
s=s[:start]+new+s[end:]
p.write_text(s,encoding='utf-8')

# Strengthen the new journey test against contradictory 3/7 messaging.
t=Path('tests/test-licence-flow-levels-ux-static.php')
x=t.read_text(encoding='utf-8')
needle="$assert(strpos($front,'ufsc_get_sport_level_help()')!==false,'front displays explicit level guidance');\n"
extra=needle+"$assert(strpos($front,'Licences incluses dans votre affiliation')!==false && strpos($front,'Quota atteint : toute nouvelle licence ou tout renouvellement supplémentaire sera ajouté au panier.')!==false,'dashboard explains 10 included then paid flow');\n$assert(strpos($front,\"'%d/7'\")===false,'dashboard no longer presents a contradictory seven-free quota');\n"
if needle not in x: raise SystemExit('journey test insertion point missing')
x=x.replace(needle,extra,1)
t.write_text(x,encoding='utf-8')
