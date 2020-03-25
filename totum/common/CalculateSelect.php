<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.07.17
 * Time: 11:21
 */

namespace totum\common;


class CalculateSelect extends Calculate
{

    static $logClassName = "select";

    function exec($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table, $vars = [])
    {
        $dtStart = microtime(true);

        $this->error = null;

        $this->vars = $vars;
        $this->fixedCodeVars = [];

        $this->whileIterators = [];
        $this->setEnvironmentVars($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table);
        $this->varName = $fieldData['name'];

        $this->newLog = [];
        $this->newLogParent = &$this->newLog;

        try {

            if (empty($this->code['='])) {
                throw new errorException('Ошибка кода - нет секции [[=]]');
            }
            $r = $this->execSubCode($this->code['='], '=');
            if (!is_array($r)) {
                throw new errorException('Код должен возвращать rowList или то, что возвращает функция SelectListAssoc');
            }

            $r = $this->getPreparedList($r);

        } catch (SqlExeption $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . 'ОШБК!';
            $this->newLog['children'][] = ['type' => 'error', 'text' => 'Ошибка базы данных при обработке кода [[' . $e->getMessage() . ']]'];
            $this->error = 'Ошибка базы данных при обработке кода [[' . $e->getMessage() . ']]';

        } catch (errorException $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . 'ОШБК!';
            $this->newLog['children'][] = ['type' => 'error', 'text' => $e->getMessage()];
            $this->error = $e->getMessage();
        }

        if ($this->error) {
            $this->error .= ' (поле [[' . $this->varName . ']] таблицы [[' . $this->aTable->getTableRow()['name'] . ']])';
        }

        static::$calcLog[$var]['time'] = (static::$calcLog[$var = $table->getTableRow()["id"] . '/' . $fieldData['name'] . '/' . static::$logClassName]['time'] ?? 0) + (microtime(true) - $dtStart);
        static::$calcLog[$var]['cnt'] = (static::$calcLog[$var]['cnt'] ?? 0) + 1;


        return $r ?? $this->error;
    }

    protected
    function funcSelectListAssoc($params)
    {

        $params = $this->getParamsArray($params, ['where', 'order']);

        $params2 = $params;

        $baseField = $params['bfield'] ?? 'id';

        $params2['field'] = [$params['field'], $baseField, 'is_del'];
        $params2['sfield'] = [];
        if (!empty($params['section'])) {
            // $params2['field'][] = $params['section'];
            $params2['sfield'][] = $params2['section'];
            unset($params2['section']);
            $params2['with__sectionFunction'] = true;
        }
        if (!empty($params['parent'])) {
            $params2['field'][] = $params['parent'];
        }

        $disabled = array_flip(array_unique($params['disabled'] ?? []));

        $rows = $this->select($params2, 'rows');

        $rows = array_map(function ($row) use ($params, $disabled, $baseField) {
            $r = ['value' => $row[$baseField]
                , 'is_del' => $row['is_del']
                , 'title' => $row[$params['field']]];
            if (array_key_exists('parent', $params)) {
                $r['parent'] = $row[$params['parent']];
            }
            if (array_key_exists($row[$baseField], $disabled)) {
                $r['disabled'] = true;
            }
            if (!empty($params['section'])) {
                $r['section'] = $row['__sectionFunction'] ?? $row[$params['section']];
            }
            return $r;
        },
            $rows);

        if (!empty($params['preview'])) {
            $rows['previewdata'] = true;
        };
        return $rows;
    }

