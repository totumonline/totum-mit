<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.06.17
 * Time: 15:02
 */

namespace totum\common\calculates;

use SoapClient;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\Totum;
use totum\common\TotumInstall;
use totum\models\TmpTables;
use totum\tableTypes\aTable;
use totum\tableTypes\RealTables;
use Exception;
use totum\tableTypes\tmpTable;

class CalculateAction extends Calculate
{
    protected $allStartSections = [];

    protected function formStartSections()
    {
        foreach ($this->code as $k => $v) {
            if (preg_match('/^([a-z]*)([\d]*)=$/', $k, $matches) && (!$matches[1] || $matches[2] !== '')) {
                $this->allStartSections[$matches[1]][$k] = $v;
                unset($this->code[$k]);
            }
        }


        foreach ($this->allStartSections as &$v) {
            uksort(
                $v,
                function ($a, $b) {
                    if ($a === '=') {
                        return -1;
                    }
                    if ($b === '=') {
                        return 1;
                    }
                    $a = str_replace('=', '', $a);
                    $b = str_replace('=', '', $b);
                    return $a <=> $b;
                }
            );
        }
    }

    protected function funcNotificationSend($params)
    {
        $params = $this->getParamsArray($params, ['custom'], [], ['custom']);

        $this->__checkNotArrayParams($params, ['title', 'eml', 'ntf']);
        $this->__checkNotEmptyParams($params, ['title']);
        if (!empty($params['users'])) {
            $users = (array)$params['users'];
            foreach ($users as $user) {
                if (!is_numeric($user)) {
                    throw new errorException($this->translate('Parameter %s must contain list of numbers', 'users'));
                }
            }
            $users = $this->Table->getTotum()->getModel('users')->getAll([
                'id' => $users,
                'interface' => 'web',
                'on_off' => 'true'
            ], 'id, email');

            if (empty($users)) {
                return;
            }

            if (!empty($params['ntf'])) {
                $add = [];
                foreach ($users as $user) {
                    $add[] = ['user_id' => $user['id'], 'title' => $params['title'], 'code' => 'admin_text', 'vars' => ['text' => $params['ntf']], 'active' => true];
                }
                $this->Table->getTotum()->getTable('notifications')->reCalculateFromOvers(
                    ['add' => $add]
                );
            }
            if (!empty($params['eml'])) {
                $emails = [];
                foreach ($users as $user) {
                    if (!empty($user['email'])) {
                        $emails[] = $user['email'];
                    }
                }
                if ($emails) {
                    $template = $this->Table->getTotum()->getConfig()->getModel('print_templates')->get(['name' => 'eml_email'],
                        'styles, html');

                    $template['body'] = preg_replace_callback(
                        '/{([a-zA-Z_]+)}/',
                        function ($match) use ($params) {
                            return match ($match[1]) {
                                'Title_of_notification' => $params['title'],
                                'Text' => $params['eml'],
                                'domen' => $this->Table->getTotum()->getConfig()->getFullHostName(),
                                default => null,
                            };
                        },
                        $template['html']
                    );
                    $eml = '<style>' . $template['styles'] . '</style>' . $template['body'];

                    $toBfl = $params['bfl'] ?? in_array(
                            'email',
                            $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
                        );

                    try {
                        foreach ($emails as $email) {
                            $this->Table->getTotum()->getConfig()->sendMail(
                                $email,
                                $params['title'],
                                $eml
                            );

                            if ($toBfl) {
                                $this->Table->getTotum()->getOutersLogger()->debug('email', $params);
                            }
                        }
                    } catch (Exception $e) {
                        if ($toBfl) {
                            $this->Table->getTotum()->getOutersLogger()->error(
                                'email',
                                ['error' => $e->getMessage()] + $params
                            );
                        }
                        throw new errorException($e->getMessage());
                    }
                }
            }
            if (!empty($params['custom'])) {
                $codes = [];
                foreach ($this->Table->getTotum()->getModel('ttm__custom_user_notific_codes')->getAll([
                    'name' => array_column($params['custom'], 'field')
                ], 'name, code') as $row) {
                    $codes[$row['name']] = $row['code'];
                };

                foreach ($params['custom'] as $p) {
                    $name = $p['field'];
                    $html = $p['value'];
                    if (empty($html) || empty($code = $codes[$name] ?? null)) {
                        continue;
                    }

                    if ($this->Table->getTotum()->getConfig()->isExecSSHOn('inner')) {
                        foreach ($users as $user) {
                            $Vars = ['user' => $user['id'], 'title' => $params['title'], 'html' => $html];

                            $data = ['code' => $code, 'vars' => $Vars];

                            $data = base64_encode(json_encode($data,
                                JSON_UNESCAPED_UNICODE));

                            $path = $this->Table->getTotum()->getConfig()->getBaseDir();

                            $schema = '';

                            if (method_exists($this->Table->getTotum()->getConfig(), 'setHostSchema')) {
                                $schema = '--schema "' . $this->Table->getTotum()->getConfig()->getSchema() . '"';
                            }

                            `cd {$path} && bin/totum exec {$schema} {$this->Table->getUser()->getId()} {$data} > /dev/null 2>&1 &`;
                        }
                    } else {
                        $CA = new static($code);
                        try {
                            foreach ($users as $user) {
                                $Vars = ['user' => $user['id'], 'title' => $params['title'], 'html' => $html];
                                $CA->execAction(
                                    $this->varName,
                                    $this->oldRow,
                                    $this->row,
                                    $this->oldTbl,
                                    $this->tbl,
                                    $this->Table,
                                    $this->vars['tpa'],
                                    $Vars
                                );
                                $this->newLogParent['children'][] = $CA->getLogVar();
                            }
                        } catch (errorException $e) {
                            $this->newLogParent['children'][] = $CA->getLogVar();
                            throw $e;
                        }
                    }
                }
            }

        }

    }

