<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 14.03.17
 * Time: 14:24
 */

namespace totum\common;

use JetBrains\PhpStorm\ExpectedValues;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\Lang\RU;
use totum\fieldTypes\Checkbox;
use totum\fieldTypes\Comments;
use totum\fieldTypes\Date;
use totum\fieldTypes\FieldParams;
use totum\fieldTypes\fieldParamsResult;
use totum\fieldTypes\File;
use totum\fieldTypes\ListRow;
use totum\fieldTypes\Number;
use totum\fieldTypes\Password;
use totum\fieldTypes\Select;
use totum\fieldTypes\StringF;
use totum\fieldTypes\TableNameField;
use totum\fieldTypes\Text;
use totum\fieldTypes\Tree;
use totum\fieldTypes\Unic;
use totum\fieldTypes\Chart;
use totum\models\Table;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;

class Field
{
    public const CHANGED_FLAGS = [
        'changed' => 1,
        'setToDefault' => 2,
        'setToPinned' => 3,
        'inAddRecalc' => 4,
    ];
    protected const NO_ERROR_IN_VALUE = ['checkbox'];

    public static array $fields = [];
    protected $data;
    protected $table;
    protected $CalculateCode;
    protected $CalculateCodeSelect;
    protected $CalculateCodeSelectValue;
    protected bool|null|CalculcateFormat $CalculateFormat = null;
    protected $log;
    /**
     * @var string
     */
    protected $calcInit;

    /**
     * Является ли значение поля листом
     *
     * @param string $type
     * @param $isMulty
     * @return bool
     */
    public static function isFieldListValues(string $type, $isMulty): bool
    {
        return match ($type) {
            'select', 'tree' => !!$isMulty,
            'listRow', 'fieldParams', 'fieldParamsResult', 'file' => true,
            default => false,
        };
    }

    public function getName()
    {
        return $this->data['name'];
    }

    public function getData($param = null)
    {
        if ($param) {
            return $this->data[$param] ?? null;
        }
        return $this->data;
    }

    protected function __construct($fieldData, aTable $table)
    {
        $this->data = $fieldData;
        $this->table = $table;

        if (!empty($fieldData['linkTableName'])) {
            if (empty($fieldData['linkFieldError'])) {
                $params = 'table: \'' . $fieldData['linkTableName'] . '\'; field: \'' . $fieldData['linkFieldName'] . '\'; ';
                if ($this->table->getTableRow()['type'] === 'cycles') {
                    $tableWhere = $this->table->getTotum()->getTableRow($fieldData['linkTableName']);
                    if ($tableWhere['type'] === 'calcs') {
                        $params .= 'cycle: #id';
                    }
                    if ($tableWhere['name'] === 'cycles_access') {
                        $params .= 'where: \'cycle__id\'=#id; where: \'cycles_table_id\'="' . $this->table->getTableRow()['id'] . '"';
                    }
                }

                $this->data['code'] = '=:select(' . $params . ')';
            } else {
                $this->data['code'] = '=:errorExeption(text: "' . $this->translate('The anchor field settings are incorrect.') . '")';
            }
            $this->data['codeOnlyInAdd'] = false;
        }

        if (array_key_exists('code', $this->data)) {
            $this->CalculateCode = new Calculate($this->data['code']);
        }

        if (empty($this->data['errorText'])) {
            $this->data['errorText'] = $this->translate('ERR!');
        }
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->table->getTotum()->getLangObj()->translate($str, $vars);
    }

    /**
     * @param $fieldData
     * @param aTable $table
     * @return Field
     */
    public static function init($fieldData, aTable $table)
    {
        $staticName = $table->getTableRow()['id'] . '/' . $fieldData['name'];
        if ($table->getTableRow()['type'] === 'calcs') {
            $staticName .= '/' . $table->getCycle()->getId();
        }
        return $table->getTotum()->fieldObjectsCaches(
            $staticName,
            (function () use ($fieldData, $table) {
                switch ($fieldData['type']) {
                    case 'select':
                        $model = Select::class;
                        break;
                    case 'tree':
                        $model = Tree::class;
                        break;
                    case 'date':
                        $model = Date::class;
                        break;
                    case 'number':
                        $model = Number::class;
                        break;
                    case 'password':
                        $model = Password::class;
                        break;
                    case 'string':
                        $model = StringF::class;
                        break;
                    case 'checkbox':
                        $model = Checkbox::class;
                        break;
                    case 'text':
                        $model = Text::class;
                        break;
                    case 'fieldParams':
                        $model = fieldParams::class;
                        break;
                    case 'fieldParamsResult':
                        $model = fieldParamsResult::class;
                        break;
                    case 'file':
                        $model = File::class;
                        break;
                    case 'listRow':
                        $model = ListRow::class;
                        break;
                    case 'comments':
                        $model = Comments::class;
                        break;
                    case 'button':
                        $model = Field::class;
                        break;
                    case 'unic':
                        if ($table->getTableRow()['name'] === 'tables' && $fieldData['name'] === 'name') {
                            $model = TableNameField::class;
                        } else {
                            $model = Unic::class;
                        }
                        break;
                    case 'chart':
                        $model = Chart::class;
                        break;
                    default:
                        $model = Field::class;
                        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                        throw new errorException($this->translate('Field type is not defined.'));
                }
                return new $model($fieldData, $table);
            })
        );
    }