    protected
    function funcSelectRowListForTree($params)
    {

        $params = $this->getParamsArray($params, ['where', 'order']);

        $params2 = $params;

        $params['bfield']=$params['bfield']??'id';

        $params2['field'] = [$params['field'], $params['bfield'], 'is_del'];
        $params2['sfield'] = [];

        if (empty($params['parent'])) throw new errorException('Параметр parent должен быть заполнен');
        $params2['field'][] = $params['parent'];

        $disabled = array_flip(array_unique($params['disabled'] ?? []));

        $rows = $this->select($params2, 'rows');

        $thisField = $this->aTable->getFields()[$this->varName];

        $ParentField = null;
        $treeListPrep = '';
        $treeRows = [];

        /* Дополненное дерево - ид папок из другой таблицы */
        if (empty($thisField['treeAutoTree'])) {
            $sourceTable = $this->aTable->getSelectTableByParams($params);
            $ParentField = Field::init($sourceTable->getFields()[$params['parent']], $sourceTable);

            if ($ParentField->getData('codeSelectIndividual')) {
                throw new errorException('Параметр [[' . $params['parent'] . ']] не должен быть индивидуально рассчитываемым селектом/деревом');
                /* $v = ['v' => $row[$params['parent']]];
                 $parentList=$ParentField->calculateSelectList($v, $row, $sourceTable);
                 unset($parentList['previewdata']);

                 $treeListPrep=$row['id'].'/'.$treeListPrep;

                 foreach ($parentList as $val=>$_r) {
                     if($ParentField->getData('type')=='tree'){
                         $treeRows[] = ['value' => $treeListPrep . $val, 'title'=>$_r[0], 'is_del'=>$_r['1'], 'parent'=>$_r[3]?$treeListPrep.$_r[3]:null];
                     }else{
                         $treeRows[] = ['value' => $treeListPrep . $val, 'title'=>$_r[0], 'is_del'=>$_r['1'], 'parent'=>null];
                     }

                 }*/
            } else {
                $treeListPrep = 'PP/';

                $v = ['v' => $rows[0][$params['parent']] ?? null, '__isForChildTree' => true];
                $parentList = $ParentField->calculateSelectList($v, $rows[0], $sourceTable);
                unset($parentList['previewdata']);

                if ($ParentField->getData('type') == 'tree') {
                    foreach ($parentList as $val => $_r) {
                        $treeRows[] = ['value' => $treeListPrep . $val, 'title' => $_r[0], 'is_del' => $_r['1'], 'parent' => $_r[3] ? $treeListPrep . $_r[3] : null];
                    }
                } else {
                    foreach ($parentList as $val => $_r) {
                        $treeRows[] = ['value' => $treeListPrep . $val, 'title' => $_r[0], 'is_del' => $_r['1'], 'parent' => null];
                    }
                }
            }
        }

        foreach ($rows as $row) {
            $r = ['value' => $row[$params['bfield']]
                , 'is_del' => $row['is_del']
                , 'title' => $row[$params['field']]];

            $r['parent'] = ($row[$params['parent']] ?? null) ? $treeListPrep . $row[$params['parent']] : null;

            if (key_exists($row[$params['bfield']], $disabled)) {
                $r['disabled'] = true;
            }
            $treeRows[] = $r;
        }


        return $treeRows;
    }

    protected
    function funcSelectRowListForSelect($params)
    {

        $params = $this->getParamsArray($params, ['where', 'order']);

        $params2 = $params;

        $baseField = $params['bfield'] ?? 'id';

        $params2['field'] = [$params['field'], $baseField, 'is_del'];
        $params2['sfield'] = [];
        if (!empty($params['section'])) {
            // $params2['field'][] = $params['section'];
            $params2['sfield'][] = $params2['section'];
            unset($params2['section']);
            $params2['with__sectionFunction'] = true;
        }

        $rows = $this->select($params2, 'rows');

        $rows = array_map(function ($row) use ($params, $baseField) {
            $r = ['value' => $row[$baseField]
                , 'is_del' => $row['is_del']
                , 'title' => $row[$params['field']]];

            if (!empty($params['section'])) {
                $r['section'] = $row['__sectionFunction'] ?? $row[$params['section']];
            }
            return $r;
        },
            $rows);

        if (!empty($params['preview'])) {
            $rows['previewdata'] = true;
        };
        return $rows;
    }

    protected function getPreparedList($rows)
    {

        $selectList = [];
        if ($this->varData['type'] === 'tree') {
            foreach ($rows as $row) {

                $r = [$row['title'] //0
                    , empty($row['is_del']) ? 0 : 1 //1
                    , null //2
                    , $row['parent'] //3
                    // disabled //4
                ];
                if ($row['disabled'] ?? false) $r[] = 1;

                $selectList[$row['value']] = $r;
            }

        } else {

            $selectList['previewdata'] = $rows['previewdata'] ?? false;
            unset($rows['previewdata']);

            foreach ($rows as $row) {
                $selectList[$row['value']] = [$row['title']];                   //0
                $selectList[$row['value']][] = !empty($row['is_del']) ? 1 : 0;  //1
                $selectList[$row['value']][] = $row['section'] ?? null;         //2
                $selectList[$row['value']][] = $row['preview'] ?? null;         //3
            }
        }
        return $selectList;
    }

}