    protected function funcExec(string $params): mixed
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            $code = $params['code'] = $params['code'] ?? $params['kod'] ?? null;

            $this->__checkNotEmptyParams($params, ['code']);
            $this->__checkNotArrayParams($params, ['code']);


            if (preg_match('/^[a-z_0-9]{3,}$/', $code) && key_exists($code, $this->Table->getFields())) {
                $code = $this->Table->getFields()[$code]['codeAction'] ?? '';
            }

            if (key_exists('ssh',
                    $params) && $params['ssh'] && ($params['ssh'] === 'true' || $params['ssh'] === true || $params['ssh'] === 'test')) {

                if (!$this->Table->getTotum()->getConfig()->isExecSSHOn('inner')) {
                    throw new criticalErrorException($this->translate('Ssh:true in exec function is disabled. Enable execSSHOn in Conf.php.'));
                }

                $Vars = [];
                foreach ($params['var'] ?? [] as $v) {
                    $Vars = array_merge($Vars, $this->getExecParamVal($v, 'var'));
                }

                $data = ['code' => $code, 'vars' => $Vars];
                $test = '> /dev/null 2>&1 &';
                if ($params['ssh'] === 'test') {
                    $data['test'] = true;
                    $test = '2>&1';
                }

                $data = base64_encode(json_encode($data,
                    JSON_UNESCAPED_UNICODE));

                $path = $this->Table->getTotum()->getConfig()->getBaseDir();

                $schema = '';

                if (method_exists($this->Table->getTotum()->getConfig(), 'setHostSchema')) {
                    $schema = '--schema "' . $this->Table->getTotum()->getConfig()->getSchema() . '"';
                }

                return `cd {$path} && bin/totum exec {$schema} {$this->Table->getUser()->getId()} {$data} {$test}`;

            } else {


                $CA = new static($code);
                try {
                    $Vars = [];
                    foreach ($params['var'] ?? [] as $v) {
                        $Vars = array_merge($Vars, $this->getExecParamVal($v, 'var'));
                    }

                    $r = $CA->execAction(
                        $this->varName,
                        $this->oldRow,
                        $this->row,
                        $this->oldTbl,
                        $this->tbl,
                        $this->Table,
                        $this->vars['tpa'],
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
        return null;
    }

    protected function funcSchemaGzStringGet($params)
    {
        $params = $this->getParamsArray($params);
        if (!$this->Table->getUser()->isCreator()) {
            throw new errorException($this->translate('The function is only available for the Creator role.'));
        }

        if ($params['password'] !== $this->Table->getUser()->getVar('pass')) {
            throw new errorException($this->translate('Password doesn\'t match.'));
        }

        $pathDbPsql = $this->Table->getTotum()->getConfig()->getSshPostgreConnect('pg_dump');

        $tmpFilename = tempnam(
            $this->Table->getTotum()->getConfig()->getTmpDir(),
            $this->Table->getTotum()->getConfig()->getSchema() . '.' . $this->Table->getUser()->getId() . '.'
        );

        $schema = $this->Table->getTotum()->getConfig()->getSchema();
        $exclude = "--exclude-table-data='{$schema}._tmp_tables'";
        if (empty($params['withlog'])) {
            $exclude .= " --exclude-table-data='{$schema}._log'";
        }
        if (empty($params['withbfl'])) {
            $exclude .= " --exclude-table-data='{$schema}._bfl'";
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
            throw new errorException($this->translate('The function is only available for the Creator role.'));
        }

        if ($params['password'] !== $this->Table->getUser()->getVar('pass')) {
            throw new errorException($this->translate('Password doesn\'t match.'));
        }
        if (empty($params['gzstring'])) {
            throw new errorException($this->translate('Scheme string is empty.'));
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
            $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

            if ($tableRow['type'] !== 'cycles') {
                throw new errorException($this->translate('The function is only available for cycles tables.'));
            }
            $params['cycle'] = $params['cycle'] ?? null;

            if (!is_array($params['cycle']) && empty($params['cycle'])) {
                if ($this->Table->getTableRow()['id'] === $tableRow['id']) {
                    $params['cycle'] = $this->row['id'] ?? null;
                } else {
                    throw new errorException($this->translate('Fill in the parameter [[%s]].', 'cycle'));
                }
            }

            $Cycles = (array)$params['cycle'];
            foreach ($Cycles as $cycleId) {
                if (empty($cycleId)) {
                    continue;
                }
                $params['cycle'] = $cycleId;
                $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['id']);
                $Cycle->recalculate();
            }
        }
    }

    public function execAction($varName, $oldRow, $newRow, $oldTbl, $newTbl, aTable $table, string $type, $var = [])
    {
        $var['tpa'] = $type;
        $r = $this->exec(['name' => $varName], ['v' => null], $oldRow, $newRow, $oldTbl, $newTbl, $table, $var);
        return $r;
    }

    public function exec($fieldData, array $newVal, $oldRow, $row, $oldTbl, $tbl, aTable $table, $vars = []): mixed
    {
        $s = match ($vars['tpa'] ?? null) {
            'add' => 'ad',
            'delete' => 'dl',
            'change' => 'ch',
            'click' => 'cl',
            'exec' => 'ex',
            default => throw new errorException($this->translate('System error. Action type not specified.')),
        };
        $this->startSections = array_merge(
            $this->allStartSections[''] ?? [],
            $this->allStartSections['a'] ?? [],
            $this->allStartSections[$s] ?? []
        );
        return parent::exec($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table, $vars);
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

        $params['schema'] = $TotumInstall->applyMatches($params['schema'], $params);
        if (empty($params['matches_name'])) {
            throw new errorException($this->translate('Scheme source not defined.'));
        }

        $TotumInstall->updateSchema($params['schema'], true, $params['matches_name']);
    }

    protected function funcLinkToButtons($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['title', 'buttons']);
        $this->__checkNotArrayParams($params, ['title']);
        $this->__checkListParam($params, 'buttons');

        $params['refresh'] = $params['refresh'] ?? false;
        $params['env'] = $this->getEnvironment();

        $requiredByttonParams = ['text', 'code'];
        $buttons = [];
        foreach ($params['buttons'] as $btn) {
            foreach ($requiredByttonParams as $req) {
                if (empty($btn[$req])) {
                    throw new errorException($this->translate('Each button must contain [[%s]].', $req));
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

    /* *
     * used in funclinktoinputselect
     * */
    protected function funcLinkToInput($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);
        if (empty($params['title'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'title'));
        }
        if (empty($params['code'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'code'));
        }

        $params['input'] = '';
        $vars = [];
        foreach ($params['var'] ?? [] as $_) {
            $vars[$_['field']] = $_['value'];
        }
        $params['vars'] = $vars;
        $params['env'] = $this->getEnvironment();

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
                ['value' => 1, 'title' => 1, 'html' => 1, 'hash' => 1, 'refresh' => 1, 'button' => 1, 'close' => 1, 'type' => 1, 'multiple' => 1]
            )
        );
    }

    protected function funcLinkToInputSelect($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);
        $params['type'] = 'select';
        $this->__checkNotEmptyParams($params, ['codeselect']);
        $this->funcLinkToInput($params);
    }

