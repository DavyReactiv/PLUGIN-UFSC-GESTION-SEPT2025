<?php
use PHPUnit\Framework\TestCase;

final class BusinessRulesContractTest extends TestCase {
    public function test_official_demographics_are_gated_by_canonical_state(): void {
        $stats = file_get_contents( dirname( __DIR__, 2 ) . '/includes/front/class-ufsc-stats.php' );
        self::assertStringContainsString( "if ( ! \$official )", $stats );
        self::assertStringContainsString( "ufsc_resolve_licence_business_state", $stats );
    }

    public function test_renewal_is_exactly_previous_validated_season(): void {
        $service = file_get_contents( dirname( __DIR__, 2 ) . '/includes/core/class-ufsc-renewal-service.php' );
        self::assertStringContainsString( "'valide' !== \$source_status", $service );
        self::assertStringContainsString( '( $target_start - 1 )', $service );
        self::assertStringContainsString( 'create_target_draft', $service );
        self::assertStringContainsString( 'SELECT GET_LOCK', $service );
    }
}
