<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.07.17
 * Time: 11:51
 */

namespace totum\common\calculates;

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
            if (is_array($this->newVal['c']) && is_array($this->newVal['v'])) {
                $val = array_merge($this->newVal['v'], $this->newVal['c']);
            } elseif (is_array($this->newVal['v'])) {
                $val = array_merge($this->newVal['v'], [$this->newVal['c']]);
            } else {
                $val = array_merge($this->newVal['c'], [$this->newVal['v']]);
            }
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
        if (array_key_exists('parent', $rows[0] ?? [])) {
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
                $selectList[$row['value']] = [$row['title']];                   //0
                $selectList[$row['value']][] = !empty($row['is_del']) ? 1 : 0;  //1
            }
        }
        return $selectList;
    }
}