    protected function funcLinkToEdit($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);

        $this->__checkNotEmptyParams($params, 'field');
        $this->__checkNotArrayParams($params, 'field');

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');
        $LinkedTable = $this->getActionTable($tableRow, $params);

        if (!key_exists($params['field'], $LinkedTable->getFields())) {
            throw new errorException(
                $this->translate('The [[%s]] field is not found in the [[%s]] table.',
                    [$params['field'], $LinkedTable->getTableRow()['name']]));
        } elseif (in_array($LinkedTable->getFields()[$params['field']]['type'], ['link', 'button', 'fieldParams'])) {
            throw new errorException(
                $this->translate('Function [[linkToEdit]] not available for [[%s]] field type.',
                    $LinkedTable->getFields()[$params['field']]['type']));
        }

        $Field = Field::init($LinkedTable->getFields()[$params['field']], $LinkedTable);

        if (!empty($params['id'])) {
            $this->__checkNumericParam($params['id'], 'id');
            $LinkedTable->loadFilteredRows('inner', [$params['id']]);
            if (!key_exists($params['id'], $LinkedTable->getTbl()['rows'])) {
                throw new errorException($this->translate('Row not found'));
            }
            $value = $LinkedTable->getTbl()['rows'][$params['id']][$params['field']] ?? ['v' => null];
            $Field->addViewValues('edit',
                $value,
                $LinkedTable->getTbl()['rows'][$params['id']],
                $LinkedTable->getTbl());

        } else {
            $value = $LinkedTable->getTbl()['params'][$params['field']] ?? ['v' => null];
        }


        $data = [];
        $data['table']['name'] = $LinkedTable->getTableRow()['name'];
        $data['table']['field'] = $params['field'];
        if ($params['id'] ?? 0) {
            $data['table']['id'] = $params['id'];
        }

        switch ($LinkedTable->getTableRow()['type']) {
            case 'calcs':
                $data['table']['extra'] = $LinkedTable->getCycle()->getId();
                break;
            case 'tmp':
                $data['table']['extra'] = $LinkedTable->getTableRow()['sess_hash'];
        }

