<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:21
 */

namespace totum\fieldTypes;

use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\config\Conf;

class Password extends Field
{
    public function getModifiedLogValue($val)
    {
        return '---';
    }

    public function getLogValue($val, $row, $tbl = [])
    {
        return '---';
    }

    public function getFullValue($val, $rowId = null)
    {
        return '---';
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {

        if ($viewType === 'web') {
            if (is_array($valArray['v'])) {
                $valArray['e'] = $this->translate('Field data type error');
            }
        }
        if ($viewType !== 'edit') {
            $valArray['v'] = '';
        }
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if ($modifyVal === '') {
            $modifyVal = $oldVal;
        } elseif (!$isCheck) {
            $modifyVal = $this->preparePass($modifyVal);
        }

        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (is_array($val)) {
            $val = '';
        }

    }

    public function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {
        $val = parent::add($channel, $inNewVal, $row, $oldTbl, $tbl, $isCheck, $vars);
        if (!$isCheck) {
            $val['v'] = $this->preparePass($val['v']);
        }
        return $val;
    }

    protected function preparePass($modifyVal)
    {
        if (!empty($modifyVal)) {
            switch (($this->data['cryptoKey'] ?? false)) {
                case 'cryptokey':
                    return Crypt::getCrypted($modifyVal, $this->table->getTotum()->getConfig()->getCryptKeyFileContent());
                case 'argon2id':
                    return password_hash($modifyVal, PASSWORD_ARGON2ID);
            }
            return md5($modifyVal);

        }
    }

    static function checkPassword(Conf $Config, string $hash, string $pass, string|null $passType = null)
    {
        switch ($passType){
            case 'argon2id':
                return password_verify($pass, $hash);
            case 'cryptokey':
                return Crypt::getDeCrypted($hash, $Config->getCryptKeyFileContent()) === $pass;

        }
        return $hash === md5($pass);

    }
}
