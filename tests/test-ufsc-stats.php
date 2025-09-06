<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/front/class-ufsc-stats.php';

class UFSC_StatsKeysTest extends TestCase {
    protected function setUp(): void {
        global $wpdb;
        $wpdb = new class {
            public $prefix = 'wp_';
            public function prepare($query, ...$args) {
                $query = str_replace(array('%d','%s'), '%s', $query);
                return vsprintf($query, $args);
            }
            public function get_results($query) {
                return array();
            }
            public function get_var($query) {
                return 0;
            }
        };
    }

    public function test_stats_keys_default_zero() {
        $stats = UFSC_Stats::get_club_stats(1);

        $this->assertArrayHasKey('paid_licences', $stats);
        $this->assertArrayHasKey('validated_licences', $stats);
        $this->assertArrayHasKey('paid', $stats);
        $this->assertArrayHasKey('validated', $stats);

        $this->assertSame(0, $stats['paid_licences']);
        $this->assertSame(0, $stats['validated_licences']);
        $this->assertSame(0, $stats['paid']);
        $this->assertSame(0, $stats['validated']);
    }
}
