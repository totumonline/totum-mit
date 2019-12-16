<?php

use totum\config\Conf;
use \totum\common\Log;


require_once '../totum/AutoloadAndErrorHandlers.php';
require_once 'Conf.php';

$ConfDb = Conf::getDb();

$schemas = array_flip(Conf::getSchemas());
$date = date('Y-m-d H:i:s');
Log::cron('start '.__FILE__);

$dir = dirname(__FILE__);

foreach ($schemas as $schema=>$host) {
   echo `cd $dir && php -f do_schema_crons.php $host "$date"`;
}
?>