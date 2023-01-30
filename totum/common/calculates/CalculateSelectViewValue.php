<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.07.17
 * Time: 11:51
 */

namespace totum\common\calculates;

use totum\common\errorException;

class CalculateSelectViewValue extends CalculateSelect
{
    protected function funcSelectListAssoc($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order'], ['section', 'preview']);


        unset($params['section']);
        unset($params['preview']);

        return parent::funcSelectListAssoc($params);
    }

    protected function funcSelectRowListForSelect($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order'], ['section', 'preview', 'previewscode']);

        unset($params['section']);
        unset($params['preview']);
        unset($params['previewscode']);


        if ($this->columnVals) {
            $val = ($this->columnVals)();
        } elseif (!empty($this->newVal['c'])) {
            $val = array_merge((array)$this->newVal['v'], (array)$this->newVal['c']);
        } else {
            $val = $this->newVal['v'];
        }

        $bField = $params['bfield'] ?? 'id';


        $params['where'][] = [
            'field' => $bField,
            'operator' => '=',
            'value' => $val
        ];


        return parent::funcSelectListAssoc($params);
    }

    protected function getPreparedList($rows)
    {
        $selectList = [];
        if ($rows && !empty($rows[0]) && is_array($rows[0]) && array_key_exists('parent', $rows[0] ?? [])) {
            foreach ($rows as $row) {
                $r = [$row['title'] //0
                    , empty($row['is_del']) ? 0 : 1 //1
                    , null //2
                    , $row['parent'] //3
                    // disabled //4
                ];
                if ($row['disabled'] ?? false) {
                    $r[] = 1;
                }
                $selectList[$row['value']] = $r;
            }
        } else {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new errorException($this->translate('Select format error in field %s', $this->varName));
                }
                if (!key_exists('value', $row) || !key_exists('title', $row)) {
                    throw new errorException($this->translate('Select format error in field %s', $this->varName));
                } elseif (is_array($row['value'])) {
                    throw new errorException($this->translate('The [[%s]] parameter must be plain row/list without nested row/list.',
                        'value'));
                } elseif (is_array($row['title'])) {
                    throw new errorException($this->translate('The [[%s]] parameter must be plain row/list without nested row/list.',
                        'title'));
                }
                $selectList[$row['value']] = [$row['title']];                   //0
                $selectList[$row['value']][] = !empty($row['is_del']) ? 1 : 0;  //1
            }
        }
        return $selectList;
    }
}
