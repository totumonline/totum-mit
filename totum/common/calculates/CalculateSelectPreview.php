<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.07.17
 * Time: 11:51
 */

namespace totum\common\calculates;

use totum\common\calculates\CalculateSelect;
use totum\tableTypes\aTable;

class CalculateSelectPreview extends CalculateSelect
{
    protected function funcSelectListAssoc($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'preview']);
        $params2 = $params;
        if (key_exists('preview', $params)) {
            $params2['sfield'] = $params['preview'];
        }

        $baseField = $params['bfield'] ?? 'id';

        $params2['where'][] = ['field' => $baseField, 'operator' => '=', 'value' => $this->newVal['v']];

        /** @var aTable $Table */
        list($rows, $Table) = $this->select($params2, 'row&table');
        $rows['previewdata'] = [];
        foreach ($params['preview'] ?? [] as $fName) {
            $rows['__fields'][$fName] = $Table->getFields()[$fName];
        }
        return $rows;
    }

    protected function funcSelectRowListForTree($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order']);
        $params2 = $params;

        $baseField = $params['bfield'] ?? 'id';

        $params2['where'][] = ['field' => $baseField, 'operator' => '=', 'value' => $this->newVal['v']];

        /** @var aTable $Table */
        list($rows, $Table) = $this->select($params2, 'row&table');

        return $rows;
    }

    protected function funcSelectRowListForSelect($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'preview']);
        $params2 = $params;
        $params2['sfield'] = $params['preview'] ?? null;

        $baseField = $params['bfield'] ?? 'id';

        $params2['where'][] = ['field' => $baseField, 'operator' => '=', 'value' => $this->newVal['v']];
        /** @var aTable $Table */
        list($rows, $Table) = $this->select($params2, 'row&table');

        $rows['previewdata'] = [];
        foreach ($params['preview'] ?? [] as $fName) {
            $rows['__fields'][$fName] = $Table->getFields()[$fName] ?? $fName;
        }
        $rows['previewscode'] = $params['previewscode'] ?? null;


        return $rows;
    }

    protected function getPreparedList($rows)
    {
        return $rows;
    }
}
