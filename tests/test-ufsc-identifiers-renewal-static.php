<?php
$root = dirname(__DIR__);
$service = file_get_contents($root.'/includes/core/class-ufsc-identifier-service.php');
$resolver = file_get_contents($root.'/includes/core/class-ufsc-identifier-resolver.php');
$renewal = file_get_contents($root.'/includes/core/class-ufsc-renewal-service.php');
$migration = file_get_contents($root.'/includes/core/class-ufsc-db-migrations.php');
$assert = static function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";};
$assert(strpos($service,"'club' => 'UFSC-C-'")!==false && strpos($service,"'licence' => 'UFSC-L-'")!==false,'Stable, separated formats.');
$assert(strpos($service,'ON DUPLICATE KEY UPDATE next_value=LAST_INSERT_ID(next_value+1)')!==false,'Atomic monotone allocation.');
$assert(strpos($migration,'UNIQUE KEY uniq_identifier (identifier_value)')!==false && strpos($migration,'UNIQUE KEY uniq_entity_identifier')!==false,'Registry uniqueness.');
$assert(strpos($service,'if ( $existing ) { return $existing; }')!==false,'Assignment is idempotent.');
$assert(strpos($service,"0 === stripos( \$value, 'UFSC-' )")!==false,'UFSC cannot populate ASPTT, regardless of case.');
$assert(strpos($resolver,"'club_asptt'")!==false && strpos($resolver,"'licence_asptt'")!==false,'ASPTT has separate canonical fields.');
$assert(strpos($renewal,"'previous_licence_id'")!==false && strpos($renewal,"'person_identifier'")!==false,'Renewal lineage and stable key retained.');
$assert(strpos($renewal,"'numero_licence_asptt'")===false,'ASPTT is not copied into renewal payload.');
$assert(strpos($renewal,"'quantity'=>1")!==false,'Cart metadata is nominative quantity one.');
$assert(strpos($renewal,"UFSC_Season_Service::get_current_season()")!==false,'Contextual renewal defaults exclusively to the canonical season service.');
$assert(strpos($service,"'POST' !== strtoupper")!==false && substr_count($service,'check_admin_referer')>=2,'Handlers require POST and nonces.');
