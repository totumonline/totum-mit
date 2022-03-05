<?php

namespace totum\common\calculates;

use PDO;
use totum\common\errorException;
use totum\common\Field;
use totum\models\TablesFields;
use totum\tableTypes\calcsTable;
use totum\tableTypes\RealTables;

trait FuncTablesTrait
{

    protected function funcGetTableSource(string $params)
    {
        $params = $this->getParamsArray($params);
        return $this->Table->getSelectByParams(
            $params,
            'table',
            $this->row['id'] ?? null,
            $this::class === Calculate::class
        );
    }

    protected function funcGetTableUpdated(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['table']);
        $this->__checkNotArrayParams($params, ['table']);

        $sourceTableRow = $this->Table->getTotum()->getTableRow($params['table']);

        if (!$sourceTableRow) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $params['table']));
        }

        if ($sourceTableRow['type'] === 'calcs') {
            $this->__checkNotEmptyParams($params, ['cycle']);
            $this->__checkNotArrayParams($params, ['cycle']);

            $SourceCycle = $this->Table->getTotum()->getCycle($params['cycle'], $sourceTableRow['tree_node_id']);
            $SourceTable = $SourceCycle->getTable($sourceTableRow);
        } else {
            $SourceTable = $this->Table->getTotum()->getTable($sourceTableRow);
        }
        return json_decode($SourceTable->getSavedUpdated(), true);
    }

    protected function funcGetUsingFields(string $params): array
    {
        $params = $this->getParamsArray($params);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        if (empty($params['field']) || !is_string($params['field'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required and should be a string.', 'field'));
        }

        $query = <<<SQL
select table_name->>'v' as table_name, name->>'v' as name, version->>'v' as version from tables_fields where data->'v'->'code'->'==usedFields=='-> :table ->> :field = '1'
           OR data->'v'->'codeSelect'->'==usedFields=='-> :table ->> :field = '1'
            OR data->'v'->'codeAction'->'==usedFields=='-> :table ->> :field = '1';
SQL;


        return TablesFields::init($this->Table->getTotum()->getConfig(), true)->executePreparedSimple(
            false,
            $query,
            ['table' => $tableRow['name'], 'field' => $params['field']]
        )->fetchAll();
    }

    protected function funcLinkToDataTable($params)
    {
        $params = $this->getParamsArray($params);

        if (!is_a($this, CalculateAction::class) && empty($params['hide'])) {
            errorException::criticalException($this->translate('You cannot use linktoDataTable outside of actionCode without hide:true.'));
        }

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $tmp = $this->Table->getTotum()->getTable($tableRow);
        $tmp->addData(['tbl' => $params['data'] ?? [], 'params' => ($params['params'] ?? [])]);
        if (empty($params['hide'])) {
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


            if (!empty($params['target'])) {
                $params['target'] = $params['target'] ?? 'self';

                if ($params['target'] === 'iframe' || $params['target'] === 'top-iframe') {
                    $q_params['iframe'] = true;
                }
                $q_params['sess_hash'] = $tmp->getTableRow()['sess_hash'];

                $link = '/Table/';
                $link .= ($tableRow['top'] ?: 0) . '/' . $tableRow['id'];

                if ($q_params) {
                    $link .= '?' . http_build_query($q_params, '', '&', PHP_QUERY_RFC1738);
                }


                $this->Table->getTotum()->addToInterfaceLink(
                    $link,
                    $params['target'],
                    $params['title'] ?? $tableRow['title'],
                    null,
                    $params['width'] ?? null,
                    $params['refresh'] ?? false,
                    ['header' => $params['header'] ?? true,
                        'footer' => $params['footer'] ?? true]
                );
            } else {
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
        }
        return $tmp->getTableRow()['sess_hash'];

    }

    protected
    function funcLogRowList(string $params): array
    {
        $params = $this->getParamsArray($params);
        $where = [];

        if (!ctype_digit((string)$params['table'])) {
            $where['tableid'] = $this->__checkTableIdOrName($params['table'] ?? null, 'table')['id'];
        } else {
            $where['tableid'] = $params['table'];
        }
        if (!empty($params['cycle'])) {
            $where['cycleid'] = (int)$params['cycle'];
        }
        if (!empty($params['id'])) {
            $where['rowid'] = (int)$params['id'];
        }
        $where['field'] = (string)($params['field'] ?? '');

        $fields = ['comment' => 'modify_text', 'dt' => 'dt', 'user' => 'userid', 'action' => 'action', 'value' => 'v'];
        if (empty($params['params']) || !is_array($params['params'])) {
            $params['params'] = array_keys($fields);
        }

        $fieldsStr = '';
        foreach ($params['params'] as $param) {
            if (key_exists($param, $fields)) {
                if ($fieldsStr) {
                    $fieldsStr .= ',';
                }
                $fieldsStr .= $fields[$param] . ' as ' . $param;
            }
        }
        if ($fieldsStr) {
            $data = $this->Table->getTotum()->getModel('_log', true)->executePrepared(
                true,
                $where,
                $fieldsStr,
                'dt desc',
                key_exists('limit', $params) ? '0,' . ((int)$params['limit']) : null
            )->fetchAll(PDO::FETCH_ASSOC);

            if (in_array('dt', $params['params'])) {
                foreach ($data as &$_row) {
                    $_row['dt'] = substr($_row['dt'], 0, 19);
                }
                unset($_row);
            }
            return $data;
        }

        throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'params'));
    }

    protected
    function funcReCalculate(string $params)
    {
        $params = $this->getParamsArray($params, ['field']);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $inVars = [];
        if (key_exists('field', $params)) {
            $inVars['inAddRecalc'] = $params['field'];
        }
        if ($tableRow['type'] === 'calcs') {
            if (empty($params['cycle']) && $this->Table->getTableRow()['type'] === 'calcs' && $this->Table->getTableRow()['tree_node_id'] === $tableRow['tree_node_id']) {
                $params['cycle'] = [$this->Table->getCycle()->getId()];
            }


            if (!is_array($params['cycle'])) {
                $this->__checkNotEmptyParams($params, ['cycle']);
            }

            $Cycles = (array)$params['cycle'];
            foreach ($Cycles as $cycleId) {
                if (empty($cycleId)) {
                    continue;
                }
                $params['cycle'] = $cycleId;
                $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['tree_node_id']);
                /** @var calcsTable $table */
                $table = $Cycle->getTable($tableRow);
                $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
            }
        } elseif ($tableRow['type'] === 'tmp') {
            if ($this->Table->getTableRow()['type'] === 'tmp' && $this->Table->getTableRow()['name'] === $tableRow['name']) {
                if (empty($params['hash'])) {
                    $table = $this->Table;
                }
            }
            if (empty($table)) {
                $table = $this->Table->getTotum()->getTable($tableRow, $params['hash']);
            }
            $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
        } else {
            $table = $this->Table->getTotum()->getTable($tableRow);

            if (is_subclass_of($table, RealTables::class) && !empty($params['where'])) {
                $ids = $table->getByParams(['field' => 'id', 'where' => $params['where']], 'list');
                $inVars['modify'] = array_fill_keys($ids, []);
            }
            $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
        }
    }

    protected
    function funcSelect(string $params)
    {
        return $this->select($params, 'field');
    }

    protected
    function funcSelectList(string $params): array
    {
        return $this->select($params, 'list');
    }

    protected
    function funcSelectUnreadComments(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params, ['users']);

        if (empty($params['users'])) {
            return [];
        }
        foreach ($params['users'] as &$user) {
            $user = (int)$user;
        }
        unset($user);
        $selectParams = array_intersect_key($params, array_flip(['table', 'cycle', 'field']));

        if ($params['id'] ?? null) {
            $selectParams['where'] = [['field' => 'id', 'operator' => '=', 'value' => $params['id']]];
        }

        $val = $this->select($selectParams, 'field') ?? [];

        if (empty($val)) {
            $vals = [];
            foreach ($params['users'] as $user) {
                $vals[] = ['user' => $user, 'num' => 0, 'comments' => []];
            }
            return $vals;
        }

        $lastCommentUser = $val[array_key_last($val)][1];

        if (count($params['users']) === 1 && $lastCommentUser === $params['users'][0]) {
            return [['user' => $params['users'][0], 'num' => 0, 'comments' => []]];
        }

        $Table = $this->getSourceTable($params);
        if ($Table->getFields()[$params['field']]['type'] !== 'comments') {
            throw new errorException($this->translate('Field not of type comments'));
        }

        if (empty($params['id'])) {
            $params['id'] = null;
        } else {
            $params['id'] = (int)$params['id'];
        }

        $Comment = Field::init($Table->getFields()[$params['field']], $Table);
        $nums = $Comment->getViewedForUsers($params['users'], $params['id']);

        if (empty($nums)) {
            $userNums = [];
        } else {
            $userNums = array_combine(array_column($nums, 'user_id'), $nums);
        }

        $vals = [];
        $countVals = count($val);
        foreach ($params['users'] as $user) {
            $lastViewedUserComment = ($userNums[$user]['nums'] ?? 0) - 1;
            for ($i = $countVals - 1; $i > $lastViewedUserComment; $i--) {
                if ($val[$i][1] === $user) {
                    break;
                }
            }
            $i++;
            $comments = $val;
            array_splice($comments, 0, $i);
            $vals[] = ['user' => $user, 'num' => count($comments), 'comments' => $comments];
        }
        return $vals;
    }

    protected
    function funcSelectRow(string $params)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'field', 'sfield', 'tfield']);
        if (!empty($params['fields'])) {
            $params['field'] = array_merge($params['field'] ?? [], (array)$params['fields']);
        }
        if (!empty($params['sfields'])) {
            $params['sfield'] = array_merge($params['sfield'] ?? [], $params['sfields']);
        }
        unset($params['section']);

        $row = $this->Table->getSelectByParams(
            $params,
            'row',
            $this->row['id'] ?? null,
            get_class($this) === Calculate::class
        );
        if (!empty($row['__sectionFunction'])) {
            $row = $row['__sectionFunction']();
        }
        return $row;
    }

    protected
    function funcSelectRowList(string $params)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'field', 'sfield', 'tfield']);

        if (!empty($params['fields'])) {
            $params['field'] = array_merge($params['field'] ?? [], (array)$params['fields']);
        }
        if (!empty($params['sfields'])) {
            $params['sfield'] = array_merge($params['sfield'] ?? [], $params['sfields']);
        }
        unset($params['section']);

        return $this->Table->getSelectByParams(
            $params,
            'rows',
            $this->row['id'] ?? null,
            get_class($this) === Calculate::class
        );
    }

    protected
    function funcSelectTreeChildren(string $params)
    {
        return $this->select($this->getParamsArray($params), 'treeChildren');
    }

    protected
    function funcTableLogSelect(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['users'], 'users');
        $date_from = $this->__checkGetDate($params['from'], 'from', 'TableLogSelect');

        $date_to = $this->__checkGetDate($params['to'], 'to', 'TableLogSelect');
        $date_to->modify('+1 day');

        $date_to = $date_to->format('Y-m-d');
        $date_from = $date_from->format('Y-m-d');
        $data = [];
        if ($params['users']) {
            $slqData = $this->Table->getTotum()->getModel('_log', true)->executePrepared(
                true,
                ['userid' => $params['users'], '>=dt' => $date_from, '<dt' => $date_to],
                'tableid, cycleid,rowid,field,modify_text,v,action,userid,dt',
                'dt'
            );

            $action = [];
            $tmp_data = [];
            foreach ($slqData as $row) {
                $row['dt'] = substr($row['dt'], 0, 19);

                $tmp_action = [$row['userid'], $row['tableid'], $row['cycleid'], $row['rowid'], $row['action'], $row['dt']];
                if (array_slice($action, 0, 5) == array_slice($tmp_action, 0, 5)) {
                    $Date = date_create($action[5]);
                    $Date->modify('+1 second');
                    if ($Date->format('Y-m-d H:i:s') >= $row['dt']) {
                        $tmp_data[] = $row;
                        $action = $tmp_action;
                        continue;
                    }
                }

                if (!empty($tmp_data)) {
                    $fields = [];
                    foreach ($tmp_data as $t) {
                        if ($t['field']) {
                            $fields[$t['field']] = [$t['v'], $t['modify_text']];
                        } elseif ($t['modify_text'] && (string)$row['action'] === '4') {
                            $fields[$this->translate('Deleting')] = $t['modify_text'];
                        }
                    }

                    $data[] = ['ind' => count($data), 'userid' => $row['userid'], 'tableid' => $tmp_data[0]['tableid'], 'cycleid' => $tmp_data[0]['cycleid'], 'rowid' => $tmp_data[0]['rowid'], 'action' => $tmp_data[0]['action'], 'dt' => $tmp_data[0]['dt'], 'fields' => $fields];
                }
                $tmp_data = [$row];


                $action = $tmp_action;
            }

            if (!empty($tmp_data) && !empty($row)) {
                $fields = [];
                foreach ($tmp_data as $t) {
                    if ($t['field']) {
                        $fields[$t['field']] = [$t['v'], $t['modify_text']];
                    }
                }
                $data[] = ['userid' => $row['userid'], 'tableid' => $tmp_data[0]['tableid'], 'cycleid' => $tmp_data[0]['cycleid'], 'rowid' => $tmp_data[0]['rowid'], 'action' => $tmp_data[0]['action'], 'dt' => $tmp_data[0]['dt'], 'fields' => $fields];
            }

            if (!empty($params['order'])) {
                usort(
                    $data,
                    function ($a, $b) use ($params) {
                        foreach ($params['order'] as $o) {
                            if (!key_exists(
                                $o['field'],
                                $a
                            )) {
                                throw new errorException($this->translate('No key %s was found in the data row.',
                                    $o['field']));
                            }
                            if ($a[$o['field']] != $b[$o['field']]) {
                                $r = $a[$o['field']] < $b[$o['field']] ? -1 : 1;
                                if ($o['ad'] === 'desc') {
                                    return -$r;
                                }
                                return $r;
                            }
                        }
                        return 0;
                    }
                );
            }
        }
        return $data;
    }

    protected
    function select($params, $mode, $withOutSection = false)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'sfield']);

        if ($withOutSection) {
            unset($params['section']);
        }
        return $this->Table->getSelectByParams(
            $params,
            $mode,
            $this->row['id'] ?? null,
            $this::class === Calculate::class
        );
    }
}