        /** @var TmpTables $model */
        $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

        $newHash = $model->getNewHash(TmpTables::SERVICE_TABLES['linktoedit'],
            $this->Table->getTotum()->getUser(),
            $data);

        $fieldData = $LinkedTable->getFields()[$params['field']];
        foreach ($fieldData as $k => &$v) {
            if (in_array($k, Totum::FIELD_CODE_PARAMS)) {
                $v = true;
            } elseif (in_array($k, Totum::FIELD_ROLES_PARAMS)) {
                $v = [];
            }
        }
        if ($fieldData['type'] === 'number') {
            $fieldData['dectimalSeparator'] = $fieldData['dectimalSeparator'] ?? $this->Table->getTotum()->getConfig()->getSettings('numbers_format')['dectimalSeparator'] ?? ',';
        }

        $this->Table->getTotum()->addToInterfaceDatas(
            'editField',
            [
                'title' => $params['title'],
                'hash' => $newHash,
                'field' => $fieldData,
                'value' => $value,
                'refresh' => $params['refresh'] ?? false
            ]
        );
    }

    protected function funcSleep(string $params)
    {
        $params = $this->getParamsArray($params, [], []);
        $sec = (float)($params['sec'] ?? 0);
        $sleep = (int)floor($sec);
        $usleep = $sec - $sleep;
        $usleep *= 1000000;

        if ($sleep > 0) {
            sleep($sleep);
        }
        if ($usleep > 0) {
            usleep($usleep);
        }

    }

    protected function funcEmailSend($params)
    {
        $params = $this->getParamsArray($params, [], []);

        if (empty($params['to'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'to'));
        }
        if (empty($params['title'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'title'));
        }
        if (empty($params['body'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'body'));
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
                $this->Table->getTotum()->getOutersLogger()->debug('email', $params);
            }
            return $r;
        } catch (Exception $e) {
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    'email',
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
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'try'));
        }
        if (!array_key_exists('catch', $paramsBefore)) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'catch'));
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
            throw new errorException($this->translate('The parameter [[%s]] should be of type row/list.', 'options'));
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
                    'SOAP',
                    ['xml_request' => $soapClient->__getLastRequest(), 'xml_response' => $soapClient->__getLastResponse(), 'data_request' => $params, 'data_response' => $data]
                );
            }
            return $data;
        } catch (Exception $e) {
            if ($toBfl) {
                if (!empty($soapClient)) {
                    $this->Table->getTotum()->getOutersLogger()->error(
                        'SOAP' . $e->getMessage(),
                        ['xml_request' => $soapClient->__getLastRequest(),
                            'xml_response' => $soapClient->__getLastResponse(),
                            'data_request' => $params, 'data_response' => $data ?? '',
                            'error' => $e->getMessage()]
                    );
                } else {
                    $this->Table->getTotum()->getOutersLogger()->error(
                        'SOAP',
                        ['xml_request' => null, 'xml_response' => null,
                            'data_request' => $params, 'data_response' => null,
                            'error' => $e->getMessage()]
                    );
                }
            }
            throw new errorException($e->getMessage());
        }
    }

    protected function funcLinkToFileDownload($params)
    {
        $params = $this->getParamsArray($params, ['file']);
        $files = array_merge($params['files'] ?? [], $params['file'] ?? []);
        $this->__checkNotArrayParams($params, ['zip']);

        $checkFile = function ($file, $withType = true) {
            if (empty($file['name'])) {
                throw new errorException($this->translate('Fill in the parameter [[%s]].', 'name'));
            }
            if (empty($file['filestring'])) {
                throw new errorException($this->translate('Fill in the parameter [[%s]].', 'filestring'));
            }
            if ($withType && empty($file['type'])) {
                throw new errorException($this->translate('Fill in the parameter [[%s]].', 'type'));
            }
        };


        if (!empty($params['zip'])) {
            $zip = new \ZipArchive();
            $Config = $this->Table->getTotum()->getConfig();
            $tmp_file = tempnam($Config->getTmpDir(), $Config->getSchema() . '.FilesDownloadZip'.$this->Table->getTotum()->getUser()->getId().'.');
            unlink($tmp_file);
            if ($zip->open($tmp_file, \ZipArchive::CREATE)) {
                foreach ($files as $file) {
                    $checkFile($file, false);
                    $zip->addFromString($file['name'], $file['filestring']);
                }
                $zip->close();
                if (!str_ends_with($name = $params['zip'], '.zip')) {
                    $name .= '.zip';
                }
                $files = [
                    ['name' => $name, 'type' => 'application/zip', 'string' => base64_encode(file_get_contents($tmp_file))]
                ];
                unlink($tmp_file);
            } else {
                throw new errorException('Creation zip archive error');
            }

        } else {
            foreach ($files as &$file) {
                $checkFile($file);
                $file['string'] = base64_encode($file['filestring']);
                unset($file['filestring']);
            }
            unset($file);
        }

        $this->Table->getTotum()->addToInterfaceDatas('files', ['files' => $files]);
    }

    protected function funcLinkToFileUpload($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);
        if (empty($params['title'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'title'));
        }
        if (empty($params['code'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'code'));
        }

        $vars = [];
        foreach ($params['var'] ?? [] as $_) {
            $vars[$_['field']] = $_['value'];
        }

        $saveData = [];
        $saveData['vars'] = $vars;
        $saveData['env'] = $this->getEnvironment();
        $saveData['code'] = $params['code'];

        $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

        do {
            $hash = md5(microtime(true) . '__linktofileupload_' . mt_srand());
            $key = ['table_name' => '_linkToFileUpload', 'user_id' => $this->Table->getUser()->getId(), 'hash' => $hash];
        } while ($model->getField('user_id', $key));


        $vars = array_merge(
            ['tbl' => json_encode(
                $saveData,
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
            'fileUpload',
            ['hash' => $hash, 'type' => $params['type'] ?? '*', 'limit' => $params['limit'] ?? 1, 'title' => $params['title'] ?? ''],
            $params['refresh'] ?? false
        );
    }

    protected function funcLinkToPanel($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field'], ['bfield']);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');
        $link = '/Table/';


        if ($tableRow['type'] === 'calcs') {
            if ($topTableRow = $this->Table->getTotum()->getTableRow($tableRow['tree_node_id'])) {
                if ($this->Table->getTableRow()['type'] === 'calcs' && (int)$tableRow['tree_node_id'] === $this->Table->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                    $Cycle_id = $this->Table->getCycle()->getId();
                } elseif ($this->Table->getTableRow()['type'] === 'cycles' && (int)$tableRow['tree_node_id'] === $this->Table->getTableRow()['id'] && !empty($this->row['id'])) {
                    $Cycle_id = $this->row['id'];
                } else {
                    $this->__checkNumericParam($params['cycle'], 'cycle');
                    $Cycle_id = $params['cycle'];
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableRow['id'] . '/';

                if (!empty($params['bfield'])) {
                    $Table = $this->Table->getTotum()->getCycle($Cycle_id,
                        $topTableRow['id'])->getTable($tableRow['id']);
                }

            } else {
                throw new errorException($this->translate('The cycles table is specified incorrectly.'));
            }
        } else {
            $link .= $tableRow ['top'] . '/' . $tableRow['id'] . '/';
            $Table = $this->Table->getTotum()->getTable($tableRow['id']);
        }

        if (!empty($params['bfield']) && !empty($params['bfield']['value'])) {
            foreach ((array)$params['bfield']['value'] as $bfieldValue) {
                $id = $Table->getByParams(['field' => 'id', 'where' => [
                    ['field' => $params['bfield']['field'], 'operator' => '=', 'value' => $bfieldValue]
                ]], 'field');
                if (!$id) {
                    throw new errorException($this->translate('Value not found'));
                }
                $this->Table->getTotum()->addLinkPanel(
                    $link,
                    $id,
                    [],
                    $params['refresh'] ?? false
                );
            }
        } elseif (!empty($params['id'])) {
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
            foreach ($field as &$v) {
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

        $getTemplate = function ($name) {
            return $this->Table->getTotum()->getModel('print_templates')->getPrepared(
                ['name' => $name],
                'styles, html, name'
            );
        };

        $this->__checkNotArrayParams($params, ['template', 'text']);

        if ($params['template'] ?? null) {
            if ($main = $getTemplate($params['template'])) {
                $mainTemplate = $main['html'];
                $style = $main['styles'];
            } else {
                throw new errorException($this->translate('Template not found.'));
            }
        } elseif ($params['text'] ?? null) {
            $mainTemplate = $params['text'];
            $style = null;
        } else {
            throw new errorException($this->translate('Template not found.'));
        }


        $usedStyles = [];

        $body = $this->replaceTemplates(
            $mainTemplate,
            $params['data'] ?? [],
            $getTemplate,
            $style,
            $usedStyles
        );

        $this->Table->getTotum()->addToInterfaceDatas(
            'print',
            [
                'body' => $body,
                'styles' => $style
            ]
        );
    }

    protected function funcLinkToData($params)
    {
        $params = $this->getParamsArray($params, ['field']);
        switch ($params['type'] ?? '') {
            case 'text':
                $this->funcLinkToDataText($params);
                break;
            case 'table':
                $this->funcLinkToDataTable($params);
                break;
        }
    }


    protected function funcEncriptedFormParams($params)
    {
        $params = $this->getParamsArray($params);
        $d = [];
        if (!empty($params['data'])) {
            $d['d'] = $params['data'];
        }
        if (!empty($params['params'])) {
            $d['p'] = $params['params'];
        }
        if ($d) {
            return 'd=' . urlencode(Crypt::getCrypted(
                    json_encode($d, JSON_UNESCAPED_UNICODE),
                    $this->Table->getTotum()->getConfig()->getCryptSolt()
                ));
        }
    }

    protected function funcLinkToDataText($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? $this->translate('The %s should be here.', 'title');

        $width = $params['width'] ?? 600;

        $htmlspecialchars = htmlspecialchars(is_array($params['text']) ?
            'OBJECT: ' . json_encode($params['text'], JSON_UNESCAPED_UNICODE) :
            $params['text'] ?? '');

        $this->Table->getTotum()->addToInterfaceDatas(
            'text',
            ['title' => $title, 'width' => $width, 'text' => $htmlspecialchars, 'close' => !!($params['close'] ?? false)],
            $params['refresh'] ?? false
        );
    }

    protected function funcLinkToDataJson($params)
    {
        $params = $this->getParamsArray($params, ['var'], [], ['var']);

        $title = $params['title'] ?? $this->translate('The %s should be here.', 'title');

        $width = $params['width'] ?? 600;

        $data = ['title' => $title,
            'width' => $width,
            'json' => $params['data'],
            'refresh' => $params['refresh'] ?? false];


        if (!empty($params['code'])) {
            $vars = [];
            foreach ($params['var'] ?? [] as $var) {
                $vars[$var['field']] = $var['value'];
            }
            $saveData = ['code' => $params['code'], 'var' => $vars];

            /** @var TmpTables $model */
            $model = $this->Table->getTotum()->getNamedModel(TmpTables::class);
            $saveData['env'] = $this->getEnvironment();

            $hash = $model->getNewHash(TmpTables::SERVICE_TABLES['linktodatajson'],
                $this->Table->getTotum()->getUser(),
                $saveData);


            $data['hash'] = $hash;
            $data['buttontext'] = $params['buttontext'] ?? 'OK';
        }

        $this->Table->getTotum()->addToInterfaceDatas(
            'json',
            $data
        );
    }

    protected function funcLinkToDataHtml($params)
    {
        $params = $this->getParamsArray($params);

        $title = $params['title'] ?? $this->translate('The %s should be here.', 'title');

        $width = $params['width'] ?? 600;

        $this->Table->getTotum()->addToInterfaceDatas(
            'text',
            ['title' => $title, 'width' => $width, 'text' => $params['html'] ?? '', 'close' => !!($params['close'] ?? false)],
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
            throw new errorException($this->translate('Parametr [[%s]] is required and should be a number.', 'num'));
        }
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        /** @var RealTables $table */
        $table = $this->Table->getTotum()->getTable($tableRow);
        if (!is_a(
            $table,
            RealTables::class
        )) {
            throw new errorException($this->translate('For simple and cycles tables only.'));
        }
        if (!$tableRow['with_order_field']) {
            throw new errorException($this->translate('The table has no n-sorting.'));
        }

        if ($table->getNTailLength() >= (int)$params['num']) {
            $table->normalizeN();
        }
    }

    protected function funcLinkToTable($params)
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);

        $this->__checkNotEmptyParams($params, ['table']);
        $tableDestRow = $this->__checkTableIdOrName($params['table'], 'table');

        $link = '/Table/';
        $q_params = [];

        if ($this->Table->getTableRow()['type'] === 'cycles' && str_starts_with($this->varName,
                'tab_') && !empty($this->row['id']) && $tableDestRow['type'] != 'calcs') {
            $params['cycle'] = $params['cycle'] ?? null;
            $link .= $this->Table->getTableRow()['top'] . '/' . $this->Table->getTableRow()['id'] . '/' . ($params['cycle'] ?: $this->row['id']) . '/' . $tableDestRow['id'];
            $linkedTable = $this->Table->getTotum()->getTable($tableDestRow);
            $q_params['b'] = $this->varName;

        } elseif ($tableDestRow['type'] === 'calcs') {
            if ($topTableRow = $this->Table->getTotum()->getTableRow($tableDestRow['tree_node_id'])) {

                if (!empty($params['cycle'])) {
                    $this->__checkNumericParam($params['cycle'], 'cycle');
                    $Cycle_id = $params['cycle'];
                } elseif ($this->Table->getTableRow()['type'] === 'calcs' && (int)$tableDestRow['tree_node_id'] === $this->Table->getCycle()->getCyclesTableId()) {
                    $Cycle_id = $this->Table->getCycle()->getId();
                } elseif ($this->Table->getTableRow()['type'] === 'cycles' && (int)$tableDestRow['tree_node_id'] === $this->Table->getTableRow()['id'] && !empty($this->row['id'])) {
                    $Cycle_id = $this->row['id'];
                } else {
                    $this->__checkNotEmptyParams($params, 'cycle');
                }

                $link .= $topTableRow['top'] . '/' . $topTableRow['id'] . '/' . $Cycle_id . '/' . $tableDestRow['id'];
                $Cycle = $this->Table->getTotum()->getCycle($Cycle_id, $tableDestRow['tree_node_id']);
                $linkedTable = $Cycle->getTable($tableDestRow);

            } else {
                throw new errorException($this->translate('The cycles table is specified incorrectly.'));
            }
        } else {
            $linkedTable = $this->Table->getTotum()->getTable($tableDestRow);
            $link .= ($tableDestRow ['top'] ?: 0) . '/' . $tableDestRow['id'];
        }

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
                $q_params['f'] = $cripted;
            }
        }

        if (!empty($params['field'])) {
            $field = $this->__getActionFields($params['field'], 'linkToTable');

            foreach ($field as $k => $v) {
                if (array_key_exists($k, $fields)) {
                    $q_params['a'][$k] = $v;
                }
            }
        }

        if (!empty($params['hash']) && $linkedTable->getTableRow()['type'] === 'tmp') {
            $this->__checkNotArrayParams($params, 'check');
            $q_params['sess_hash'] = $params['hash'];
        }

        $params['target'] = $params['target'] ?? 'self';

        if ($params['target'] === 'iframe' || $params['target'] === 'top-iframe') {
            $q_params['iframe'] = true;
        }

        if ($q_params) {
            $link .= '?' . http_build_query($q_params, '', '&', PHP_QUERY_RFC1738);
        }


        $this->Table->getTotum()->addToInterfaceLink(
            $link,
            $params['target'] ?? 'self',
            $params['title'] ?? $tableDestRow['title'],
            null,
            $params['width'] ?? null,
            $params['refresh'] ?? false,
            [
                'header' => $params['header'] ?? true,
                'footer' => $params['footer'] ?? true,
                'topbuttons' => $params['topbuttons'] ?? true
            ]
        );
    }

    protected function funcLinkToScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        $this->__checkNotEmptyParams($params, ['uri']);
        $this->__checkNotArrayParams($params, ['uri', 'title']);


        $link = $params['uri'];
        $title = $params['title'] ?? $this->translate('Calling a third-party script.');
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

    protected function funcInsert($params)
    {
        if ($params = $this->getParamsArray($params, ['field', 'cycle'], ['field'])) {
            $addedIds = [];
            $funcSet = function ($params) use (&$addedIds) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                if (key_exists('field', $params)) {
                    $fields = $this->__getActionFields($params['field'], 'Insert');
                } else {
                    $fields = [];
                }

                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }

                $fields = $this->clearNONEFields($fields);
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
        $this->funcInsertListExt($params);
    }


    protected function funcTableLog($params)
    {
        if ($params = $this->getParamsArray($params)) {
            $table = $this->getSourceTable($params);

            if (!$table) {
                return;
            }
            if ($table->getTableRow()['type'] === 'tmp') {
                throw new errorException($this->translate('Not for the temporary table.'));
            }
            if (empty($params['field'])) {
                throw new errorException($this->translate('Fill in the parameter [[%s]].', 'field'));
            }
            if (!($field = $table->getFields()[$params['field']])) {
                throw new errorException($this->translate('The [[%s]] field is not found in the [[%s]] table.',
                    [$params['field'], $table->getTableRow()['name']]));
            }
            if ($field['category'] === 'column') {
                if (!is_numeric($params['id'])) {
                    throw new errorException($this->translate('The %s parameter must be a number.', 'id'));
                }


                $valID = $table->getByParams(
                    ['field' => ['id', $params['field']], 'where' => [['field' => 'id', 'operator' => '=', 'value' => $params['id']]]],
                    'row'
                );
                if (!$valID) {
                    throw new errorException($this->translate('The row with %s was not found in table %s.',
                        ['id ' . $params['id'], $table->getTableRow()['name']]));
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
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        if ($params['fields'] ?? null) {
            if (!is_array($params['fields'])) {
                throw new errorException($this->translate('The parameter [[%s]] should be of type row/list.',
                    'fields'));
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
        foreach (($params['field'] ?? []) as $f) {
            $f = $this->getExecParamVal($f, 'field');
            if ($f[array_key_first($f)] === '*NONE*') {
                continue;
            }
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
            if ($tableRow['type'] !== 'calcs') {
                unset($params['cycle']);
            }

            $addedIds = [];

            $funcSet = function ($params) use ($rowList, &$addedIds) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }

                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
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
        $this->funcInsertListExt($params);
    }

    protected function __doAction($params, $func, $isFieldSimple = false)
    {
        $notPrepareParams = $isFieldSimple ? [] : ['field'];

        if ($params = $this->getParamsArray($params, ['field'], $notPrepareParams)) {

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
                if (!empty($params['hash']) && str_starts_with($params['hash'], 'i-')) {
                    $hashData = TmpTables::init($this->Table->getTotum()->getConfig())->getByHash(
                        TmpTables::SERVICE_TABLES['insert_row'],
                        $this->Table->getUser(),
                        $params['hash']
                    );
                    $hashData['_ihash'] = $params['hash'];
                    if (key_exists('_hash', $hashData)) {
                        $params['hash'] = $hashData['_hash'];
                        unset($hashData['_hash']);
                    }

                    $table = $this->getSourceTable($params);

                    if (!$table) {
                        return;
                    }
                    $fields = $this->__getActionFields($params['field'], 'Set');
                    $fields = $this->clearNONEFields($fields);
                    $table->checkInsertRow(null, [], $hashData, $fields);
                } else {
                    $table = $this->getSourceTable($params);

                    if (!$table) {
                        return;
                    }
                    if (!empty($params['log'])) {
                        $table->setWithALogTrue($params['log']);
                    }
                    $fields = $this->__getActionFields($params['field'], 'Set');
                    $fields = $this->clearNONEFields($fields);
                    $where = $params['where'] ?? [];
                    $table->actionSet($fields, $where, 1);
                }
            }
        );
    }

    protected function funcDelete($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $table->actionDelete($where, 1);
            }
        );
    }

    protected function funcRestore($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $table->actionRestore($where, 1);
            }
        );
    }

    protected function funcDuplicate($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                if (empty($params['field'])) {
                    $fields = [];
                } else {
                    $fields = $this->__getActionFields($params['field'], 'Duplicate');
                }

                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
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

                if (!$table) {
                    return;
                }
                $fields = $this->__getActionFields($params['field'], 'DuplicateList');

                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }

                $where = $params['where'] ?? [];
                $addedIds = $table->actionDuplicate($fields, $where, null, $params['after'] ?? null);
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

                if (!$table) {
                    return;
                }
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $table->actionDelete($where, null);
            }
        );
    }

    protected function funcRestoreList($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $table->actionRestore($where, null);
            }
        );
    }

    protected function funcClear($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }

                $where = $params['where'] ?? [];

                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
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

                if (!$table) {
                    return;
                }
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
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

                if (!$table) {
                    return;
                }
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
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

                if (!$table) {
                    return;
                }
                $fields = $this->__getActionFields($params['field'], 'SetList');
                $fields = $this->clearNONEFields($fields);
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $where = $params['where'] ?? [];
                $table->actionSet($fields, $where, null);
            }
        );
    }

    protected function __execButtonList($params)
    {
        $table = $this->getSourceTable($params);

        if (!$table) {
            return;
        }

        $params['field'] = $params['field'][0] ?? null;
        if (!$params['field']) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'field'));
        }
        if (!key_exists(
            $params['field'],
            $table->getFields()
        )) {
            throw new errorException($this->translate('The [[%s]] field is not found in the [[%s]] table.',
                [$params['field'], $table->getTableRow()['name']]));
        }

        $field = $table->getFields()[$params['field']];

        $CA = new CalculateAction($field['codeAction']);
        if ($field['category'] === 'column') {
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
                    $table,
                    'exec'
                );
            }
        } else {
            $CA->execAction(
                $field['name'],
                $table->getTbl()['params'],
                $table->getTbl()['params'],
                $table->getTbl(),
                $table->getTbl(),
                $table,
                'exec'
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

                if (!$table) {
                    return;
                }
                $fields = $this->__getActionFields($params['field'], 'SetListExtended');
                $fields = $this->clearNONEFields($fields);

                $where = $params['where'] ?? [];
                $modify = $table->getModifyForActionSetExtended($fields, $where);

                if ($modify) {
                    if (!empty($params['log'])) {
                        $table->setWithALogTrue($params['log']);
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

                if (!$table) {
                    return;
                }
                $where = $params['where'] ?? [];
                if (!empty($params['log'])) {
                    $table->setWithALogTrue($params['log']);
                }
                $table->actionClear($params['field'], $where, null);
            },
            true
        );
    }

    protected function funcReorder($params)
    {
        $this->__doAction(
            $params,
            function ($params) {
                $this->__checkRequiredParams($params, ['ids'], 'reorder');
                $table = $this->getSourceTable($params);

                if (!$table) {
                    return;
                }
                $table->actionReorder($params['ids'], (int)($params['after'] ?? null));
            },
            true
        );
    }

    protected function getActionTable(array $tableRow, array $params): aTable
    {
        switch ($tableRow['type']) {
            case 'calcs':
                if ($topTableRow = $this->Table->getTotum()->getTableRow($tableRow['tree_node_id'])) {
                    if ($this->Table->getTableRow()['type'] === 'calcs' && (int)$tableRow['tree_node_id'] === $this->Table->getCycle()->getCyclesTableId() && empty($params['cycle'])) {
                        $Cycle_id = $this->Table->getCycle()->getId();
                    } elseif ($this->Table->getTableRow()['type'] === 'cycles' && (int)$tableRow['tree_node_id'] === $this->Table->getTableRow()['id'] && !empty($this->row['id'])) {
                        $Cycle_id = $this->row['id'];
                    } else {
                        $this->__checkNumericParam($params['cycle'], 'cycle');
                        $Cycle_id = $params['cycle'];
                    }
                    $Cycle = $this->Table->getTotum()->getCycle($Cycle_id, $tableRow['tree_node_id']);
                    $linkedTable = $Cycle->getTable($tableRow);
                } else {
                    throw new errorException($this->translate('The cycles table is specified incorrectly.'));
                }
                return $linkedTable;
            case 'tmp':
                $this->__checkNotEmptyParams($params, 'hash');
                $this->__checkNotArrayParams($params, 'hash');
                return $this->Table->getTotum()->getTable($tableRow, $params['hash']);
            default:
                return $this->Table->getTotum()->getTable($tableRow);
        }
    }

}
