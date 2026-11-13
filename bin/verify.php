<?php
declare(strict_types=1);
$checks=['PHP >= 8.1'=>version_compare(PHP_VERSION,'8.1.0','>='),'PDO'=>extension_loaded('pdo'),'PDO MySQL'=>extension_loaded('pdo_mysql'),'Fileinfo'=>extension_loaded('fileinfo'),'DOM'=>extension_loaded('dom'),'JSON'=>extension_loaded('json'),'OpenSSL'=>extension_loaded('openssl'),'mbstring'=>extension_loaded('mbstring'),'database schema'=>is_file(dirname(__DIR__).'/database/schema.sql'),'public entry point'=>is_file(dirname(__DIR__).'/public/index.php')];
$failed=false;foreach($checks as $label=>$ok){echo ($ok?'[OK]   ':'[FAIL] ').$label.PHP_EOL;$failed=$failed||!$ok;}exit($failed?1:0);
