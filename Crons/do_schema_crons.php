<?php

use totum\common\Auth;
use totum\common\Controller;
use totum\common\Log;
use totum\common\Model;
use totum\common\Settings;
use totum\common\Sql;
use totum\common\tableSaveException;
use totum\config\Conf;
use totum\models\Table;
use totum\tableTypes\tableTypes;

set_time_limit(400);

$_SERVER['HTTP_HOST'] = $argv[1] ?? null;
$date = $argv[2] ?? date('Y-m-d H:i:s');
if (empty($_SERVER['HTTP_HOST'])) die('schema not found');


ini_set('log_errors', 1);
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
ini_set('memory_limit', "512M");

require_once '../totum/AutoloadAndErrorHandlers.php';
require_once 'Conf.php';

if (empty(Conf::getSchemas()[$_SERVER['HTTP_HOST']])) die('schema not found');

Auth::CronUserStart();

$crnTexts = Model::init('settings')->getAll(['status' => "true"]);



$date = date_create($date);

$nowMinute = floor($date->format('i') / 10) * 10;
$nowHour = $date->format('H');
$nowDay = $date->format('d');
$nowMonth = $date->format('m');
$nowWeekDay = $date->format('N');


Log::cron(__FILE__ . ' : ' . $_SERVER['HTTP_HOST']);


$checkRules = [
    'minute' => $nowMinute
    , 'hour' => $nowHour
    , 'day_of_month' => $nowDay
    , 'month' => $nowMonth
    , 'weekday' => $nowWeekDay
];
$Table = tableTypes::getTable(Table::getTableRowByName("settings"));

foreach ($crnTexts as $rule) {
    $rule=\totum\tableTypes\RealTables::decodeRow($rule);
    foreach ($rule as $k=>&$v) if (is_array($v) && key_exists('v', $v)) $v=$v['v'];
    unset($v);

    foreach ($checkRules as $field => $val) {
        $checkField = $rule[$field];
        if (!empty($checkField) && !in_array($val, $checkField)) continue 2;
    }

    $Cacl = new \totum\common\CalculateAction($rule['code']);
    Log::cron($rule['code']);
    \totum\common\Sql::transactionStart();
    try {
        $Cacl->execAction('code', $rule, $rule, ['params' => $Table->params], ['params' => $Table->params], $Table);
    }
    catch (\totum\common\errorException $exception) {

        \totum\common\Mail::send(Conf::adminEmail,
            'Ошибка крона ' . Conf::getSchema() . ' ' . $rule['kod'],
            $exception->getMessage());


        $Cacl = new \totum\common\CalculateAction('=: insert(table: "notifications"; field: \'user_id\'=1; field: \'active\'=true; field: \'title\'="Ошибка крона"; field: \'code\'="admin_text"; field: "vars"=$#vars)');
        $Cacl->execAction('kod',
            $rule,
            $rule,
            ['params' => $Table->params],
            ['params' => $Table->params],
            $Table,
            ['vars' => ['text' => 'Ошибка крона <b>' . $rule['opisanie'] . '</b>:<br>' . $exception->getMessage()]]);
    }

    \totum\common\Sql::transactionCommit();

}

/*Удалить старые временные таблицы*/
$plus24=date_create();
$plus24->modify('-24 hours');
\totum\common\Sql::exec('delete from _tmp_tables where touched<\''.$plus24->format('Y-m-d H:i').'\'');

foreach (Controller::getLinks() ?? [] as $link) {

    $data = http_build_query($link['postData']);

    $context = stream_context_create(
        array(
            'http' => array(
                'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                'method' => 'POST',
                'content' => $data
            )
        )
    );
    $contents = file_get_contents($link['uri'], false, $context);
    Log::cron($link['uri'] . ':' . $data);
}
?>