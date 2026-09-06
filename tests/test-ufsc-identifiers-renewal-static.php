<?php
$root = dirname(__DIR__);
$service = file_get_contents($root.'/includes/core/class-ufsc-identifier-service.php');
$resolver = file_get_contents($root.'/includes/core/class-ufsc-identifier-resolver.php');
$renewal = file_get_contents($root.'/includes/core/class-ufsc-renewal-service.php');
$migration = file_get_contents($root.'/includes/core/class-ufsc-db-migrations.php');
$assert = static function($ok,$message){if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";};

$assert(strpos($service,"'club' => 'UFSC-C-'")!==false && strpos($service,"'licence' => 'UFSC-L-'")!==false,'Stable, separated UFSC formats.');
$assert(strpos($service,'ON DUPLICATE KEY UPDATE next_value=LAST_INSERT_ID(next_value+1)')!==false,'Atomic monotone allocation.');
$assert(strpos($migration,'UNIQUE KEY uniq_identifier (identifier_value)')!==false && strpos($migration,'UNIQUE KEY uniq_entity_identifier')!==false,'Registry uniqueness.');
$assert(strpos($service,'if ( $existing ) { return $existing; }')!==false,'Assignment is idempotent.');
$assert(strpos($service,"0 === stripos( \$value, 'UFSC-' )")!==false,'UFSC identifier cannot populate a partner namespace.');

$assert(strpos($resolver,"'club_ffst'")!==false && strpos($resolver,"'licence_ffst'")!==false,'FFST has dedicated canonical fields.');
$assert(strpos($resolver,"'club_asptt'")!==false && strpos($resolver,"'licence_asptt'")!==false,'Historical partner namespaces remain readable.');
$assert(strpos($service,'save_ffst')!==false && strpos($service,"'numero_affiliation_ffst'")!==false && strpos($service,"'numero_licence_ffst'")!==false,'FFST identifiers have an administrator-owned save path.');

$assert(strpos($migration,"'numero_affiliation_ffst' => 'varchar(64) NULL DEFAULT NULL'")!==false,'FFST club identifier storage is additive.');
$assert(strpos($migration,"'numero_licence_ffst' => 'varchar(64) NULL DEFAULT NULL'")!==false,'FFST licence identifier storage is additive.');
$assert(strpos($migration,"'infos_ffst' => 'tinyint(1) NOT NULL DEFAULT 0'")!==false,'FFST consent storage is additive.');
$assert(strpos($migration,'DROP COLUMN')===false && strpos($migration,'RENAME COLUMN')===false,'Migration does not drop or rename historical columns.');

$assert(strpos($renewal,"'previous_licence_id'")!==false && strpos($renewal,"'person_identifier'")!==false,'Renewal lineage and stable key retained.');
$assert(strpos($renewal,'legacy_partner_fields')!==false && strpos($renewal,"'numero_licence_asptt'")!==false,'Historical partner fields are explicitly blocklisted during renewal.');
$assert(strpos($renewal,"if ( in_array( \$field, \$legacy_fields, true ) ) { continue; }")!==false,'Even external renewal copy lists cannot propagate historical partner fields.');
$assert(strpos($renewal,"'infos_ffst'")!==false,'New-season renewal data supports FFST consent.');
$assert(strpos($renewal,"'quantity'=>1")!==false,'Cart metadata is nominative quantity one.');
$assert(strpos($renewal,"UFSC_Season_Service::get_current_season()")!==false,'Contextual renewal defaults exclusively to the canonical season service.');
$assert(strpos($service,"'POST' !== strtoupper")!==false && substr_count($service,'check_admin_referer')>=2,'Handlers require POST and nonces.');