    public function getValueFromCsv($val)
    {
        return $val;
    }


    protected function checkFormatObject()
    {
        if (is_null($this->CalculateFormat)) {
            if (!empty($this->data['format']) && $this->data['format'] !== 'f1=:') {
                $this->CalculateFormat = new CalculcateFormat($this->data['format']);
            } else {
                $this->CalculateFormat = false;
            }
        }

        return !!$this->CalculateFormat;
    }

    public function addFormat(&$valArray, $row, $tbl, $pageIds)
    {
        if ($this->checkFormatObject()) {
            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'format', 'field' => $this->data['name']]);

            if ($format = $this->CalculateFormat->getFormat($this->data['name'],
                $row,
                $tbl,
                $this->table,
                ['rows' => $this->table->getRowsForFormat($pageIds)])) {
                $valArray['f'] = $format;
            }
            $this->table->calcLog($Log, 'result', $format);
        }
    }

    public function getPanelFormat($row, $tbl)
    {
        if ($this->checkFormatObject()) {
            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'format', 'field' => $this->data['name']]);
            $result = $this->CalculateFormat->getPanelFormat($this->data['name'], $row, $tbl, $this->table);
            $this->table->calcLog($Log, 'result', $result);
        } else {
            $result = null;
        }
        return $result;
    }

    public function __destruct()
    {
    }

    public function getModifyFlag($newValExists, $newVal, $oldVal, $isInSetTodefaults, $isInSetToPinned, $modifyCalculated)
    {
        if (in_array($this->data['name'], $this->table->getInAddRecalc()) && !empty($this->data['codeOnlyInAdd'])) {
            return Field::CHANGED_FLAGS['inAddRecalc'];
        } elseif ($isInSetToPinned) {
            return Field::CHANGED_FLAGS['setToPinned'];
        } elseif ($isInSetTodefaults) {
            return Field::CHANGED_FLAGS['setToDefault'];
        } elseif (isset($newVal) || $newValExists) {
            if ($modifyCalculated !== true) {
                if ($oldVal && (Calculate::compare('==', $oldVal['v'], $newVal, $this->table->getLangObj()))) {
                    return false;
                }
                switch ($modifyCalculated) {
                    case 'handled':
                        if (isset($this->data['code']) && empty($this->data['codeOnlyInAdd']) && empty($oldVal['h'])) {
                            return false;
                        }
                        break;
                }
            }
            return Field::CHANGED_FLAGS['changed'];
        }
        return false;
    }

    public function action($oldRow, $newRow, $oldTbl, $newTbl, string $type, $vars = [])
    {
        if (!empty($this->data['codeAction'])) {
            $CalculateCodeAction = new CalculateAction($this->data['codeAction']);
            try {
                $res = $CalculateCodeAction->execAction(
                    $this->data['name'],
                    $oldRow,
                    $newRow,
                    $oldTbl,
                    $newTbl,
                    $this->table,
                    $type,
                    $vars
                );
            } catch (\Exception $e) {
                $row = $oldRow ?? $newRow ?? [];
                if (method_exists($e, 'addPath')) {
                    $e->addPath('action ' . $this->translate('field [[%s]] of [[%s]] table',
                            [$this->data['name'], $this->table->getTableRow()['name']]) . (!empty($row['id']) ? ' id ' . $row['id'] : ''));
                }
                throw $e;
            }

        }
    }

    public function getModifiedLogValue($val)
    {
        return $val;
    }

    public function isLogging()
    {
        if (array_key_exists('logging', $this->data) && $this->data['logging'] === false) {
            return false;
        }
        return true;
    }

    #[ExpectedValues(values: ['web', 'xml', 'inner'])]
    public function isChannelChangeable($action, $channel)
    {
        switch ($channel) {
            case 'web':
                return $this->isWebChangeable($action);
            case 'xml':
                return $this->isXmlChangeable($action);
            case 'inner':
                return true;
            default:
                throw new errorException($this->translate('Unsupported channel [[%s]] is specified.', $action));
        }
    }

    /**
     * @param $action 'insert|modify'
     */
    public function isWebChangeable($action)
    {
        if ($this->data['category'] === 'filter') {
            $action = 'modify'; //Проверять по параметрам изменения - хак для фильтров
        }

        switch ($action) {
            case 'insert':
                if ($insertable = !empty($this->data['insertable'])) {
                    if (!$this->table->getUser()->isCreator() && !empty($this->data['webRoles'])) {
                        if (count(array_intersect(
                                $this->data['webRoles'],
                                $this->table->getUser()->getRoles()
                            )) === 0) {
                            $insertable = false;
                        }
                    }
                    if ($insertable && !empty($this->data['addRoles']) && count(array_intersect(
                            $this->data['addRoles'],
                            $this->table->getUser()->getRoles()
                        )) === 0) {
                        $insertable = false;
                    }
                }
                return $insertable;
                break;
            case 'modify':
                if ($editable = !empty($this->data['editable'])) {

                    //Для фильтров не применять webRoles
                    if (!$this->table->getUser()->isCreator() && $this->data['category'] !== 'filter' && !empty($this->data['webRoles'])) {
                        if (count(array_intersect(
                                $this->data['webRoles'],
                                $this->table->getUser()->getRoles()
                            )) === 0) {
                            $editable = false;
                        }
                    }
                    if ($editable && !empty($this->data['editRoles']) && count(array_intersect(
                            $this->data['editRoles'],
                            $this->table->getUser()->getRoles()
                        )) === 0) {
                        $editable = false;
                    }
                }

                return $editable;
                break;
        }
    }

    /**
     * @param $action 'insert|modify'
     */
    public function isXmlChangeable($action)
    {
        if ($this->data['category'] === 'filter') {
            $action = 'modify'; //Проверять по параметрам изменения - хак для фильтров
        }

        switch ($action) {
            case 'insert':
                if ($insertable = !empty($this->data['apiInsertable'])) {
                    if (!empty($this->data['xmlRoles'])) {
                        if (count(array_intersect(
                                $this->data['xmlRoles'],
                                $this->table->getUser()->getRoles()
                            )) === 0) {
                            $insertable = false;
                        }
                    }
                }
                return $insertable;
                break;
            case 'modify':
                if ($editable = !empty($this->data['apiEditable'])) {
                    if (!$this->table->getTotum()->getUser()->isCreator() && !empty($this->data['xmlRoles'])) {
                        if (count(array_intersect(
                                $this->data['xmlRoles'],
                                $this->table->getUser()->getRoles()
                            )) === 0) {
                            $editable = false;
                        }
                        if ($editable && !empty($this->data['xmlEditRoles']) && count(array_intersect(
                                $this->data['xmlEditRoles'],
                                $this->table->getUser()->getRoles()
                            )) === 0) {
                            $editable = false;
                        }
                    }
                }
                return $editable;
                break;
        }
    }

    public function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {
        if ($channel === 'webInsertRow') {
            $channel = 'inner';
        }

        $insertable = match ($channel) {
            'inner' => true,
            'web' => $this->isWebChangeable('insert'),
            'xml' => $this->isXmlChangeable('insert'),
            default => throw new errorException($this->translate('Unsupported channel [[%s]] is specified.', $channel)),
        };

        $newVal = ['v' => null];


        if (empty($this->data['code']) && (!$insertable || ($inNewVal ?? '') === '' || $inNewVal === [] || $inNewVal === $this->data['errorText'])) {
            if ($this->data['category'] === 'filter') {
                if (is_null($inNewVal)) {
                    $inNewVal = $this->getDefaultValue();
                }
                $newVal = ['v' => $inNewVal];
            } elseif ($inNewVal !== [] && array_key_exists('default', $this->data) && $this->data['default'] !== '') {
                $inNewVal = $this->getDefaultValue();
                $newVal = ['v' => $inNewVal];
            } elseif ($insertable && !empty($this->data['required']) && !$isCheck) {
                throw new errorException($this->translate('Field [[%s]] of table [[%s]] is required.',
                    [$this->data['title'], $this->table->getTableRow()['title']]));
            }
        }


        if ($insertable) {
            $newVal = ['v' => $inNewVal];
        }

        //Что бы это значило?
        if ($this->data['category'] === 'filter' && $newVal['v'] === '') {
            $newVal['h'] = true;
        } elseif ($newVal['v'] !== '' && !is_null($newVal['v'])) {
            $newVal['h'] = true;
        }

        //Чтобы не рассчитывать, если значение уже задано для codeOnlyInAdd
        if (empty($newVal['h']) || empty($this->data['codeOnlyInAdd'])) {
            $this->calculate($newVal, [], $row, $oldTbl, $tbl, $vars, "add");
        }


        if (!$isCheck && !empty($this->data['codeOnlyInAdd'])) {
            unset($newVal['c']);
        }

        try {
            $this->checkVal($newVal, $row, $isCheck);
        } catch (errorException $e) {
            $e->addPath($this->translate('field [[%s]] of [[%s]] table',
                [$this->data['name'], $this->table->getTableRow()['name']]));

            if (!$isCheck) {
                throw $e;
            }
        } catch (\Exception $e) {
            if (method_exists($e, 'addPath')) {
                $e->addPath($this->translate('field [[%s]] of [[%s]] table',
                    [$this->data['name'], $this->table->getTableRow()['name']]));
            }
            throw $e;
        }

        if (!empty($newVal['h'])) {
            if (is_null($newVal['v']) || !array_key_exists('c', $newVal) || $newVal['c'] === $newVal['v']) {
                unset($newVal['h']);
            }
        }

        if (key_exists('c', $newVal)) {
            if (empty($newVal['h']) || $newVal['c'] === $newVal['v'] || (is_numeric($newVal['c']) && is_numeric($newVal['v']) && $newVal['c'] == $newVal['v'])) {
                unset($newVal['c']);
            }
        }

        return $newVal;
    }

    public function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {
        $oldVal = $oldRow[$this->data['name']] ?? null;

        if ($changeFlag === Field::CHANGED_FLAGS['inAddRecalc']) {
            $newVal = ['v' => null];
            $this->calculate($newVal, $oldRow, $row, $oldTbl, $tbl, [], 'modify');
        } else {
            $editable = $this->isChannelChangeable('modify', $channel);

            if (!$editable) {
                $changeFlag = false;
            }
            switch ($changeFlag) {

                case static::CHANGED_FLAGS['changed']:


                    $newVal = ['v' => $newVal, 'h' => true];

                    if (!($newVal['v'] === '' && $this->data['type'] === 'select' && !empty($this->data['withEmptyVal']))) {
                        $newVal['v'] =
                            $this->modifyValue(
                                $newVal['v'],
                                $oldVal['v'] ?? null,
                                $isCheck,
                                $row
                            );
                    }

                    break;
                case static::CHANGED_FLAGS['setToPinned']:
                    $newVal = ['v' => ($oldVal['v'] ?? null)];
                    $newVal['h'] = true;
                    break;
                case static::CHANGED_FLAGS['setToDefault']:
                    $newVal = ['v' => null];
                    break;
                case false:

                    $newVal = [];
                    $newVal['v'] = $oldVal['v'] ?? null;

                    if (!empty($oldVal['h'])) {
                        $newVal['h'] = true;
                    }
                    break;

            }

            if (empty($this->data['codeOnlyInAdd'])) {
                $this->calculate($newVal, $oldRow, $row, $oldTbl, $tbl, [], "modify");
            }
        }
        try {
            $this->checkVal($newVal, $row, $isCheck);
        } catch (\Exception $e) {
            if (method_exists($e, 'addPath')) {
                $e->addPath($this->translate('field [[%s]] of [[%s]] table',
                        [$this->data['name'], $this->table->getTableRow()['name']]) . (!empty($row['id']) ? ' id ' . $row['id'] : ''));
            }
            throw $e;
        }

        if (!array_key_exists('c', $newVal) && !empty($newVal['h'])) {
            unset($newVal['h']);
        }

        if (empty($newVal['h'])
            || $newVal['c'] === $newVal['v']
            || (is_numeric($newVal['c']) && is_numeric($newVal['v']) && $newVal['c'] == $newVal['v'])) {
            unset($newVal['c']);
        }

        return $newVal;
    }

    /**
     *
     * Значение для секции селекта
     *
     * @param $val
     * @param $row
     * @param array $tbl
     * @return array|mixed|null|string
     */
    public function getSelectValue($val, $row, $tbl = [])
    {
        $return = $val;
        return $return;
    }

    public function getLogValue($val, $row, $tbl = [])
    {
        return $val;
    }

    /**
     * @param $viewType 'web'|'edit'|'xml'|'csv', 'print'
     * @param array $valArray
     * @param $row
     * @param array $tbl
     */
    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        switch ($viewType) {
            case 'print':
            case 'web':
                if (array_key_exists('c', $valArray) && (empty($valArray['h']) || $valArray['c'] === $valArray['v'])) {
                    unset($valArray['c']);
                }
                break;
        }
    }

    public function getFullValue($val, $rowId = null)
    {
        return $val;
    }

    public function addXmlExport(\SimpleXMLElement $simpleXMLElement, $fVar)
    {
        $paramInXml = $simpleXMLElement->addChild($this->data['name'], $fVar['v']);
        if (isset($fVar['e'])) {
            $paramInXml->addAttribute('error', $fVar['e']);
        }
        if (isset($fVar['c'])) {
            $paramInXml->addAttribute('c', $fVar['c']);
            $paramInXml->addAttribute('h', isset($fVar['h']) ? '1' : '0');
        }
    }

    /**
     * @param array $val
     * @param $row
     * @param array $tbl
     * @return array $list
     * @throws errorException
     */
    public function calculateSelectList(array &$val, $row, $tbl = [])
    {
        throw new errorException('This is not select field');
    }


    protected function getDefaultValue()
    {
        return $this->data['default'] ?? null;
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (is_object($modifyVal)) {
            $modifyVal = $modifyVal->val;
        }
        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
    }

    protected function checkVal(&$newVal, $row, $isCheck = false)
    {
        if ($newVal['e'] ?? null) {
            return;
        }
        if (!isset($newVal['v'])) {
            $newVal['v'] = null;
        }


        $val = &$newVal['v'];

        if (!$isCheck && !empty($this->data['required']) && (($val ?? '') === '' || $val === [])) {
            errorException::criticalException(
                $this->translate('Field [[%s]] of table [[%s]] is required.',
                    [$this->data['title'], ($this->table->getTableRow()['title'] ?? $this->table->getTableRow()['name'])]),
                $this->table
            );
        }

        if ($val === '' && $this->data['category'] !== 'filter') {
            $val = null;
        } else {
            try {
                $this->checkValByType($val, $row, $isCheck);
            } catch (errorException $errorException) {
                $newVal['v'] = $this->data['errorText'];
                $newVal['e'] = $errorException->getMessage();
            }
        }


        if (!$this->__checkIsNotBinary($newVal['v'])) {
            $newVal['v'] = $this->data['errorText'];
            $newVal['e'] = $this->translate('Non-utf8 content');
        }
        if (key_exists('c', $newVal) && !$this->__checkIsNotBinary($newVal['c'])) {
            $newVal['c'] = $this->data['errorText'];
        }

    }

    protected function __checkIsNotBinary($val)
    {
        if (is_array($val)) {
            foreach ($val as $_v) {
                if (!$this->__checkIsNotBinary($_v)) {
                    return false;
                }
            }
            return true;
        } elseif (is_string($val)) {
            return mb_detect_encoding($val, 'UTF-8', true);
        }
        return true;
    }

    protected function calculate(array &$newVal, $oldRow, $row, $oldTbl, $tbl, $vars, $calcInit)
    {

        //Поле инсерта расчетных таблиц
        if ($this->data['name'] === 'insert' && is_subclass_of($this->table, JsonTables::class)) {
            $newVal['c'] = $row['insert']['c'] ?? null;
            if (!($newVal['h'] ?? null)) {
                $newVal['v'] = $newVal['c'];
            }
        } elseif (array_key_exists('code', $this->data)) {
            $Log = $this->table->calcLog(['field' => $this->data['name'], 'cType' => 'code', 'action' => $calcInit, 'itemId' => $row['id'] ?? $oldRow['id'] ?? null]);

            try {
                $newVal['c'] = $this->CalculateCode->exec(
                    $this->data,
                    $newVal,
                    $oldRow,
                    $row,
                    $oldTbl,
                    $tbl,
                    $this->table,
                    $vars
                );


                if ($error = $this->CalculateCode->getError()) {
                    $newVal['c'] = $this->data['errorText'];
                    $newVal['e'] = $error;
                    if (in_array($this->data['type'], static::NO_ERROR_IN_VALUE)) {
                        $newVal['c'] = null;
                    }
                }

                if (!($newVal['h'] ?? null)) {
                    $newVal['v'] = $newVal['c'];
                }

                $this->table->calcLog($Log, 'result', $newVal['v']);
            } catch (\Exception $exception) {
                $this->table->calcLog($Log, 'error', $exception->getMessage());
                throw $exception;
            }
        }
    }
}
