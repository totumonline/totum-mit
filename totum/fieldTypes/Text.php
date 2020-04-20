<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 18:01
 */

namespace totum\fieldTypes;


use totum\common\Field;
use totum\tableTypes\aTable;

class Text extends Field
{
    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);
        if (empty($this->data['viewTextMaxLength'])) {
            $this->data['viewTextMaxLength'] = 100;
        }
    }

    function addXmlExport(\SimpleXMLElement $simpleXMLElement, $fVar)
    {
        $paramInXml = $simpleXMLElement->addChild($this->data['name'], base64_encode($fVar['v']));

        if (isset($fVar['e'])) {
            $paramInXml->addAttribute('error', $fVar['e']);
        }
        if (isset($fVar['c'])) {
            $paramInXml->addAttribute('c', $fVar['c'] != $fVar['v'] ? 'Текст изменен' : 'Текст соответствует');
            $paramInXml->addAttribute('h', isset($fVar['h']) ? '1' : '0');
        }
    }

    function getValueFromCsv($val)
    {
        return $val = base64_decode($val);
    }

    function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);

        if ($viewType === 'web' && array_key_exists('c', $valArray)) {
            $valArray['c'] = 'Текст изменен';
        }

        $valArray['v'] = $valArray['v'] ?? '';

        switch ($viewType) {
            case 'web':
                if ($this->table->getTableRow()['type'] !== 'tmp' && ($isBig = mb_strlen($valArray['v']) > $this->data['viewTextMaxLength'])) {
                    $valArray['v'] = mb_substr($valArray['v'], 0, $this->data['viewTextMaxLength']) . '...';
                }

                break;
            case 'print':

                if (($isBig = mb_strlen($valArray['v']) > $this->data['viewTextMaxLength']) && !($this->data['printTextfull']??false)) {
                    $valArray['v'] = mb_substr($valArray['v'], 0, $this->data['viewTextMaxLength']) . '...';
                }
                $valArray['v'] = htmlspecialchars($valArray['v']);
                if($this->data['textType']=='text'){
                    $valArray['v'] = nl2br($valArray['v']);
                }

                break;
            case 'csv':
                $valArray['v'] = base64_encode($valArray['v']);
                break;
        }
    }

    protected function getDefaultValue()
    {
        if ($this->data['textType'] === 'json') {
            return json_decode($this->data['default'], true) ?? $this->data['default'];
        }
        return parent::getDefaultValue();
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (!is_string($val)) {
            if ($this->data['textType'] === 'json') {
                if (!is_null($val)) {
                    $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                }
            } elseif (is_array($val)) {
                $valTmp = "";
                foreach ($val as $v) {
                    if ($valTmp != '') $valTmp .= "\n";
                    $valTmp .= $v;
                }
                $val = $valTmp;
            } else  $val = strval($val);
        }

    }
    protected function modifyValue($modifyVal, $oldVal, $isCheck)
    {
        if (is_object($modifyVal)) {
            if($modifyVal->sign =='+'){
                $modifyVal =  $oldVal.(string)$modifyVal->val;
            }
            else $modifyVal = (string)$modifyVal->val;
        }
        return $modifyVal;
    }
}