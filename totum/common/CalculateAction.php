<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.06.17
 * Time: 15:02
 */

namespace totum\common;


use SoapClient;
use totum\config\Conf;
use totum\models\Table;
use totum\tableTypes\aTable;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;
use \Exception;

class CalculateAction extends Calculate
{
    static $logClassName = "act";

    function funcExec($params)
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            $code=$params['code']??$params['kod'];

            if (!empty($code)) {
                $CA = new static($code);
                try {
                    $Vars = [];
                    foreach ($params['var'] ?? [] as $v) {
                        $Vars = array_merge($Vars, $this->getExecParamVal($v));
                    }

                    $r = $CA->execAction($this->varName,
                        $this->oldRow,
                        $this->row,
                        $this->oldTbl,
                        $this->tbl,
                        $this->aTable,
                        $Vars);
                    $this->newLogParent['children'][] = $CA->getLogVar();

                    return $r;

                } catch (errorException $e) {
                    $this->newLogParent['children'][] = $CA->getLogVar();
                    throw $e;
                }
            }
        }
    }

    protected
    function funcReCalculateCycle($params)
    {
        if ($params = $this->getParamsArray($params)) {
            $tableRow = $this->__checkTableIdOrName($params['table'], 'table', '$table->reCalculate(reCalculate');

            if ($tableRow['type'] != 'cycles') throw new errorException('Функция принимает только таблицы циклов');

            if (!is_array($params['cycle']) && empty($params['cycle'])) {
                throw new errorException('Не указан [[cycle]] в функции [[reCalculate]]');
            }

            $Cycles = (array)$params['cycle'];
            foreach ($Cycles as $cycleId) {
                $params['cycle'] = $cycleId;
                $Cycle = Cycle::init($params['cycle'], $tableRow['id']);
                $Cycle->recalculate();
            }

        }
        aTable::$isActionRecalculateDone = true;
    }

    function execAction($varName, $oldRow, $newRow, $oldTbl, $newTbl, $table, $var = [])
    {
        $dtStart = microtime(true);

        $this->newLog = [];
        $this->newLogParent = &$this->newLog;

        $this->fixedCodeVars = [];
        $this->whileIterators = [];
        $this->vars = $var;
        $this->varName = $varName;

        $this->error = '';

        $this->row = $newRow;
        $this->oldRow = $oldRow;
        $this->tbl = $newTbl;
        $this->oldTbl = $oldTbl;
        $this->aTable = $table;

        try {

            $r = $this->execSubCode($this->code['='], '=');

        } catch (SqlExeption $e) {
            $this->error = 'Ошибка базы данных [[' . $e->getMessage() . ']]';
            $this->newLog['text'] .= $this->error;
            throw new errorException($this->error);
        } catch (errorException $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . $e->getMessage() . ' поля [[' . $this->varName . ']] таблицы [[' . $this->aTable->getTableRow()['name'] . ']]';
            $e->addPath(' поля [[' . $this->varName . ']] таблицы [[' . $this->aTable->getTableRow()['name'] . ']]');
            throw $e;
        }

        static::$calcLog[$var]['time'] = (static::$calcLog[$var = $table->getTableRow()["id"] . '/' . $varName . '/' . static::$logClassName]['time'] ?? 0) + (microtime(true) - $dtStart);
        static::$calcLog[$var]['cnt'] = (static::$calcLog[$var]['cnt'] ?? 0) + 1;


        return $r;
    }

    protected
    function __getActionTable($params, $funcName)
    {
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'reCalculate');

        switch ($tableRow['type']) {
            case 'calcs':
                if (empty($params['cycle'])) {
                    throw new errorException('Параметр [[cycle]] не указан');
                }

                $Cycle = Cycle::init($params['cycle'], $tableRow['tree_node_id']);
                $table = $Cycle->getTable($tableRow);
                unset($params['cycle']);

                break;
            case 'globcalcs':
                $Cycle = Cycle::init(0, 0);
                $table = $Cycle->getTable($tableRow);

                break;
            case 'tmp':
                if (empty($params['hash']) && $this->aTable->getTableRow()['name'] != $tableRow['name']) {

                    throw new errorException('Параметр [[hash]] не указан');
                }
                if ($params['hash']) {
                    $table = tableTypes::getTable($tableRow, $params['hash'] ?? null);
                } else $table = $this->aTable;
                break;
            default:
                $table = tableTypes::getTable($tableRow);

                break;
        }
        return $table;
    }

    protected
    function funcEmailSend($params)
    {
        $params = $this->getParamsArray($params, [], []);

        if (empty($params['to'])) throw new errorException('Параметр to обязателен');
        if (empty($params['title'])) throw new errorException('Параметр title обязателен');
        if (empty($params['body'])) throw new errorException('Параметр body обязателен');

        $toBfl = $params['bfl'] ?? in_array('email',
                tableTypes::getTableByName('settings')->getTbl()['params']['bfl']['v'] ?? []);

        try {
            $r = Mail::send($params['to'],
                $params['title'],
                $params['body'],
                $params['from'] ?? null,
                $params['files'] ?? []);
            if ($toBfl) {
                Bfl::add('script',
                    'system',
                    $params);
            }
            return $r;
        } catch (\Exception $e) {

            if ($toBfl) {
                Bfl::add('script',
                    'system',
                    $params);
            }
            throw new errorException($e->getMessage());
        }
    }

    protected
    function funcTryCatch($params)
    {
        $paramsBefore = $this->getParamsArray($params, [], ['catch', 'try']);

        if (!array_key_exists('try', $paramsBefore)) throw new errorException('try - обязательный параметр');
        if (!array_key_exists('catch', $paramsBefore)) throw new errorException('catch - обязательный параметр');

        try {
            return $this->getParamsArray($params, [], ['error', 'catch'])['try'];
        } catch (\Exception $e) {
            $this->vars[$paramsBefore['error'] ?? 'exception'] = $e->getMessage();
            return $this->execSubCode($paramsBefore['catch'], 'catch');

        }
    }

    protected
    function funcGetFromSoap($params)
    {

        $params = $this->getParamsArray($params, [], []);
        if (array_key_exists('options',
                $params) && !is_array($params['options'])) throw new errorException('options должно быть типа row');


        $toBfl = $params['bfl'] ?? in_array('soap',
                tableTypes::getTableByName('settings')->getTbl()['params']['bfl']['v'] ?? []);
        try {

            $soapClient = new SoapClient($params['wsdl'] ?? null,
                (['cache_wsdl' => WSDL_CACHE_NONE,
                        'exceptions' => true,
                        'soap_version' => SOAP_1_2,
                        'trace' => 1,
                    ] + ($params['options'] ?? [])));


            if (array_key_exists('params', $params)) {
                $res = $soapClient->{$params['func']}(json_decode(json_encode($params['params'],
                    JSON_UNESCAPED_UNICODE)));
            } else {
                $res = $soapClient->{$params['func']}();
            }

            $objectToArray = function ($d) use (&$objectToArray) {
                if (is_object($d)) {
                    $d = (array)$d;
                }
                if (is_array($d)) {
                    return array_map($objectToArray, $d);
                } else {
                    return $d;
                }
            };
            $this->addInNewLogVar('cogs',
                'soap-data-request',
                $soapClient->__getLastRequest());

            $this->addInNewLogVar('cogs',
                'soap-data-response',
                $soapClient->__getLastResponse());
            $data = $objectToArray($res);
            if ($toBfl) {
                Bfl::add('soap',
                    'system',
                    ['xml_request' => $soapClient->__getLastRequest(), 'xml_response' => $soapClient->__getLastResponse(), 'data_request' => $params, 'data_response' => $data]);
            }
            return $data;

        } catch (\Exception $e) {
            if (!empty($soapClient)) {
                $this->addInNewLogVar('a',
                    'soap-data',
                    ['Исходящее SOAP' => $soapClient->__getLastRequest(), 'Ответное SOAP' => $soapClient->__getLastResponse()]);
                if ($toBfl) {
                    Bfl::add('soap',
                        'system',
                        ['xml_request' => $soapClient->__getLastRequest(), 'xml_response' => $soapClient->__getLastResponse(), 'data_request' => $params, 'data_response' => $data, 'error' => $e->getMessage()]);
                }
            } elseif ($toBfl) {
                Bfl::add('soap',
                    'system',
                    ['xml_request' => null, 'xml_response' => null, 'data_request' => $params, 'data_response' => null, 'error' => $e->getMessage()]);
            }
            throw new errorException($e->getMessage());
        }

    }

    protected
    function funcLinkToPanel($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'LinkToPanel');
        $link = '/Table/';

        if ($tableRow['type'] === 'calcs') {
            if ($topTableRow = Table::getTableRowById($tableRow['tree_node_id'])) {
                if ($this->aTable->getTableRow()['type'] === 'calcs' && $tableRow['tree_node_id'] == $this->aTable->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                    $Cycle_id = $this->aTable->getCycle()->getId();
                } else {
                    $this->__checkDigitParam($params['cycle'], 'cycle', 'LinkToPanel');
                    $Cycle_id = $params['cycle'];
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableRow['id'] . '/';
                $Cycle = Cycle::init($Cycle_id, $tableRow['tree_node_id']);
                $linkedTable = $Cycle->getTable($tableRow);
            } else {
                throw new errorException('Таблица циклов указана неверно');
            }
        } else {
            $linkedTable = tableTypes::getTable($tableRow);
            $link .= $tableRow ['top'] . '/' . $tableRow['id'] . '/';
        }
        if (!empty($params['id'])) {
            $ids = (array)$params['id'];
            foreach ($ids as $id) {
                Controller::addLinkPanel($link,
                    $id,
                    [],
                    $params['refresh'] ?? false);
            }
        } elseif (!empty($params['field'])) {
            $field = $this->__getActionFields($params['field'], 'LinkToPanel');
            foreach ($field as $f => &$v) {
                $v = ['v' => $v];
            }
            Controller::addLinkPanel($link,
                null,
                $field,
                $params['refresh'] ?? false);
        } else {
            Controller::addLinkPanel($link,
                null,
                [],
                $params['refresh'] ?? false);
        }

    }

    protected
    function funcLinkToPrint($params)
    {
        $params = $this->getParamsArray($params);

        if (!$params['template'] || !($templates = Model::init('print_templates')->getAllIndexedByField([],
                'styles, html, name',
                'name')) || (!array_key_exists($params['template'],
                $templates))) throw new errorException('Шаблон не найден');

        $style = $templates[$params['template']]['styles'];

        $usedStyles = [];

        $funcReplaceTemplates = function ($html, $data) use (&$funcReplaceTemplates, $templates, &$style, &$usedStyles) {
            return preg_replace_callback('/{(([a-z_0-9]+)(\["[a-z_0-9]+"\])?(?:,([a-z]+(?::[^}]+)?))?)}/',
                function ($matches) use ($data, $templates, &$funcReplaceTemplates, &$style, &$usedStyles) {
                    if (array_key_exists($matches[2], $data)) {

                        if (is_array($data[$matches[2]])) {

                            if (!empty($matches[3])) {
                                $matches[3] = substr($matches[3], 2, -2);
                                if (!array_key_exists($matches[3],
                                    $data[$matches[2]])) throw new errorException('Не найден ключ ' . $matches[3] . ' в параметре [' . $matches[2] . ']');
                                $value = $data[$matches[2]][$matches[3]];
                            } else {

                                if (empty($data[$matches[2]]['template'])) throw new errorException('Не указан template для параметра [' . $matches[2] . ']');
                                if (!array_key_exists($data[$matches[2]]['template'],
                                    $templates)) throw new errorException('Не найден template [' . $data[$matches[2]]['template'] . '] для параметра [' . $matches[2] . ']');
                                $template = $templates[$data[$matches[2]]['template']];
                                $html = '';
                                if (!in_array($template['name'], $usedStyles)) {
                                    $style .= $template['styles'];
                                    $usedStyles[] = $template['name'];
                                }

                                if (array_key_exists(0, $data[$matches[2]]['data'])) {
                                    foreach ($data[$matches[2]]['data'] ?? [] as $_data) {
                                        $html .= $funcReplaceTemplates($template['html'], $_data);
                                    }
                                } else {
                                    $html .= $funcReplaceTemplates($template['html'],
                                        (array)$data[$matches[2]]['data']);
                                }

                                return $html;
                            }

                        } else {
                            $value = $data[$matches[2]];
                        }

                        if (!empty($matches[4])) {
                            if ($formatData = explode(':', $matches[4], 2)) {

                                switch ($formatData[0]) {
                                    case 'money':
                                        if (is_numeric($value)) {
                                            $value = Formats::num2str($value);
                                        }
                                        break;
                                    case 'number':
                                        if (count($formatData) == 2) {
                                            if (is_numeric($value)) {
                                                if ($numberVals = explode('|', $formatData[1])) {
                                                    if (is_numeric($value)) {
                                                        $value = number_format($value,
                                                                $numberVals[0],
                                                                $numberVals[1] ?? '.',
                                                                $numberVals[2] ?? '')
                                                            . ($numberVals[3] ?? '');
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                    case 'date':
                                        if (count($formatData) == 2) {
                                            if ($date = date_create($value)) {
                                                if (strpos($formatData[1], 'F') !== false) {
                                                    $formatData[1] = str_replace('F',
                                                        Formats::months[$date->format('n')],
                                                        $formatData[1]);
                                                }
                                                if (strpos($formatData[1], 'f') !== false) {
                                                    $formatData[1] = str_replace('f',
                                                        Formats::monthRods[$date->format('n')],
                                                        $formatData[1]);
                                                }
                                                $value = $date->format($formatData[1]);

                                            }
                                        }
                                        break;
                                    case 'checkbox':
                                        if (is_bool($value)) {
                                            $sings = [];
                                            if (count($formatData) == 2) {
                                                $sings = explode('|', $formatData[1] ?? '');
                                            }

                                            switch ($value) {
                                                case true:
                                                    $value = $sings[0] ?? '✓';
                                                    break;
                                                case false:
                                                    $value = $sings[1] ?? '-';
                                                    break;
                                            }
                                        }
                                        break;
                                }
                            }
                        }

                        return $value;
                    }

                },
                $html);
        };
        //var_dump($style); die;

        Controller::addToInterfaceDatas('print',
            [
                'body' => $funcReplaceTemplates($templates[$params['template']]['html'], $params['data'] ?? []),
                'styles' => $style
            ]
        );
    }

    protected
    function funcLinkToData($params)
    {
        $params = $this->getParamsArray($params, ['field']);

        $title = $params['title'] ?? 'Здесь должен быть title';

        switch ($params['type'] ?? '') {
            case 'text':
                $width = $params['width'] ?? 500;

                Controller::addToInterfaceDatas('text',
                    ['title' => $title, 'width' => $width, 'text' => htmlspecialchars($params['text']) ?? ''],
                    $params['refresh'] ?? false);
                break;

            case 'table':

                $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

                $tmp = tableTypes::getTable($tableRow);
                $tmp->addData(['tbl' => $params['data'] ?? [], 'params' => ['params' => $params['params'] ?? []]]);
                $data = $tmp->getTableDataForRefresh(null);

                if (empty($params['width'])) {
                    $width = 130;
                    foreach ($tmp->getFieldsFiltered('sortedVisibleFields')['column'] as $field) {
                        $width += $field['width'];
                    }
                    if ($width > 1200) $width = 1200;
                } else {
                    $width = $params['width'];
                }

                $table = [
                    'title' => $title,
                    'table_id' => $tableRow['id'],
                    'sess_hash' => $tmp->getTableRow()['sess_hash'],
                    'data' => array_values($data['chdata']['rows']),
                    'data_params' => $data['chdata']['params'],
                    'f' => $data['chdata']['f'],
                    'width' => $width,
                    'height' => $params['height'] ?? '80vh'
                ];

                Controller::addToInterfaceDatas('table',
                    $table,
                    $params['refresh'] ?? false,
                    ['header' => $params['header'] ?? true,
                        'footer' => $params['footer'] ?? true]);
                break;
        }

    }

    protected
    function funcLinkToDataTable($params)
    {

        $params = $this->getParamsArray($params);

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $tmp = tableTypes::getTable($tableRow);
        $tmp->addData(['tbl' => $params['data'] ?? [], 'params' => ['params' => $params['params'] ?? []]]);
        $data = $tmp->getTableDataForRefresh(null);

        if (empty($params['width'])) {
            $width = 130;
            foreach ($tmp->getFieldsFiltered('sortedVisibleFields')['column'] as $field) {
                $width += $field['width'];
            }
            if ($width > 1200) $width = 1200;
        } else {
            $width = $params['width'];
        }
        $table = [
            'title' => $params['title'] ?? $tableRow['title'],
            'table_id' => $tableRow['id'],
            'sess_hash' => $tmp->getTableRow()['sess_hash'],
            'data' => array_values($data['chdata']['rows']),
            'data_params' => $data['chdata']['params'],
            'f' => $data['chdata']['f'],
            'width' => $width,
            'height' => $params['height'] ?? '80vh'
        ];

        Controller::addToInterfaceDatas('table',
            $table,
            $params['refresh'] ?? false,
            ['header' => $params['header'] ?? true,
                'footer' => $params['footer'] ?? true]);

    }

    protected
    function funcLinkToDataText($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? 'Здесь должен быть title';

        $width = $params['width'] ?? 600;

        Controller::addToInterfaceDatas('text',
            ['title' => $title, 'width' => $width, 'text' => htmlspecialchars($params['text'] ?? '')],
            $params['refresh'] ?? false);

    }

    protected
    function funcLinkToDataHtml($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? 'Здесь должен быть title';

        $width = $params['width'] ?? 600;

        Controller::addToInterfaceDatas('text',
            ['title' => $title, 'width' => $width, 'text' => $params['html'] ?? ''],
            $params['refresh'] ?? false);

    }
    protected
    function funcNormalizeN($params)
    {
        $params = $this->getParamsArray($params);

        if(!key_exists('num', $params) || !is_numeric(strval($params['num']))) throw new errorException('Параметр num обязателен и должен быть числом');
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'NormalizeN');


        /** @var RealTables $table */
        $table=tableTypes::getTable($tableRow);
        if (!is_a($table, RealTables::class)) throw new errorException('Нормализация проводится только для простых таблиц и таблиц циклов');
        if (!$tableRow['with_order_field']) throw new errorException('Таблица не сортируется по N');

        if ($table->getNTailLength()>=(int)$params['num']){
            $table->normalizeN();
        }

    }
    protected
    function funcLinkToTable($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'LinkToTable');

        $link = '/Table/';

        if ($tableRow['type'] === 'calcs') {
            if ($topTableRow = Table::getTableRowById($tableRow['tree_node_id'])) {
                if ($this->aTable->getTableRow()['type'] === 'calcs' && $tableRow['tree_node_id'] == $this->aTable->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                    $Cycle_id = $this->aTable->getCycle()->getId();
                } else {
                    $this->__checkDigitParam($params['cycle'], 'cycle', 'LinkToTable');
                    $Cycle_id = $params['cycle'];
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableRow['id'];
                $Cycle = Cycle::init($Cycle_id, $tableRow['tree_node_id']);
                $linkedTable = $Cycle->getTable($tableRow);
            } else {
                throw new errorException('Таблица циклов указана неверно');
            }
        } else {
            $linkedTable = tableTypes::getTable($tableRow);
            $link .= ($tableRow ['top'] ? $tableRow ['top'] : 0) . '/' . $tableRow['id'];
        }

        $iParams = false;

        $fields = $linkedTable->getFields();
        $filtered = [];
        if (!empty($params['filter'])) {
            $filters = [];
            foreach ($params['filter'] as $i => $f) {
                if ($f['field'] == 'id' || !empty($fields[$f['field']])) {
                    $filters[$f['field']] = $f['value'];
                    if ($f['field'] != 'id' && !empty($fields[$f['field']]['column'])) {
                        $filtered[$fields[$f['field']]['column']] = 1;
                    }
                }
            }
            if ($filters) {
                $cripted = Crypt::getCrypted(json_encode($filters, JSON_UNESCAPED_UNICODE));;
                $link .= '?f=' . urlencode($cripted);
                $iParams = true;
            }
        }

        if (!empty($params['field'])) {
            $field = $this->__getActionFields($params['field'], 'linkToTable');

            foreach ($field as $k => $v) {
                if (array_key_exists($k, $fields)) {
                    $link .= ($iParams === false ? '?' : '&');
                    $link .= 'a[' . $k . ']=' . $v;
                    $iParams = true;
                }
            }
        }


        Controller::addLinkLocation($link,
            $params['target'] ?? 'self',
            $params['title'] ?? $tableRow['title'],
            null,
            $params['width'] ?? null,
            $params['refresh'] ?? false,
            ['header' => $params['header'] ?? true,
                'footer' => $params['footer'] ?? true]
        );
    }

    protected
    function funcLinkToScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match('`https?://`',
                $params['uri'])) throw new errorException('Параметр uri обязателен и должен начитаться с http/https');

        $link = $params['uri'];
        $title = $params['title'] ?? 'Обращение к стороннему скрипту';
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'linkToScript');
        }


        Controller::addLinkLocation($link,
            $params['target'] ?? 'self',
            $title,
            $post ?? null,
            $params['width'] ?? null,
            $params['refresh'] ?? false

        );
    }

    protected
    function funcGetFromScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match('`https?://`',
                $params['uri'])) throw new errorException('Параметр uri обязателен и должен начитаться с http/https');

        $link = $params['uri'];
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'GetFromScript');
        } elseif (!empty($params['posts'])) {
            $post = $params['posts'];
        }


        if (!empty($params['gets'])) {
            $link .= strpos($link, '?') === false ? '?' : '&';
            $link .= http_build_query($params['gets']);
        }

        $toBfl = $params['bfl'] ?? in_array('script',
                tableTypes::getTableByName('settings')->getTbl()['params']['bfl']['v'] ?? []);

        try {
            $r = $this->cURL($link,
                'http://' . Conf::getFullHostName(),
                $params['header'] ?? 0,
                $params['cookie'] ?? '',
                $post,
                ($params['timeout'] ?? null),
                ($params['headers'] ?? "")
            );
            if ($toBfl) {
                Bfl::add('script',
                    'system',
                    [
                        'link' => $link,
                        'ref' => 'http://' . Conf::getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]);
            }
            return $r;
        } catch (\Exception $e) {

            if ($toBfl) {
                Bfl::add('script',
                    'system',
                    [
                        'link' => $link,
                        'ref' => 'http://' . Conf::getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'error' => $e->getMessage()
                    ]);
            }
            throw new errorException($e->getMessage());
        }


    }

    protected
    function __getActionFields($fieldParams, $funcName)
    {
        $fields = [];

        if (empty($fieldParams)) return false;
        foreach ($fieldParams as $f) {
            $fc = $this->getCodes($f);

            try {

                if (count($fc) < 2) throw new Exception();


                $fieldName = $this->__getValue($fc[0]);
                if (empty($fieldName)) throw new Exception();


                $fieldValue = $this->__getValue($fc[2] ?? $fc[1]);

                if (in_array(strtolower($funcName), ['set', 'setlist', 'setlistextended'])) {
                    if ($fc[1]['type'] == 'operator') {
                        $percent = $fc[2]['percent'] ?? false;
                        $fieldValue = new FieldModifyItem($fc[1]['operator'], $fieldValue, $percent);
                    } elseif (empty($fc['comparison'])) {
                        $fieldValue = new FieldModifyItem('+', $fieldValue, $fc[1]['percent'] ?? false);
                    }

                }

                //if (is_null($fieldValue)) throw new Exception();


            } catch (errorException $e) {
                $e->addPath('[[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
                throw $e;
            } catch (SqlExeption $e) {
                throw new errorException($e->getMessage().' [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            } catch (Exception $e) {
                throw new errorException('Неправильное оформление кода в [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            }
            $fields[$fieldName] = $fieldValue;
        }
        return $fields;
    }

    protected
    function funcInsert($params)
    {
        if ($params = $this->getParamsArray($params, ['field', 'cycle'], ['field'])) {
            if (empty($params['cycle']) && in_array($this->aTable->getTableRow()['type'], ['calcs', 'globcalcs'])) {
                $params['cycle'] = [$this->aTable->getCycle()->getId()];
            }
            $addedIds = [];
            $funcSet = function ($params) use (&$addedIds) {
                $table = $this->__getActionTable($params, 'Insert');
                if ($params['field']) {
                    $fields = $this->__getActionFields($params['field'], 'Insert');
                } else $fields = [];

                if(!empty($params['log'])) $table->setWithALogTrue();

                $addedIds += $table->actionInsert($fields, null, $params['after'] ?? null);

            };


            if (!empty($params['cycle'])) {
                $cycleIds = $params['cycle'];
                foreach ($cycleIds as $cycleId) {
                    $params['cycle'] = $cycleId;
                    $funcSet($params);
                }
            } else {
                $funcSet($params);
            }


            aTable::$isActionRecalculateDone = true;
            if (!empty($params['inserts']) && !is_array($params['inserts'])) $this->vars[$params['inserts']] = $addedIds;
        }
    }

    protected function funcinsertListExtended($params){
        return $this->funcInsertListExt($params);
    }

    protected
    function funcInsertListExt($params)
    {
        $params = $this->getParamsArray($params, ['fields', 'field'], ['field']);

        $MainList = [];

        foreach ($params['fields'] as $i => $rowList) {
            if ($rowList) {
                $this->__checkListParam($rowList, 'fields' . (++$i), 'InsertListExtended');
                $max = count($MainList) > count($rowList) ? count($MainList) : count($rowList);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], $rowList[$i] ?? []);
                }
            }
        }
        foreach ($params['field'] ?? [] as $i => $field) {
            $field = $this->getExecParamVal($field);
            $k = array_keys($field)[0];
            $v = array_values($field)[0];
            if (is_array($v) && array_key_exists(0, $v)) {

                $max = count($MainList) > count($v) ? count($MainList) : count($v);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], [$k => $v[$i] ?? null]);
                }

            } else {
                $max = count($MainList);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], [$k => $v]);
                }
            }
        }

        if (($rowList = $MainList) && $params = $this->getParamsArray($params,
                ['field', 'cycle'],
                ['field'])) {
            if (empty($params['cycle']) && in_array($this->aTable->getTableRow()['type'], ['calcs', 'globcalcs'])) {
                $params['cycle'] = [$this->aTable->getCycle()->getId()];
            }

            $addedIds = [];

            $funcSet = function ($params) use ($rowList, &$addedIds) {
                $table = $this->__getActionTable($params, 'Insert');

                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }

                $addedIds[] = $table->actionInsert(null, $rowList, $params['after'] ?? null);
            };

            if (!empty($params['cycle'])) {
                $cycleIds = (array)$params['cycle'];
                foreach ($cycleIds as $cycleId) {
                    $params['cycle'] = $cycleId;
                    $funcSet($params);
                }
            } else {
                $funcSet($params);
            }
            aTable::$isActionRecalculateDone = true;
            if (!empty($params['inserts']) && !is_array($params['inserts'])) $this->vars[$params['inserts']] = $addedIds;
        }
    }

    protected function funcTableLog($params){
        if ($params = $this->getParamsArray($params)) {

            $table = $this->__getActionTable($params, 'TableLog');
            if ($table->getTableRow()['type']==='tmp') throw new errorException('Нельзя писать в лог данные временной таблицы');
            if(empty($params['field'])) throw new errorException('Заполните поле field');
            if (!($field=$table->getFields()[$params['field']])) throw new errorException('Поле [['.$params['field'].']] не найдено в таблице '.$table->getTableRow()['name']);
            if ($field['category']=='column') {
                if(!is_numeric($params['id'])) throw new errorException('Поле id должно быть числовым');


                $valID=$table->getByParams(['field'=>['id', $params['field']], 'where'=>[['field'=>'id', 'operator'=>'=', 'value'=>$params['id']]]], 'row');
                if(!$valID) throw new errorException('Строка с ид '.$params['id'].' не найдена в таблице '.$table->getTableRow()['name']);
                $val=$valID[$params['field']];
            }else{
                $val=$table->getByParams(['field'=>[$params['field']]], 'field');
            }
            aLog::innerLog($table->getTableRow()['id'], $table->getCycle()?$table->getCycle()->getId():null, $params['id']??null, $params['field'], $params['comment']??null, $val);

        }
    }

    protected
    function funcInsertList($params)
    {
        if (($rowList = $this->funcRowListCreate($params)) && $params = $this->getParamsArray($params,
                ['field', 'cycle'],
                ['field'])) {
            if (empty($params['cycle']) && in_array($this->aTable->getTableRow()['type'], ['calcs', 'globcalcs'])) {
                $params['cycle'] = [$this->aTable->getCycle()->getId()];
            }

            $addedIds = [];

            $funcSet = function ($params) use ($rowList, &$addedIds) {
                $table = $this->__getActionTable($params, 'Insert');
                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }

                $addedIds = array_merge($addedIds, $table->actionInsert(null, $rowList, $params['after'] ?? null));
            };

            if (!empty($params['cycle'])) {
                $cycleIds = (array)$params['cycle'];
                foreach ($cycleIds as $cycleId) {
                    $params['cycle'] = $cycleId;
                    $funcSet($params);
                }
            } else {
                $funcSet($params);
            }
            aTable::$isActionRecalculateDone = true;
            if (!empty($params['inserts']) && !is_array($params['inserts'])) $this->vars[$params['inserts']] = $addedIds;
        }
    }

    protected
    function __doAction($params, $func, $isFieldSimple = false)
    {
        $notPrepareParams = $isFieldSimple ? [] : ['field'];

        if ($params = $this->getParamsArray($params, ['field'], $notPrepareParams)) {

            if (empty($params['cycle']) && $this->aTable->getTableRow()['type'] === 'calcs') {
                $params['cycle'] = [$this->aTable->getCycle()->getId()];
            }

            if (!empty($params['cycle'])) {
                foreach ((array)$params['cycle'] as $cycle) {
                    $tmpParams = $params;
                    $tmpParams['cycle'] = $cycle;
                    $func($tmpParams);
                }
            } else {
                $func($params);
            }
            aTable::$isActionRecalculateDone = true;
        }
    }

    protected
    function funcSet($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'Set');
                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }
                $fields = $this->__getActionFields($params['field'], 'Set');
                $where = $params['where'] ?? [];

                $table->actionSet($fields, $where, 1);
            });
    }

    protected
    function funcDelete($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'Delete');
                $where = $params['where'] ?? [];
                if(!empty($params['log'])) $table->setWithALogTrue();
                $table->actionDelete($where, 1);
            });
    }

    protected
    function funcDuplicate($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'Duplicate');
                $fields = $this->__getActionFields($params['field'], 'Duplicate');

                if(!empty($params['log'])) $table->setWithALogTrue();

                $where = $params['where'] ?? [];
                $addedIds = $table->actionDuplicate($fields, $where, 1, $params['after']??null);
                if (!empty($params['inserts']) && !is_array($params['inserts'])) $this->vars[$params['inserts']] = $addedIds;
            });

    }

    protected
    function funcDuplicateList($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'DuplicateList');
                $fields = $this->__getActionFields($params['field'], 'DuplicateList');

                if(!empty($params['log'])) $table->setWithALogTrue();

                $where = $params['where'] ?? [];
                $addedIds = $table->actionDuplicate($fields, $where, null, $params['after']);
                if (!empty($params['inserts']) && !is_array($params['inserts'])) $this->vars[$params['inserts']] = $addedIds;
            });
    }

    protected
    function funcDeleteList($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'DeleteList');
                $where = $params['where'] ?? [];
                if(!empty($params['log'])) $table->setWithALogTrue();
                $table->actionDelete($where, null);
            });
    }

    protected
    function funcClear($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'Clear');

                $where = $params['where'] ?? [];

                if(!empty($params['log'])) $table->setWithALogTrue();

                $table->actionClear($params['field'], $where, 1);
            },
            true);
    }

    protected
    function funcPin($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'Pin');
                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionPin($params['field'], $where, 1);
            },
            true);
    }

    protected
    function funcPinList($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'PinList');
                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionPin($params['field'], $where, null);
            },
            true);
    }

    protected
    function funcSetList($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'SetList');
                $fields = $this->__getActionFields($params['field'], 'SetList');
                if(!empty($params['log'])){
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionSet($fields, $where, null);
            });
    }

    protected
    function funcSetListExtended($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'SetListExtended');
                $fields = $this->__getActionFields($params['field'], 'SetListExtended');

                $where = $params['where'] ?? [];
                $modify = $table->getModifyForActionSetExtended($fields, $where);

                if ($modify) {
                    if(!empty($params['log'])){
                        $table->setWithALogTrue();
                    }

                    $table->reCalculateFromOvers(
                        [
                            'modify' => $modify
                        ]);
                }
            });
    }


    protected
    function funcClearList($params)
    {
        $this->__doAction($params,
            function ($params) {
                $table = $this->__getActionTable($params, 'ClearList');
                $where = $params['where'] ?? [];
                if(!empty($params['log'])) $table->setWithALogTrue();
                $table->actionClear($params['field'], $where, null);
            },
            true);
    }

    static function cURL($url, $ref = '', $header = 0, $cookie = '', $post = null, $timeout = null, $headers = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, $header);

        if ($timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        if (!is_null($post)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        if ($headers){
            $headers = (array)$headers;
        }
        if ($cookie) {
            $headers[]= "Cookie: " . $cookie;
        }
        if ($headers) curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$headers);

        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            curl_close($ch);
            throw new errorException($error);
        }
        curl_close($ch);
        return $result;

    }
}