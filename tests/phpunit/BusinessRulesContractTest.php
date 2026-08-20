<?php
use PHPUnit\Framework\TestCase;

final class BusinessRulesContractTest extends TestCase {
    public function test_official_demographics_are_gated_by_canonical_state(): void {
        $stats = file_get_contents( dirname( __DIR__, 2 ) . '/includes/front/class-ufsc-stats.php' );
        self::assertStringContainsString( "if ( ! \$official )", $stats );
        self::assertStringContainsString( "ufsc_resolve_licence_business_state", $stats );
    }

    public function test_renewal_accepts_previous_season_open_statuses_without_mutating_history(): void {
        $service = file_get_contents( dirname( __DIR__, 2 ) . '/includes/core/class-ufsc-renewal-service.php' );
        self::assertStringContainsString( "\$renewable_source_statuses", $service );
        self::assertStringContainsString( "'valide', 'brouillon', 'draft', 'en_attente', 'pending'", $service );
        self::assertStringContainsString( '( $target_start - 1 )', $service );
        self::assertStringContainsString( 'source_season_mismatch', $service );
        self::assertStringContainsString( 'inactive_affiliation', $service );
        self::assertStringContainsString( 'duplicate_renewal', $service );
        self::assertStringContainsString( 'create_target_draft', $service );
        self::assertStringContainsString( 'previous_licence_id', $service );
        self::assertStringContainsString( 'SELECT GET_LOCK', $service );
        self::assertStringNotContainsString( 'Seule une licence validée de la saison précédente peut être renouvelée.', $service );
    }
}
