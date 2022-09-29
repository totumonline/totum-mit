<?php


namespace totum\common\logs;

use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\Lang\LangInterface;
use totum\tableTypes\aTable;
use totum\tableTypes\tmpTable;

class CalculateLog
{
    /**
     * @var null
     */
    protected $params;
    /**
     * @var string
     */
    protected $children = [];
    protected $parent;
    /**
     * @var float|string
     */
    protected $startMicrotime;
    /**
     * @var int
     */
    protected $level;

    protected $tableName;
    protected $fieldName;
    protected $section;

    protected $types = [];

    protected $topParent;
    /**
     * @var mixed|null
     */
    protected $tableId;

    public function __construct($params = ["Totum"], $parent = null, $tableName = null, $tableId = null, $fieldName = null, $section = null)
    {
        $this->startMicrotime = microtime(true);

        $this->params = $params;

        if (key_exists('code', $this->params) && is_callable($this->params['code'])) {
            $this->params['code'] = $this->params['code']();
        }
        if (!empty($params['calc']) && $params['calc'] === CalculateAction::class) {
            $this->params['cType'] = 'a';
        }

        if ($this->parent = $parent) {
            $this->level = $parent->getLevel() + 1;
            $this->topParent = $this->parent->getTopParent();
        } else {
            $this->level = 0;
            $this->topParent = $this;
        }
        $this->tableName = $tableName;
        $this->tableId = $tableId;
        $this->fieldName = $fieldName;
        $this->section = $section;

        /*echo str_repeat(' ', $this->level) . $this->tableName . '/' . $this->fieldName . ' ('.spl_object_id($this).'): ' . json_encode(
                $this->params,
                JSON_UNESCAPED_UNICODE
            ) . "\n\n";*/
    }

    public function setLogTypes($types)
    {
        $this->types = $types;
    }

    public function getTopParent()
    {
        return $this->topParent;
    }

    public function getChildInstance($params)
    {
        $tableName = $this->tableName;
        $tableId = $this->tableId;
        $fieldName = $this->fieldName;
        $section = $this->section;


        if (key_exists('table', $params) && is_object($params['table']) && is_a($params['table'], aTable::class)) {
            $Table = $params['table'];
            $params['table'] = $Table->getTableRow()['name'];
            if ($Table->getCycle()) {
                $params['cycle'] = $Table->getCycle()->getId();
            } elseif (is_a($Table, tmpTable::class)) {
                $params['hash'] = $Table->getTableRow()['sess_hash'];
            }
            $tableId = $Table->getTableRow()['id'];
        }

        if ($params['table'] ?? null) {
            $tableName = $params['table'];
            $fieldName = null;
        }
        if ($params['field'] ?? null) {
            $fieldName = $params['field'];
        }
        switch ($params['name'] ?? '') {
            case 'RECALC':
                $section = 'code';
                break;
            case 'ACTIONS':
                $section = 'actions';
                break;
            case 'SELECTS AND FORMATS':
            case 'TABLE FORMAT':
            case 'SELECTS AND FORMATS ROWS':
            case 'SELECTS AND FORMATS OF OTHER NON-ROWS PARTS':
                $section = 'views';
                break;
        }

        $logTypes = $this->topParent->getTypes();
        if (!$logTypes) {
            $log = new CalculateLogEmpty([], $this);
        } elseif ($logTypes !== ['all']) {
            switch ($section) {
                case 'code':
                    if (($params['field'] ?? null) && !array_intersect(['c', 'flds'], $logTypes)) {
                        $log = new CalculateLogEmpty([], $this);
                        break;
                    }
                // no break!
                case 'actions':
                    if (!array_intersect(['a', 'c', 'recalcs', 'flds'], $logTypes)) {
                        $log = new CalculateLogEmpty([], $this);
                    }
                    break;
                case 'views':
                    if (!array_intersect(['s', 'f', 'flds'], $logTypes)) {
                        $log = new CalculateLogEmpty([], $this);
                    } elseif ($params['cType'] ?? '') {
                        switch ($params['cType'] ?? '') {
                            case 'format':
                                if (!array_intersect(['f', 'flds'], $logTypes)) {
                                    $log = new CalculateLogEmpty([], $this);
                                }
                                break;
                            default:
                                if (!array_intersect(['s', 'flds'], $logTypes)) {
                                    $log = new CalculateLogEmpty([], $this);
                                }
                                break;
                        }
                    }
                    break;
            }
        }
        /*
         * ?
         * if ($logTypes === ['flds'] && $this->fieldName && $this->section !== 'actions') {
            $log = new CalculateLogEmpty([], $this);
        }*/
        if (empty($log)) {
            $log = new static($params, $this, $tableName, $tableId, $fieldName, $section);
        }


        $this->children[] = $log;
        return $log;
    }

