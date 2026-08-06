<?php
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/includes/core/class-ufsc-identifier-resolver.php';
$row = array('numero_licence_ufsc'=>'UFSC-L-000042','numero_licence_asptt'=>'ASPTT-9','numero_licence_delegataire'=>'DELEG-1');
if ('UFSC-L-000042' !== UFSC_Identifier_Resolver::read($row, 'licence_ufsc')) exit("FAIL canonical UFSC\n");
if ('ASPTT-9' !== UFSC_Identifier_Resolver::read($row, 'licence_asptt')) exit("FAIL canonical ASPTT\n");
unset($row['numero_licence_asptt']);
if ('' !== UFSC_Identifier_Resolver::read($row, 'licence_asptt')) exit("FAIL ambiguous alias exposed as ASPTT\n");
if ('ambiguous_legacy' !== UFSC_Identifier_Resolver::classify_field('numero_licence_delegataire')) exit("FAIL classification\n");
echo "OK: canonical identifier resolution keeps ambiguous aliases read-only and unclassified\n";
