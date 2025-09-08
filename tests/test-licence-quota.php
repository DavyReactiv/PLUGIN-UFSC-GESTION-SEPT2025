<?php
define('ABSPATH', __DIR__);
require_once __DIR__ . '/../includes/core/class-sql.php';
require_once __DIR__ . '/../inc/woocommerce/hooks.php';

function ufsc_get_licences_table() { return 'licences'; }
function ufsc_get_clubs_table() { return 'clubs'; }
function current_time($type) { return '2025-01-01 00:00:00'; }

class WPDB_Stub {
    public $licences = array();
    public $clubs = array();
    public function prepare($query, ...$args){
        foreach($args as $a){
            $query = preg_replace("/%[ds]/", $a, $query, 1);
        }
        return $query;
    }
    public function update($table, $data, $where, $format = null, $where_format = null) {
        $id = $where['id'];
        if ($table === 'licences') {
            $this->licences[$id] = array_merge($this->licences[$id], $data);
        } elseif ($table === 'clubs') {
            $this->clubs[$id] = array_merge($this->clubs[$id], $data);
        }
        return 1;
    }
    public function query($query) {
        if (preg_match('/quota_licences = COALESCE\(quota_licences,0\) \+ (\d+) WHERE id = (\d+)/', $query, $m)) {
            $club = (int)$m[2];
            $qty  = (int)$m[1];
            $this->clubs[$club]['quota_licences'] = ($this->clubs[$club]['quota_licences'] ?? 0) + $qty;
            return 1;
        }
        return 0;
    }
}
$wpdb = new WPDB_Stub();

$wpdb->licences[1] = array('id'=>1,'club_id'=>1,'statut'=>'draft','is_included'=>1);
$wpdb->clubs[1] = array('quota_licences'=>0);

UFSC_SQL::mark_licence_as_paid_and_validated(1, '2025');
ufsc_quota_add_paid(1, 1, '2025');

if ($wpdb->licences[1]['statut'] === 'valide' && $wpdb->licences[1]['is_included'] === 0 && $wpdb->clubs[1]['quota_licences'] === 1) {
    echo "Licence quota transition OK\n";
} else {
    echo "Licence quota transition FAIL\n";
}