    public function addParam($key, $value)
    {
        if ($key === 'error' || $key === 'result') {
            $times = round(microtime(true) - $this->startMicrotime, 6);
            $this->params['times'] = $times;

            // echo str_repeat(' ', $this->level) . '('.spl_object_id($this).") $key: ".substr($v=json_encode($value, JSON_UNESCAPED_UNICODE), 0, 30).(strlen($v) > 30 ? '...' : '')."\n\n";
        }
        $this->params[$key] = $value;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function getLodTree()
    {
        $tree = $this->params;
        foreach ($this->children as $child) {
            $tree['children'][] = $child->getLodTree();
        }
        return $tree;
    }

    public function __toString()
    {
        return json_encode($this->getLodTree(), JSON_UNESCAPED_UNICODE);
    }

    public function getFieldLogs($level = 0)
    {
        $fields = [];
        if (key_exists('field', $this->params) && key_exists('cType', $this->params)) {
            $var = $this->tableId . '/' . $this->params['field'] . '/' . ($this->params['cType']);
            if (!key_exists($var, $fields)) {
                $fields[$var] = ['cnt' => 0];
            }
            $fields[$var]['cnt']++;
            $fields[$var]['time'] = $fields[$var]['time'] ?? (0 + $this->params['times']);
        }

        foreach ($this->children as $child) {
            foreach ($child->getFieldLogs($level + 1) as $f => $vals) {
                if (key_exists($f, $fields)) {
                    $fields[$f]['cnt'] += $vals['cnt'];
                    $fields[$f]['time'] += $vals['time'];
                } else {
                    $fields[$f]['cnt'] = $vals['cnt'];
                    $fields[$f]['time'] = $vals['time'];
                }
            }
        }

        if (!$level) {
            foreach ($fields as &$f) {
                $f['time'] = round($f['time'], 5);
            }
            unset($f);
        }

        return $fields;
    }

    public function getLogsForJsTree(LangInterface $Lang)
    {
        $tree = [];

        $tree['icon'] = 'fa fa-folder';

        if ($this->fieldName) {
            $tree['icon'] = 'fa fa-magic';
        }

        if (($this->params['type'] ?? false) === 'fixed') {
            $tree['icon'] = 'fa fa-hand-grab-o';
        }

        $tree['text'] = '';
        $tree['children'] = [];

        if ($this->params['recalculate'] ?? null) {
            switch ($this->params['recalculate']) {
                case 'param':
                    $tree['text'] .= $Lang->translate('Header');
                    if (!$this->children) {
                        return null;
                    }
                    break;
                case 'footer':
                    $tree['text'] .= $Lang->translate('Footer');
                    if (!$this->children) {
                        return null;
                    }
                    break;
                case 'column':
                    $tree['text'] .= $Lang->translate('Rows part');
                    if (!$this->children) {
                        return null;
                    }
                    break;
                case 'filter':
                    $tree['text'] .= $Lang->translate('Filters');
                    if (!$this->children) {
                        return null;
                    }
                    break;
            }
        }


        $tree = $this->formatLogItem($tree, $this->topParent->getTypes() === ['flds']);


        $ids = [];
        /** @var CalculateLog $child */
        foreach ($this->children as $child) {
            /*if (empty($child->getParams())) {
                continue;
            }*/

            if ($data = $child->getLogsForJsTree($Lang)) {
                if (($child->getParams()['name'] ?? null) === '=') {
                    array_push($tree['children'], ...$data['children']);
                } else {
                    $ids[$child->getParams()['itemId'] ?? ''][] = $data;
                }
            }
        }


        if ($ids) {
            if (count($ids) > 1 || !key_exists('', $ids)) {
                foreach ($ids as $id => $row) {
                    if ($id !== '') {
                        $tree['children'][] = ['text' => $Lang->translate('Row: id %s',
                            (string)$id), 'children' => $row, 'icon' => 'fa fa-folder'];
                    } else {
                        array_push($tree['children'], ...$row);
                    }
                }
            } else {
                array_push($tree['children'], ...$ids['']);
            }
        }

        if ($tree['text'] == '' && $tree['children'] === []) {
            return null;
        }

        return $tree;
    }

    public function getLogsByElements($tableId, $field = null)
    {
        $fields = [];
        if (!$field) {
            if (($this->params['field'] ?? false) && $this->tableId === $tableId && ($cType = $this->params['cType'] ?? false)) {
                $data = [];
                foreach ($this->children as $child) {
                    $data[] = $child->getLogsByElements($tableId, $this->params['field']);
                }

                $cTypeLetter = $cType[0];
                if ($cTypeLetter === 'v') {
                    $cTypeLetter = 's';
                }

                if ($this->params['itemId']) {
                    $fields[$this->params['itemId']][$this->params['field']][$cTypeLetter] = $data;
                } else {
                    $fields[$this->params['field']][$cTypeLetter] = $data;
                }
            } else {
                foreach ($this->children as $child) {
                    foreach ($child->getLogsByElements($tableId) as $k => $v) {
                        if (ctype_digit($k)) {
                            foreach ($v as $f => $_v) {
                                $fields[$k][$f] = array_merge($fields[$k][$f] ?? [], $_v);
                            }
                        } else {
                            $fields[$k] = array_merge($fields[$k] ?? [], $v);
                        }
                    }
                }

            }
        } else {
            $data = $this->formatLogItem();
            foreach ($this->children as $child) {
                $_log = $child->getLogsByElements($tableId, $field);
                if ($_log) {
                    $data['children'][] = $_log;
                }
            }
            return $data;
        }

        return $fields;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return int
     */
    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->tableName;
    }

    /**
     * @return mixed|string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return string
     */
    public function getSection()
    {
        return $this->section;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * @param array $tree
     * @param $withTimes
     * @return array
     */
    protected function formatLogItem(array $tree = [], $withTimes = false): array
    {
        $names = ['name', 'table', 'field'];
        foreach ($names as $name) {
            if (key_exists($name, $this->params)) {
                $tree['text'] = ($tree['text'] ?? '') . $this->params[$name];
                switch ($name) {
                    case 'name':
                        if ($this->params['name'] === 'RECALC' || $this->params['name'] === 'ACTIONS') {
                            $tree['text'] .= " \"$this->tableName\"";
                        }
                        if ($this->params['cycle'] ?? false) {
                            $tree['text'] .= " / cycle " . $this->params['cycle'];
                        }
                        break;
                    case 'table':
                        $tree['type'] = 'table_simple';
                        break;
                    case 'field':
                        switch (($this->params['cType'] ?? null)) {
                            case 'format':
                                $tree['text'] = 'F ' . $tree['text'];
                                break;
                            case 'view':
                                $tree['text'] = 'S ' . $tree['text'];
                                break;
                        }

                        $tree['icon'] = 'fa fa-folder';

                        break;

                }
                break;
            }
        }
        if (($this->params['action'] ?? null) === 'select') {
            if ($this->params['cached']) {
                $tree['icon'] = 'fa fa-hand-rock-o';
            } else {
                $tree['icon'] = 'fa fa-database';
            }

            $tree['text'] = 'select from table "' . $tree['text'] . '"';
        }

        $tree['text'] = $tree['text'] ?? '';

        //  $tree['children'] [] = ['text' => 'field:' . $fieldName . '; table:' . $tableName.'; section: '.$section];


        if ($withTimes) {
            if (!empty($this->params['field']) && !empty($this->params['times']) && $this->params['times'] > 0.0001) {
                $tree['text'] .= ' ' . $this->params['times'] . ' sec.';
            }
        } elseif (key_exists('result', $this->params)) {

            if (is_array($this->params['result']) && count($this->params['result']) > 3) {
                $tree['children'][] = ['icon' => 'fa fa-code', 'text' => json_encode(
                    $this->params['result'],
                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                )];
                $tree['text'] .= ': list/row of ' . count($this->params['result']) . ' values';
            } elseif (in_array($this->params['result'], ['done'], true)) {
                if ($this->params['times'] > 0.001) {
                    $tree['text'] .= ': ' . round($this->params['times'], 3) . ' c.';
                }
            } elseif (in_array($this->params['result'], ['changed', 'no changed'], true)) {
                $tree['text'] .= ': ' . $this->params['result'];
                if ($this->params['times'] > 0.001) {
                    $tree['text'] .= ': ' . round($this->params['times'], 3) . ' c.';
                }
            } else {
                $tree['text'] .= ': ' . json_encode($this->params['result'], JSON_UNESCAPED_UNICODE);
            }

            if (($this->params['type'] ?? false) === 'fixed') {
                $tree['icon'] = 'fa fa-hand-grab-o';
            }
        }

        if ($this->params['error'] ?? null) {
            $tree['children'][] = ['text' => $this->params['error'], 'type' => 'error'];
        }

        if ($this->params['inVars'] ?? null) {
            $tree['children'][] = ['text' => json_encode($this->params['inVars'], true), 'icon' => 'fa fa-code'];
        }


        if ($this->params['code'] ?? null) {
            $tree['children'][] = ['text' => $this->params['code'], 'icon' => 'fa fa-cogs'];
        }

        foreach ($this->params as $name => $val) {
            if (preg_match('/^$|#|json|math|str|cond/', $name)) {
                $tree['children'][] = ['text' => $name . " = " . json_encode(
                        $val,
                        JSON_UNESCAPED_UNICODE
                    ), 'icon' => 'fa fa-hash'];
            }
        }

        return $tree;
    }
}
