<?php
$root = dirname( __DIR__ );

$levels    = file_get_contents( $root . '/inc/common/fighter-level.php' );
$category  = file_get_contents( $root . '/includes/core/class-ufsc-category-repository.php' );
$front     = file_get_contents( $root . '/includes/frontend/class-frontend-shortcodes.php' );
$js        = file_get_contents( $root . '/assets/js/ufsc-license-form.js' );
$admin     = file_get_contents( $root . '/includes/admin/class-sql-admin.php' );
$handlers  = file_get_contents( $root . '/includes/core/class-unified-handlers.php' );
$migration = file_get_contents( $root . '/includes/core/class-ufsc-db-migrations.php' );
$cart      = file_get_contents( $root . '/inc/woocommerce/cart-integration.php' );

$assert = static function ( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
};

$assert( false === strpos( $levels, "'classe_c'  => __( 'Senior Combat" ), 'Classe C must not remain selectable' );
$assert( false !== strpos( $levels, "'classe_c' => 'classe_b'" ), 'legacy Classe C must normalize to Classe B' );
$assert( false !== strpos( $levels, 'ufsc_competition_age_from_birth_date' ), 'fighter level must use season competition age' );
$assert( false !== strpos( $levels, "array( 'assaut', 'classe_b', 'classe_a', 'pro' )" ), 'Senior 18-40 choices must be Assaut/B/A/Pro' );
$assert( false !== strpos( $levels, "array( 'assaut', 'combat' )" ), 'Cadet 2nd year/Junior choices must be Assaut/Combat' );

$assert( false !== strpos( $category, "const RING_DISCIPLINE    = 'kickboxing_ring_combat'" ), 'ring referential must exist' );
$assert( false !== strpos( $category, "'cadettes_2e_annee'" ) && false !== strpos( $category, "'cadets_2e_annee'" ), 'ring cadet 2nd-year categories must exist' );
$assert( false !== strpos( $category, '63.5' ) && false !== strpos( $category, '91' ), 'official ring weight limits must be represented' );
$assert( false !== strpos( $category, "in_array( $level, array( 'combat', 'classe_b', 'classe_a', 'pro' ), true )" ), 'combat levels must select ring referential' );

$assert( false !== strpos( $front, 'data-season-start-year' ), 'front licence form must expose season start year' );
$assert( false !== strpos( $front, 'data-ufsc-sport-category-preview' ), 'front licence form must expose live category preview' );
$assert( false !== strpos( $front, 'ufsc_get_sport_level_options_for_athlete' ), 'renewal form must restrict levels by athlete age' );
$assert( false !== strpos( $js, "age >= 18 && age <= 40" ), 'front JS must restrict senior classes to 18-40' );
$assert( false !== strpos( $js, "age >= 15 && age < 18" ), 'front JS must restrict combat to cadet 2nd year/junior' );
$assert( false !== strpos( $js, "age <= 50 ? 'veteran' : 'assaut'" ), 'front JS must not default over-50 athletes to hidden veteran option' );

$assert( false === strpos( $admin, 'Mineur : Assaut. Majeur : Classe C' ), 'admin help must no longer advertise Classe C' );
$assert( false !== strpos( $admin, 'ufsc_get_sport_level_help' ), 'admin must reuse central sport-level help' );
$assert( false !== strpos( $handlers, "'fighter_level'  => $licence->fighter_level ?? ''" ), 'weight recalculation must use stored fighter level' );

$assert( false !== strpos( $migration, "'ufsc_2026_classe_c_to_b_backup'" ), 'migration must save a backup manifest before changing current Classe C rows' );
$assert( false !== strpos( $migration, "'2026-2027' !== $season" ), 'migration must be restricted to season 2026-2027' );
$assert( false !== strpos( $migration, 'ufsc_get_detected_season_column' ), 'migration must require a reliable season column' );
$assert( false !== strpos( $migration, "START TRANSACTION" ) && false !== strpos( $migration, "ROLLBACK" ) && false !== strpos( $migration, "COMMIT" ), 'migration must be transactional' );
$assert( false !== strpos( $migration, "SET fighter_level = 'classe_b'" ), 'migration must modify only fighter level to Classe B' );

$assert( false === strpos( $levels, 'WC()->cart' ), 'fighter level logic must not touch WooCommerce cart' );
$assert( false === strpos( $category, 'WC()->cart' ), 'category repository must not touch WooCommerce cart' );
$assert( false !== strpos( $cart, 'sanitize_renewal_updates' ), 'existing renewal cart flow must remain in place' );

echo "Licence sport categories 2026-2027 static safeguards OK\n";
