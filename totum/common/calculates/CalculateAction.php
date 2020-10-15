<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.06.17
 * Time: 15:02
 */

namespace totum\common\calculates;

use SoapClient;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\FieldModifyItem;
use totum\common\Formats;
use totum\common\sql\SqlException;
use totum\common\TotumInstall;
use totum\tableTypes\RealTables;
use \Exception;

class CalculateAction extends Calculate
{
    protected function funcExec($params)
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            $code = $params['code'] ?? $params['kod'];

            if (!empty($code)) {
                $CA = new static($code);
                try {
                    $Vars = [];
                    foreach ($params['var'] ?? [] as $v) {
                        $Vars = array_merge($Vars, $this->getExecParamVal($v));
                    }

                    $r = $CA->execAction(
                        $this->varName,
                        $this->oldRow,
                        $this->row,
                        $this->oldTbl,
                        $this->tbl,
                        $this->Table,
                        $Vars
                    );
                    $this->newLogParent['children'][] = $CA->getLogVar();

                    return $r;
                } catch (errorException $e) {
                    $this->newLogParent['children'][] = $CA->getLogVar();
                    throw $e;
                }
            }
        }
    }

    /*TODO сломано*/
    protected function funcSchemaGzStringGet($params)
    {
        $params = $this->getParamsArray($params);
        if (!$this->Table->getUser()->isCreator()) {
            throw new errorException('Функция доступна только роли Создатель');
        }

        if ($params['password'] !== $this->Table->getUser()->getVar('pass')) {
            throw new errorException('Пароль не подходит');
        }

        $pathDbPsql = $this->Table->getTotum()->getConfig()->getSshPostgreConnect('pg_dump');

        $tmpFilename = tempnam(
            $this->Table->getTotum()->getConfig()->getTmpDir(),
            $this->Table->getTotum()->getConfig()->getSchema() . '.' . $this->Table->getUser()->getId() . '.'
        );

        $schema = $this->Table->getTotum()->getConfig()->getSchema();
        $exclude = "--exclude-table-data='_tmp_tables'";
        if (empty($params['withlog'])) {
            $exclude .= " --exclude-table-data='_log'";
        }
        if (empty($params['withbfl'])) {
            $exclude .= " --exclude-table-data='_bfl'";
        }
        exec(
            "$pathDbPsql -O --schema '{$schema}' {$exclude} | grep -v '^--' | gzip > \"{$tmpFilename}\"",
            $data
        );
        $f = file_get_contents($tmpFilename);
        unlink($tmpFilename);
        return $f;
    }

    protected function funcSchemaGzStringUpload($params)
    {
        $params = $this->getParamsArray($params);
        if (!$this->Table->getUser()->isCreator()) {
            throw new errorException('Функция доступна только роли Создатель');
        }

        if ($params['password'] !== $this->Table->getUser()->getVar('pass')) {
            throw new errorException('Пароль не подходит');
        }
        if (empty($params['gzstring'])) {
            throw new errorException('Строка схемы пуста');
        }
        $pathDbPsql = $this->Table->getTotum()->getConfig()->getSshPostgreConnect('psql');


        $tmpFileName = tempnam(
            $this->Table->getTotum()->getConfig()->getTmpDir(),
            $this->Table->getTotum()->getConfig()->getSchema() . '.' . $this->Table->getTotum()->getUser()->getId() . '.'
        );
        $tmpFileName2 = tempnam(
            $this->Table->getTotum()->getConfig()->getTmpDir(),
            $this->Table->getTotum()->getConfig()->getSchema() . '.' . $this->Table->getTotum()->getUser()->getId() . '.'
        );

        file_put_contents($tmpFileName, $params['gzstring']);
        ` zcat {$tmpFileName} > $tmpFileName2`;

        file_put_contents(
            $tmpFileName,
            sprintf(
                'drop schema "%s" cascade; %s',
                $this->Table->getTotum()->getConfig()->getSchema(),
                preg_replace(
                    '/^(REVOKE|GRANT) ALL .*?;[\n\r]*$/m',
                    '',
                    file_get_contents($tmpFileName2)
                )
            )
        );

        $tmpErrors = tempnam($this->Table->getTotum()->getConfig()->getTmpDir(), 'schema_errors_');

        `$pathDbPsql -q -1 -b -v ON_ERROR_STOP=1 -f $tmpFileName 2>$tmpErrors`;
        if ($errors = file_get_contents($tmpErrors)) {
            throw new errorException($errors);
        }
    }

    protected function funcReCalculateCycle($params)
    {
        if ($params = $this->getParamsArray($params)) {
            $tableRow = $this->__checkTableIdOrName($params['table'], 'table', '$table->reCalculate(reCalculate');

            if ($tableRow['type'] !== 'cycles') {
                throw new errorException('Функция принимает только таблицы циклов');
            }

            if (!is_array($params['cycle']) && empty($params['cycle'])) {
                throw new errorException('Не указан [[cycle]] в функции [[reCalculate]]');
            }

            $Cycles = (array)$params['cycle'];
            foreach ($Cycles as $cycleId) {
                $params['cycle'] = $cycleId;
                $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['id']);
                $Cycle->recalculate();
            }
        }
    }

    public function execAction($varName, $oldRow, $newRow, $oldTbl, $newTbl, $table, $var = [])
    {
        $r = $this->exec(['name' => $varName], null, $oldRow, $newRow, $oldTbl, $newTbl, $table, $var);

        return $r;
    }

    protected function funcSchemaUpdate($params)
    {
        $params = $this->getParamsArray($params);
        $TotumInstall = new TotumInstall(
            $this->Table->getTotum()->getConfig(),
            $this->Table->getTotum()->getUser(),
            null,
            $this->Table->getCalculateLog()
        );

        $params['schema'] = TotumInstall::applyMatches($params['schema'], $params);
        if (empty($params['matches_name'])) {
            throw new errorException('Не определен источник схемы');
        }

        $TotumInstall->updateSchema($params['schema'], true, $params['matches_name']);
    }

    protected function funcLinkToButtons($params)
    {
        $params = $this->getParamsArray($params);
        if (empty($params['title'])) {
            throw new errorException('Заполните параметр [[title]]');
        }
        if (empty($params['buttons'])) {
            throw new errorException('Заполните параметр [[buttons]]');
        }
        if (!is_array($params['buttons'])) {
            throw new errorException('Параметр [[buttons]] должен содержать массив');
        }

        $params['refresh'] = $params['refresh'] ?? false;

        $requiredByttonParams = ['text', 'code'];
        $buttons = [];
        foreach ($params['buttons'] as $btn) {
            foreach ($requiredByttonParams as $req) {
                if (empty($btn[$req])) {
                    throw new errorException('Каждая кнопка должна содержать [[' . $req . ']]');
                }
            }
            unset($btn['code']);
            unset($btn['vars']);

            $btn['refresh'] = $btn['refresh'] ?? $params['refresh'];
            $buttons[] = $btn;
        }

        $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

        do {
            $hash = md5(microtime(true) . '__linktobuttons_' . mt_srand());
            $key = ['table_name' => '_linkToButtons', 'user_id' => $this->Table->getUser()->getId(), 'hash' => $hash];
        } while ($model->getField('user_id', $key));

        $vars = array_merge(
            ['tbl' => json_encode(
                $params,
                JSON_UNESCAPED_UNICODE
            ),
                'touched' => date('Y-m-d H:i')],
            $key
        );


        $model->insertPrepared(
            $vars,
            false
        );
        $params['hash'] = $hash;

        $params['buttons'] = $buttons;
        $this->Table->getTotum()->addToInterfaceDatas('buttons', $params);
    }

    protected function funcLinkToInput($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);
        if (empty($params['title'])) {
            throw new errorException('Заполните параметр [[title]]');
        }
        if (empty($params['code'])) {
            throw new errorException('Заполните параметр [[code]]');
        }

        $params['input'] = '';
        $vars = [];
        foreach ($params['var'] ?? [] as $_) {
            $vars[$_['field']] = $_['value'];
        }
        $params['vars'] = $vars;

        $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

        do {
            $hash = md5(microtime(true) . '__linktoinput_' . mt_srand());
            $key = ['table_name' => '_linkToInput', 'user_id' => $this->Table->getUser()->getId(), 'hash' => $hash];
        } while ($model->getField('user_id', $key));

        $vars = array_merge(
            ['tbl' => json_encode(
                $params,
                JSON_UNESCAPED_UNICODE
            ),
                'touched' => date('Y-m-d H:i')],
            $key
        );
        $model->insertPrepared(
            $vars,
            false
        );
        $params['hash'] = $hash;
        $this->Table->getTotum()->addToInterfaceDatas(
            'input',
            array_intersect_key(
                $params,
                ["value" => 1, "title" => 1, "html" => 1, "hash" => 1, "refresh" => 1, "button" => 1]
            )
        );
    }


    protected function funcEmailSend($params)
    {
        $params = $this->getParamsArray($params, [], []);

        if (empty($params['to'])) {
            throw new errorException('Параметр to обязателен');
        }
        if (empty($params['title'])) {
            throw new errorException('Параметр title обязателен');
        }
        if (empty($params['body'])) {
            throw new errorException('Параметр body обязателен');
        }

        $toBfl = $params['bfl'] ?? in_array(
            'email',
            $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
        );

        try {
            $r = $this->Table->getTotum()->getConfig()->sendMail(
                $params['to'],
                $params['title'],
                $params['body'],
                $params['files'] ?? [],
                $params['from'] ?? null
            );

            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->debug("email", $params);
            }
            return $r;
        } catch (Exception $e) {
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    "email",
                    ['error' => $e->getMessage()] + $params
                );
            }
            throw new errorException($e->getMessage());
        }
    }

    protected function funcTryCatch($params)
    {
        $paramsBefore = $this->getParamsArray($params, [], ['catch', 'try']);

        if (!array_key_exists('try', $paramsBefore)) {
            throw new errorException('try - обязательный параметр');
        }
        if (!array_key_exists('catch', $paramsBefore)) {
            throw new errorException('catch - обязательный параметр');
        }

        try {
            return $this->getParamsArray($params, [], ['error', 'catch'])['try'];
        } catch (Exception $e) {
            $this->vars[$paramsBefore['error'] ?? 'exception'] = $e->getMessage();
            return $this->execSubCode($paramsBefore['catch'], 'catch');
        }
    }

    protected function funcGetFromSoap($params)
    {
        $params = $this->getParamsArray($params, [], []);
        if (array_key_exists(
            'options',
            $params
        ) && !is_array($params['options'])) {
            throw new errorException('options должно быть типа row');
        }


        $toBfl = $params['bfl'] ?? in_array(
            'soap',
            $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
        );
        try {
            $soapClient = new SoapClient(
                $params['wsdl'] ?? null,
                (['cache_wsdl' => WSDL_CACHE_NONE,
                        'exceptions' => true,
                        'soap_version' => SOAP_1_2,
                        'trace' => 1,
                    ] + ($params['options'] ?? []))
            );


            if (array_key_exists('params', $params)) {
                $res = $soapClient->{$params['func']}(json_decode(json_encode(
                    $params['params'],
                    JSON_UNESCAPED_UNICODE
                )));
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
            $data = $objectToArray($res);
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->debug(
                    "SOAP",
                    ['xml_request' => $soapClient->__getLastRequest(), 'xml_response' => $soapClient->__getLastResponse(), 'data_request' => $params, 'data_response' => $data]
                );
            }
            return $data;
        } catch (Exception $e) {
            if ($toBfl) {
                if (!empty($soapClient)) {
                    $this->Table->getTotum()->getOutersLogger()->error(
                        "SOAP" . $e->getMessage(),
                        ['xml_request' => $soapClient->__getLastRequest(),
                            'xml_response' => $soapClient->__getLastResponse(),
                            'data_request' => $params, 'data_response' => $data,
                            'error' => $e->getMessage()]
                    );
                } else {
                    $this->Table->getTotum()->getOutersLogger()->error(
                        "SOAP",
                        ['xml_request' => null, 'xml_response' => null,
                            'data_request' => $params, 'data_response' => null,
                            'error' => $e->getMessage()]
                    );
                }
            }
            throw new errorException($e->getMessage());
        }
    }

    protected function funcLinkToPanel($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'LinkToPanel');
        $link = '/Table/';

        if ($tableRow['type'] === 'calcs') {
            if ($topTableRow = $this->Table->getTotum()->getTableRow($tableRow['tree_node_id'])) {
                if ($this->Table->getTableRow()['type'] === 'calcs' && (int)$tableRow['tree_node_id'] == $this->Table->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                    $Cycle_id = $this->Table->getCycle()->getId();
                } else {
                    $this->__checkNumericParam($params['cycle'], 'cycle', 'LinkToPanel');
                    $Cycle_id = $params['cycle'];
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableRow['id'] . '/';
            } else {
                throw new errorException('Таблица циклов указана неверно');
            }
        } else {
            $link .= $tableRow ['top'] . '/' . $tableRow['id'] . '/';
        }
        if (!empty($params['id'])) {
            $ids = (array)$params['id'];
            foreach ($ids as $id) {
                $this->Table->getTotum()->addLinkPanel(
                    $link,
                    $id,
                    [],
                    $params['refresh'] ?? false
                );
            }
        } elseif (!empty($params['field'])) {
            $field = $this->__getActionFields($params['field'], 'LinkToPanel');
            foreach ($field as $f => &$v) {
                $v = ['v' => $v];
            }
            $this->Table->getTotum()->addLinkPanel(
                $link,
                null,
                $field,
                $params['refresh'] ?? false
            );
        } else {
            $this->Table->getTotum()->addLinkPanel(
                $link,
                null,
                [],
                $params['refresh'] ?? false
            );
        }
    }

    protected function funcLinkToPrint($params)
    {
        $params = $this->getParamsArray($params);

        if (!$params['template'] || !($templates = $this->Table->getTotum()->getModel('print_templates')->getAllIndexedByField(
            [],
            'styles, html, name',
            'name'
        )) || (!array_key_exists(
                $params['template'],
                $templates
            ))) {
            throw new errorException('Шаблон не найден');
        }

        $style = $templates[$params['template']]['styles'];

        $usedStyles = [];

        $funcReplaceTemplates = function ($html, $data) use (&$funcReplaceTemplates, $templates, &$style, &$usedStyles) {
            return preg_replace_callback(
                '/{(([a-z_0-9]+)(\["[a-z_0-9]+"\])?(?:,([a-z]+(?::[^}]+)?))?)}/',
                function ($matches) use ($data, $templates, &$funcReplaceTemplates, &$style, &$usedStyles) {
                    if (array_key_exists($matches[2], $data)) {
                        if (is_array($data[$matches[2]])) {
                            if (!empty($matches[3])) {
                                $matches[3] = substr($matches[3], 2, -2);
                                if (!array_key_exists(
                                    $matches[3],
                                    $data[$matches[2]]
                                )) {
                                    throw new errorException('Не найден ключ ' . $matches[3] . ' в параметре [' . $matches[2] . ']');
                                }
                                $value = $data[$matches[2]][$matches[3]];
                            } else {
                                if (empty($data[$matches[2]]['template'])) {
                                    throw new errorException('Не указан template для параметра [' . $matches[2] . ']');
                                }
                                if (!array_key_exists(
                                    $data[$matches[2]]['template'],
                                    $templates
                                )) {
                                    throw new errorException('Не найден template [' . $data[$matches[2]]['template'] . '] для параметра [' . $matches[2] . ']');
                                }
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
                                    $html .= $funcReplaceTemplates(
                                        $template['html'],
                                        (array)$data[$matches[2]]['data']
                                    );
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
                                        if (count($formatData) === 2) {
                                            if (is_numeric($value)) {
                                                if ($numberVals = explode('|', $formatData[1])) {
                                                    if (is_numeric($value)) {
                                                        $value = number_format(
                                                            $value,
                                                            $numberVals[0],
                                                            $numberVals[1] ?? '.',
                                                            $numberVals[2] ?? ''
                                                        )
                                                            . ($numberVals[3] ?? '');
                                                    }
                                                }
                                            }
                                        }
                                        break;
                                    case 'date':
                                        if (count($formatData) === 2) {
                                            if ($date = date_create($value)) {
                                                if (strpos($formatData[1], 'F') !== false) {
                                                    $formatData[1] = str_replace(
                                                        'F',
                                                        Formats::months[$date->format('n')],
                                                        $formatData[1]
                                                    );
                                                }
                                                if (strpos($formatData[1], 'f') !== false) {
                                                    $formatData[1] = str_replace(
                                                        'f',
                                                        Formats::monthRods[$date->format('n')],
                                                        $formatData[1]
                                                    );
                                                }
                                                $value = $date->format($formatData[1]);
                                            }
                                        }
                                        break;
                                    case 'checkbox':
                                        if (is_bool($value)) {
                                            $sings = [];
                                            if (count($formatData) === 2) {
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
                $html
            );
        };
        //var_dump($style); die;

        $this->Table->getTotum()->addToInterfaceDatas(
            'print',
            [
                'body' => $funcReplaceTemplates($templates[$params['template']]['html'], $params['data'] ?? []),
                'styles' => $style
            ]
        );
    }

    protected function funcLinkToData($params)
    {
        $params = $this->getParamsArray($params, ['field']);
        switch ($params['type'] ?? '') {
            case 'text':
                return $this->funcLinkToDataText($params);
            case 'table':
                return $this->funcLinkToDataTable($params);
        }
    }

    protected function funcLinkToDataTable($params)
    {
        $params = $this->getParamsArray($params);

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $tmp = $this->Table->getTotum()->getTable($tableRow);
        $tmp->addData(['tbl' => $params['data'] ?? [], 'params' => ($params['params'] ?? [])]);

        if (empty($params['width'])) {
            $width = 130;
            foreach ($tmp->getVisibleFields('web', true)['column'] as $field) {
                $width += $field['width'];
            }
            if ($width > 1200) {
                $width = 1200;
            }
        } else {
            $width = $params['width'];
        }
        $table = [
            'title' => $params['title'] ?? $tableRow['title'],
            'table_id' => $tableRow['id'],
            'sess_hash' => $tmp->getTableRow()['sess_hash'],
            'width' => $width,
            'height' => $params['height'] ?? '80vh'
        ];

        $this->Table->getTotum()->addToInterfaceDatas(
            'table',
            $table,
            $params['refresh'] ?? false,
            ['header' => $params['header'] ?? true,
                'footer' => $params['footer'] ?? true]
        );
    }

    protected function funcLinkToAnonymTable($params)
    {
        $params = $this->getParamsArray($params);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        if ($tableRow['type'] !== "tmp") {
            throw new errorException('Только для временных таблиц');
        }
        $d = [];
        if (!empty($params['data'])) {
            $d['d'] = $params['data'];
        }
        if (!empty($params['params'])) {
            $d['p'] = $params['params'];
        }
        $t = $tableRow['id'];
        if ($d) {
            $t = $tableRow['id'] . '?d=' . urlencode(Crypt::getCrypted(
                json_encode($d, JSON_UNESCAPED_UNICODE),
                $this->Table->getTotum()->getConfig()->getCryptSolt()
            ));
        }
        return $this->Table->getTotum()->getConfig()->getAnonymHost() . '/' . $this->Table->getTotum()->getConfig()->getAnonymModul() . '/' . $t;
    }

    protected function funcLinkToDataText($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? 'Здесь должен быть title';

        $width = $params['width'] ?? 600;

        $this->Table->getTotum()->addToInterfaceDatas(
            'text',
            ['title' => $title, 'width' => $width, 'text' => htmlspecialchars($params['text'] ?? '')],
            $params['refresh'] ?? false
        );
    }

    protected function funcLinkToDataHtml($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? 'Здесь должен быть title';

        $width = $params['width'] ?? 600;

        $this->Table->getTotum()->addToInterfaceDatas(
            'text',
            ['title' => $title, 'width' => $width, 'text' => $params['html'] ?? ''],
            $params['refresh'] ?? false
        );
    }

    protected function funcNormalizeN($params)
    {
        $params = $this->getParamsArray($params);

        if (!key_exists(
            'num',
            $params
        ) || !is_numeric(strval($params['num']))) {
            throw new errorException('Параметр num обязателен и должен быть числом');
        }
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'NormalizeN');

        /** @var RealTables $table */
        $table = $this->Table->getTotum()->getTable($tableRow);
        if (!is_a(
            $table,
            RealTables::class
        )) {
            throw new errorException('Нормализация проводится только для простых таблиц и таблиц циклов');
        }
        if (!$tableRow['with_order_field']) {
            throw new errorException('Таблица не сортируется по N');
        }

        if ($table->getNTailLength() >= (int)$params['num']) {
            $table->normalizeN();
        }
    }

    protected function funcLinkToTable($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'LinkToTable');

        $link = '/Table/';

        if ($tableRow['type'] === 'calcs') {
            if ($topTableRow = $this->Table->getTotum()->getTableRow($tableRow['tree_node_id'])) {
                if ($this->Table->getTableRow()['type'] === 'calcs' && (int)$tableRow['tree_node_id'] === $this->Table->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                    $Cycle_id = $this->Table->getCycle()->getId();
                } else {
                    $this->__checkNumericParam($params['cycle'], 'cycle', 'LinkToTable');
                    $Cycle_id = $params['cycle'];
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableRow['id'];
                $Cycle = $this->Table->getTotum()->getCycle($Cycle_id, $tableRow['tree_node_id']);
                $linkedTable = $Cycle->getTable($tableRow);
            } else {
                throw new errorException('Таблица циклов указана неверно');
            }
        } else {
            $linkedTable = $this->Table->getTotum()->getTable($tableRow);
            $link .= ($tableRow ['top'] ? $tableRow ['top'] : 0) . '/' . $tableRow['id'];
        }

        $iParams = false;

        $fields = $linkedTable->getFields();
        if (!empty($params['filter'])) {
            $filters = [];
            foreach ($params['filter'] as $i => $f) {
                if ($f['field'] === 'id' || !empty($fields[$f['field']])) {
                    $filters[$f['field']] = $f['value'];
                }
            }
            if ($filters) {
                $cripted = Crypt::getCrypted(json_encode($filters, JSON_UNESCAPED_UNICODE));
                ;
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


        $this->Table->getTotum()->addToInterfaceLink(
            $link,
            $params['target'] ?? 'self',
            $params['title'] ?? $tableRow['title'],
            null,
            $params['width'] ?? null,
            $params['refresh'] ?? false,
            ['header' => $params['header'] ?? true,
                'footer' => $params['footer'] ?? true]
        );
    }

    protected function funcLinkToScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match(
            '`https?://`',
            $params['uri']
        )) {
            throw new errorException('Параметр uri обязателен и должен начитаться с http/https');
        }

        $link = $params['uri'];
        $title = $params['title'] ?? 'Обращение к стороннему скрипту';
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'linkToScript');
        }


        $this->Table->getTotum()->addToInterfaceLink(
            $link,
            $params['target'] ?? 'self',
            $title,
            $post ?? null,
            $params['width'] ?? null,
            $params['refresh'] ?? false
        );
    }

    protected function funcGetFromScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match(
            '`https?://`',
            $params['uri']
        )) {
            throw new errorException('Параметр uri обязателен и должен начитаться с http/https');
        }

        $link = $params['uri'];
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'GetFromScript');
        } elseif (!empty($params['posts'])) {
            $post = $params['posts'];
        } else {
            $post = null;
        }


        if (!empty($params['gets'])) {
            $link .= strpos($link, '?') === false ? '?' : '&';
            $link .= http_build_query($params['gets']);
        }

        $toBfl = $params['bfl'] ?? in_array(
            'script',
            $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
        );

        try {
            $r = $this->cURL(
                $link,
                'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                $params['header'] ?? 0,
                $params['cookie'] ?? '',
                $post,
                ($params['timeout'] ?? null),
                ($params['headers'] ?? "")
            );
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    "getFromScript",
                    [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            return $r;
        } catch (Exception $e) {
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    "getFromScript:",
                    ['error' => $e->getMessage()] + [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            throw new errorException($e->getMessage());
        }
    }

    protected function __getActionFields($fieldParams, $funcName)
    {
        $fields = [];

        if (empty($fieldParams)) {
            return false;
        }
        foreach ($fieldParams as $f) {
            $fc = $this->getCodes($f);

            try {
                if (count($fc) < 2) {
                    throw new Exception();
                }


                $fieldName = $this->__getValue($fc[0]);
                if (empty($fieldName)) {
                    throw new Exception();
                }


                $fieldValue = $this->__getValue($fc[2] ?? $fc[1]);

                if (in_array(strtolower($funcName), ['set', 'setlist', 'setlistextended'])) {
                    if ($fc[1]['type'] === 'operator') {
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
            } catch (SqlException $e) {
                throw $e;

                throw new errorException($e->getMessage() . ' [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            } catch (Exception $e) {
                throw new errorException('Неправильное оформление кода в [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            }
            $fields[$fieldName] = $fieldValue;
        }
        return $fields;
    }

    protected function funcInsert($params)
    {
        if ($params = $this->getParamsArray($params, ['field', 'cycle'], ['field'])) {
            if (empty($params['cycle']) && $this->Table->getCycle()) {
                $params['cycle'] = [$this->Table->getCycle()->getId()];
            }
            $addedIds = [];
            $funcSet = function ($params) use (&$addedIds) {
                $table = $this->getSourceTable($params);
                if ($params['field']) {
                    $fields = $this->__getActionFields($params['field'], 'Insert');
                } else {
                    $fields = [];
                }

                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }

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


            if (!empty($params['inserts']) && !is_array($params['inserts'])) {
                $this->vars[$params['inserts']] = $addedIds;
            }
        }
    }

    protected function funcinsertListExtended($params)
    {
        return $this->funcInsertListExt($params);
    }


    protected function funcTableLog($params)
    {
        if ($params = $this->getParamsArray($params)) {
            $table = $this->getSourceTable($params);
            if ($table->getTableRow()['type'] === 'tmp') {
                throw new errorException('Нельзя писать в лог данные временной таблицы');
            }
            if (empty($params['field'])) {
                throw new errorException('Заполните поле field');
            }
            if (!($field = $table->getFields()[$params['field']])) {
                throw new errorException('Поле [[' . $params['field'] . ']] не найдено в таблице ' . $table->getTableRow()['name']);
            }
            if ($field['category'] === 'column') {
                if (!is_numeric($params['id'])) {
                    throw new errorException('Поле id должно быть числовым');
                }


                $valID = $table->getByParams(
                    ['field' => ['id', $params['field']], 'where' => [['field' => 'id', 'operator' => '=', 'value' => $params['id']]]],
                    'row'
                );
                if (!$valID) {
                    throw new errorException('Строка с ид ' . $params['id'] . ' не найдена в таблице ' . $table->getTableRow()['name']);
                }
                $val = $valID[$params['field']];
            } else {
                $val = $table->getByParams(['field' => [$params['field']]], 'field');
            }
            $this->Table->getTotum()->totumActionsLogger()->innerLog(
                $table->getTableRow()['id'],
                $table->getCycle() ? $table->getCycle()->getId() : null,
                $params['id'] ?? null,
                $params['field'],
                $params['comment'] ?? null,
                $val
            );
        }
    }

    protected function funcInsertListExt($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);
        $MainList = [];
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table', 'InsertListExtended');

        if ($params['fields'] ?? null) {
            if (!is_array($params['fields'])) {
                throw new errorException('Параметр fields должен быть row или list');
            }

            if (ctype_digit((string)array_keys($params['fields'])[0])) {
                /*Массив из row как от функции selectRowList*/
                foreach ($params['fields'] as $i => $row) {
                    foreach ($row as $f => $val) {
                        $MainList[$f][$i] = $val ?? null;
                    }
                }
            } else {
                /*row листов*/
                $MainList = $params['fields'];
            }
        }

        $rows = $MainList;
        foreach ($params['field'] as $f) {
            $f = $this->getExecParamVal($f);
            $rows = array_replace($rows, $f);
        }

        $listCount = 0;
        $rowList = [];
        foreach ($rows as $f => $list) {
            if (is_array($rows[$f]) && key_exists(0, $rows[$f])) {
                if (count($rows[$f]) > $listCount) {
                    $listCount = count($rows[$f]);
                }
            }
        }
        foreach ($rows as $f => &$list) {
            if (!is_array($rows[$f]) || !key_exists(0, $rows[$f])) {
                $list = array_fill(0, $listCount, $list);
            } else {
                if (count($list) < $listCount) {
                    $diff = $listCount - count($list);
                    for ($i = 0; $i < $diff; $i++) {
                        $list[] = null;
                    }
                }
            }
            $list = array_values($list);
        }
        unset($list);

        for ($i = 0; $i < $listCount; $i++) {
            $rowList[$i] = [];
            foreach ($rows as $f => $list) {
                $rowList[$i][$f] = $list[$i];
            }
        }

        if (($rowList)) {
            if ($tableRow['type'] === 'calcs') {
                if (empty($params['cycle']) && $this->Table->getTableRow()['type'] === 'calcs') {
                    $params['cycle'] = [$this->Table->getCycle()->getId()];
                }
            } else {
                unset($params['cycle']);
            }

            $addedIds = [];

            $funcSet = function ($params) use ($rowList, &$addedIds) {
                $table = $this->getSourceTable($params);

                if (!empty($params['log'])) {
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
            if (!empty($params['inserts']) && !is_array($params['inserts'])) {
                $this->vars[$params['inserts']] = $addedIds;
            }
        }
    }

    protected function funcInsertList($params)
    {
        return $this->funcInsertListExt($params);
    }

    protected function __doAction($params, $func, $isFieldSimple = false)
    {
        $notPrepareParams = $isFieldSimple ? [] : ['field'];

        if ($params = $this->getParamsArray($params, ['field'], $notPrepareParams)) {
            if (empty($params['cycle']) && $this->Table->getTableRow()['type'] === 'calcs') {
                $params['cycle'] = [$this->Table->getCycle()->getId()];
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
        }
    }

    protected function funcSet($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $fields = $this->__getActionFields($params['field'], 'Set');
                $where = $params['where'] ?? [];

                $table->actionSet($fields, $where, 1);
            }
        );
    }

    protected function funcDelete($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $table->actionDelete($where, 1);
            }
        );
    }

    protected function funcDuplicate($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $fields = $this->__getActionFields($params['field'], 'Duplicate');

                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }

                $where = $params['where'] ?? [];
                $addedIds = $table->actionDuplicate($fields, $where, 1, $params['after'] ?? null);
                if (!empty($params['inserts']) && !is_array($params['inserts'])) {
                    $this->vars[$params['inserts']] = $addedIds;
                }
            }
        );
    }

    protected function funcDuplicateList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $fields = $this->__getActionFields($params['field'], 'DuplicateList');

                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }

                $where = $params['where'] ?? [];
                $addedIds = $table->actionDuplicate($fields, $where, null, $params['after']);
                if (!empty($params['inserts']) && !is_array($params['inserts'])) {
                    $this->vars[$params['inserts']] = $addedIds;
                }
            }
        );
    }

    protected function funcDeleteList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $table->actionDelete($where, null);
            }
        );
    }

    protected function funcClear($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                $where = $params['where'] ?? [];

                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }

                $table->actionClear($params['field'], $where, 1);
            },
            true
        );
    }

    protected function funcPin($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionPin($params['field'], $where, 1);
            },
            true
        );
    }

    protected function funcPinList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionPin($params['field'], $where, null);
            },
            true
        );
    }

    protected function funcSetList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $fields = $this->__getActionFields($params['field'], 'SetList');
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $where = $params['where'] ?? [];
                $table->actionSet($fields, $where, null);
            }
        );
    }

    protected function __execButtonList($params)
    {
        $table = $this->getSourceTable($params);
        $params['field'] = $params['field'][0] ?? null;
        if (!$params['field']) {
            throw new errorException('Поле кнопки не указано');
        }
        if (!key_exists(
            $params['field'],
            $table->getFields()
        )) {
            throw new errorException('Поле кнопки не указано');
        }

        $field = $table->getFields()[$params['field']];

        $CA = new CalculateAction($field['codeAction']);
        if ($field['category'] === "column") {
            $params['field'] = ['__all__'];
            $rows = $table->getByParams($params, 'rows');
            foreach ($rows as $row) {
                if (is_a($table, RealTables::class)) {
                    $row = RealTables::decodeRow($row);
                }

                $CA->execAction(
                    $field['name'],
                    $row,
                    $row,
                    $table->getTbl(),
                    $table->getTbl(),
                    $table
                );
            }
        } else {
            $CA->execAction(
                $field['name'],
                $table->getTbl()['params'],
                $table->getTbl()['params'],
                $table->getTbl(),
                $table->getTbl(),
                $table
            );
        }
    }

    protected function funcExecButtonList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $this->__execButtonList($params);
            },
            true
        );
    }

    protected function funcExecButton($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $params['limit'] = 1;
                $this->__execButtonList($params);
            },
            true
        );
    }

    protected function funcSetListExtended($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $fields = $this->__getActionFields($params['field'], 'SetListExtended');

                $where = $params['where'] ?? [];
                $modify = $table->getModifyForActionSetExtended($fields, $where);

                if ($modify) {
                    if (!empty($params['log'])) {
                        $table->setWithALogTrue();
                    }

                    $table->reCalculateFromOvers(
                        [
                            'modify' => $modify
                        ]
                    );
                }
            }
        );
    }


    protected function funcClearList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue();
                }
                $table->actionClear($params['field'], $where, null);
            },
            true
        );
    }

    public static function cURL($url, $ref = '', $header = 0, $cookie = '', $post = null, $timeout = null, $headers = null)
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

        if ($headers) {
            $headers = (array)$headers;
        }
        if ($cookie) {
            $headers[] = "Cookie: " . $cookie;
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$headers);
        }

        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            curl_close($ch);
            throw new errorException($error);
        }
        curl_close($ch);
        return $result;
    }
}
