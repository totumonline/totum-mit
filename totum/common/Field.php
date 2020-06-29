<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 14.03.17
 * Time: 14:24
 */

namespace totum\common;


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
use totum\tableTypes\_Table;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;

class Field
{
    const ChangedFlags = [
        'changed' => 1,
        'setToDefault' => 2,
        'setToPinned' => 3
    ];
    const typesWithNoErrorInValue = ['number', 'checkbox'];

    static $fields = [];
    protected $data, $table, $CalculateCode, $CalculateCodeSelect, $CalculateCodeSelectValue, $CalculateFormat, $timeCalculating = ['calculate' => 0, 'calculateSelect' => 0], $log;

    /**
     * @var Calculate[];
     */

    static function getDataForUpdates($v)
    {
        return $v;
    }

    /**
     * Является ли значение поля листом
     *
     * @param $type
     * @param $isMulty
     * @return bool
     */
    static function isFieldListValues($type, $isMulty)
    {
        switch ($type) {
            case 'select':
            case 'tree':
                return !!$isMulty;
                break;
            case 'listRow':
            case 'fieldParams':
            case 'fieldParamsResult':
            case 'file':
                return true;
        }
    }

    public function getName()
    {
        return $this->data['name'];
    }

    public function getData($param = null)
    {
        if ($param)
            return $this->data[$param] ?? null;
        return $this->data;
    }

    protected function __construct($fieldData, aTable $table)
    {
        $this->data = $fieldData;
        $this->table = $table;

        if (!empty($fieldData['linkTableName'])) {
            if (empty($fieldData['linkFieldError'])) {


                $params = 'table: \'' . $fieldData['linkTableName'] . '\'; field: \'' . $fieldData['linkFieldName'] . '\'; ';
                if ($this->table->getTableRow()['type'] == 'cycles') {
                    $tableWhere = Table::getTableRowByName($fieldData['linkTableName']);
                    if ($tableWhere['type'] == 'calcs') {
                        $params .= 'cycle: #id';
                    }
                    if ($tableWhere['name'] == 'cycles_access') {
                        $params .= 'where: \'cycle__id\'=#id; where: \'cycles_table_id\'="' . $this->table->getTableRow()['id'] . '"';
                    }
                }

                $this->data['code'] = '=:select(' . $params . ')';
            } else {
                $this->data['code'] = '=:errorExeption(text: "Неверные параметры якорного поля")';
            }
            $this->data['codeOnlyInAdd'] = false;

        }

        if (!empty($this->data['code'])) $this->CalculateCode = new Calculate($this->data['code']);

        if (!empty($this->data['format']) && $this->data['format'] != 'f1=:') $this->CalculateFormat = new CalculcateFormat($this->data['format']);
        if (empty($this->data['errorText'])) $this->data['errorText'] = 'ОШБК!';

    }

    /**
     * @param $fieldData
     * @param _Table $table
     * @return Field
     */
    static function init($fieldData, _Table $table)
    {
        $staticName = $table->getTableRow()['id'] . '/' . $fieldData['name'];
        if ($table->getTableRow()['type'] == 'calcs') {
            $staticName .= '/' . $table->getCycle()->getId();
        }
        return static::$fields[$staticName] = static::$fields[$staticName] ?? (function () use ($fieldData, $table) {
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
                        if ($table->getTableRow()['id'] === Table::$TableId && $fieldData['name'] === 'name') {
                            $model = TableNameField::class;
                        } else
                            $model = Unic::class;
                        break;
                    case 'chart':
                        $model = Chart::class;
                        break;
                    default:
                        $model = Field::class;
                        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                        throw new errorException('Тип поля не определен');
                }
                return new $model($fieldData, $table);
            })();
    }

    function getValueFromCsv($val)
    {
        return $val;
    }


    function addFormat(&$valArray, $row, $tbl)
    {
        if ($this->CalculateFormat) {
            if ($format = $this->CalculateFormat->getFormat($this->data['name'], $row, $tbl, $this->table)) {
                $valArray['f'] = $format;
            }
            $this->addInControllerLog('f', $this->CalculateFormat->getLogVar(), $row);
        }
    }

    function __destruct()
    {
        if ($this->timeCalculating['calculate'] > 0.03)
            Log::calcs($this->data['name'] . ' ' . $this->table->getTableRow()['name'] . print_r($this->timeCalculating,
                    1));
    }

    function getModifyFlag($newValExists, $newVal, $oldVal, $isInSetTodefaults, $isInSetToPinned, $modifyCalculated)
    {

        if ($isInSetToPinned)
            return Field::ChangedFlags['setToPinned'];
        else if ($isInSetTodefaults)
            return Field::ChangedFlags['setToDefault'];
        elseif (isset($newVal) || $newValExists) {


            if ($modifyCalculated !== true) {
                if ($oldVal && ($oldVal['v'] == $newVal)) {
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
            return Field::ChangedFlags['changed'];
        }
        return false;
    }

    function action($oldRow, $newRow, $oldTbl, $newTbl, $vars = [])
    {
        if (!empty($this->data['codeAction'])) {


            $CalculateCodeAction = new CalculateAction($this->data['codeAction']);

            try {

                try {
                    $CalculateCodeAction->execAction($this->data['name'],
                        $oldRow,
                        $newRow,
                        $oldTbl,
                        $newTbl,
                        $this->table,
                        $vars);
                } catch (errorException $e) {
                    if (Auth::isCreator()) {
                        $e->addPath('Таблица [[' . $this->table->getTableRow()['name'] . ']]; Поле [[' . $this->data['name'] . ']]');
                    } else {
                        $e->addPath('Таблица [[' . $this->table->getTableRow()['title'] . ']]; Поле [[' . $this->data['title'] . ']]');
                    }

                    throw $e;
                }

                $this->addInControllerLog('a', $CalculateCodeAction->getLogVar(), $oldRow ?? $newRow);

            } catch (errorException $errorException) {

                $this->addInControllerLog('a', $CalculateCodeAction->getLogVar(), $oldRow ?? $newRow);
                throw $errorException;
            }

        }
    }

    function isLogging()
    {
        if (array_key_exists('logging', $this->data) && $this->data['logging'] === false) return false;
        return true;
    }

    function isChannelChangeable($action, $channel)
    {
        switch ($channel) {
            case 'web':
                return $this->isWebChangeable($action);
            case 'xml':
                return $this->isXmlChangeable($action);
            case 'inner':
                return true;
            default:
                throw new errorException('Не указан канал ' . $action);
        }
    }

    /**
     * @param $action 'insert|modify'
     */
    function isWebChangeable($action)
    {
        if ($this->data['category'] === 'filter') {
            $action = 'modify'; //Проверять по параметрам изменения - хак для фильтров
        }

        switch ($action) {
            case 'insert':
                if ($insertable = !empty($this->data['insertable'])) {
                    if (!Auth::isCreator() && !empty($this->data['webRoles'])) {
                        if (count(array_intersect($this->data['webRoles'], Auth::$aUser->getRoles())) == 0) {
                            $insertable = false;
                        }
                    }
                    if ($insertable && !empty($this->data['addRoles']) && count(array_intersect($this->data['addRoles'],
                            Auth::$aUser->getRoles())) == 0) {
                        $insertable = false;
                    }
                }
                return $insertable;
                break;
            case 'modify':
                if ($editable = !empty($this->data['editable'])) {

                    //Для фильтров не применять webRoles
                    if (!Auth::isCreator() && $this->data['category'] !== 'filter' && !empty($this->data['webRoles'])) {
                        if (count(array_intersect($this->data['webRoles'], Auth::$aUser->getRoles())) == 0) {
                            $editable = false;
                        }
                    }
                    if ($editable && !empty($this->data['editRoles']) && count(array_intersect($this->data['editRoles'],
                            Auth::$aUser->getRoles())) == 0) {
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
    function isXmlChangeable($action)
    {
        if ($this->data['category'] === 'filter') {
            $action = 'modify'; //Проверять по параметрам изменения - хак для фильтров
        }

        switch ($action) {
            case 'insert':
                if ($insertable = !empty($this->data['apiInsertable'])) {
                    if (!empty($this->data['xmlRoles'])) {
                        if (count(array_intersect($this->data['xmlRoles'], Auth::$aUser->getRoles())) == 0) {
                            $insertable = false;
                        }
                    }
                }
                return $insertable;
                break;
            case 'modify':
                if ($editable = !empty($this->data['apiEditable'])) {
                    if (!Auth::isCreator() && !empty($this->data['xmlRoles'])) {
                        if (count(array_intersect($this->data['xmlRoles'], Auth::$aUser->getRoles())) == 0) {
                            $editable = false;
                        }
                        if ($editable && !empty($this->data['xmlEditRoles']) && count(array_intersect($this->data['xmlEditRoles'],
                                Auth::$aUser->getRoles())) == 0) {
                            $editable = false;
                        }
                    }
                }
                return $editable;
                break;
        }
    }

    function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {

        switch ($channel) {
            case 'inner':
                $insertable = true;
                break;
            case 'web':
                $insertable = $this->isWebChangeable('insert');

                break;
            case 'xml':
                $insertable = $this->isXmlChangeable('insert');
                break;
            default:
                throw new errorException('Не указан канал добавления');
        }

        $newVal = ['v' => null];


        if (empty($this->data['code']) && (!$insertable || $inNewVal === '' || is_null($inNewVal) || $inNewVal === $this->data['errorText'])) {

            if ($this->data['category'] === 'filter') {
                if (is_null($inNewVal)) {
                    $inNewVal = $this->getDefaultValue();
                }
                $newVal = ['v' => $inNewVal];
            } elseif (array_key_exists('default', $this->data) && $this->data['default'] !== '') {
                $inNewVal = $this->getDefaultValue();
                $newVal = ['v' => $inNewVal];

            } elseif ($insertable && !empty($this->data['required']) && !$isCheck) {

                throw new errorException('Поле [[' . $this->data['title'] . ']] обязательно для заполнения Таблица [[' . $this->table->getTableRow()['title'] . ']]');
            }

        }


        if ($insertable) {

            $newVal = ['v' => $inNewVal];
        }
        /*if ($this->data['category']==='filter'){
            var_dump($newVal); die;
        }


        */

        //Что бы это значило?
        if ($this->data['category'] === 'filter' && $newVal['v'] === '') {
            $newVal['h'] = true;
        } else if ($newVal['v'] !== '' && !is_null($newVal['v'])) {
            $newVal['h'] = true;
        }

        //Чтобы не рассчитывать, если значение уже задано для codeOnlyInAdd
        if (empty($newVal['h']) || empty($this->data['codeOnlyInAdd'])) {
            $this->calculate($newVal, [], $row, $oldTbl, $tbl, $vars);
        }


        if (!$isCheck && !empty($this->data['codeOnlyInAdd'])) {
            unset($newVal['c']);
        }

        try {
            $this->checkVal($newVal, $row, $isCheck);
        } catch (errorException $e) {
            if (Auth::isCreator()) {
                $e->addPath('Таблица [[' . $this->table->getTableRow()['name'] . ']]; Поле: [[' . $this->data['name'] . ']]');
            } else {
                $e->addPath('Таблица [[' . $this->table->getTableRow()['title'] . ']]; Поле [[' . $this->data['title'] . ']]');
            }

            if (!$isCheck) throw $e;
        }

        if (!empty($newVal['h'])) {
            if (is_null($newVal['v']) || !array_key_exists('c', $newVal) || $newVal['c'] == $newVal['v']) {
                unset($newVal['h']);
            }
        }

        if (array_key_exists('c', $newVal)) {
            if (empty($newVal['h']) || $newVal['c'] === $newVal['v']) {
                unset($newVal['c']);
            }
        }

        return $newVal;
    }

    function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {


        $oldVal = $oldRow[$this->data['name']] ?? null;

        $editable = $this->isChannelChangeable('modify', $channel);

        if (!$editable) {
            $changeFlag = false;
        }


        switch ($changeFlag) {

            case static::ChangedFlags['changed']:


                $newVal = ['v' => $newVal, 'h' => true];

                if (!($newVal['v'] === '' && $this->data['type'] == 'select' && !empty($this->data['withEmptyVal']))) {

                    $newVal['v'] =
                        $this->modifyValue($newVal['v'],
                            $oldVal['v'],
                            $isCheck);

                }

                break;
            case static::ChangedFlags['setToPinned']:
                $newVal = ['v' => ($oldVal['v'] ?? null)];
                $newVal['h'] = true;
                break;
            case static::ChangedFlags['setToDefault']:
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

            $this->calculate($newVal, $oldRow, $row, $oldTbl, $tbl);
        }
        try {

            $this->checkVal($newVal, $row, $isCheck);
        } catch (errorException $e) {
            if (Auth::isCreator()) {
                $e->addPath('Таблица [[' . $this->table->getTableRow()['name'] . ']]; Поле: [[' . $this->data['name'] . ']]');
            } else {
                $e->addPath('Таблица [[' . $this->table->getTableRow()['title'] . ']]; Поле [[' . $this->data['title'] . ']]');
            }

            throw $e;
        }

        if (!array_key_exists('c', $newVal) && !empty($newVal['h'])) {
            unset($newVal['h']);
        }

        if (array_key_exists('c', $newVal)) {
            //сделано для того, чтобы не пересчитывались каскадно старые расчетные таблицы
            if (!array_key_exists('c',
                    $oldVal ?? []) || $this->table->isNowTable() || $changeFlag === static::ChangedFlags['setToDefault']) {
                if (empty($newVal['h']) || $newVal['c'] === $newVal['v']) {
                    unset($newVal['c']);
                }
            }
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
    function getSelectValue($val, $row, $tbl = [])
    {
        $return = $val;
        return $return;
    }

    function getLogValue($val, $row, $tbl = [])
    {
        return $val;
    }

    /**
     * @param $viewType 'web'|'edit'|'xml'|'csv', 'print'
     * @param array $valArray
     * @param $row
     * @param array $tbl
     */
    function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        switch ($viewType) {
            case 'print':
            case 'web':
                if (array_key_exists('c', $valArray) && (empty($valArray['h']) || $valArray['c'] == $valArray['v'])) {
                    unset($valArray['c']);
                }
                break;
        }
    }

    function getFullValue($val, $rowId = null)
    {
        return $val;
    }

    function addXmlExport(\SimpleXMLElement $simpleXMLElement, $fVar)
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
     * @param $val
     * @param $row
     * @param array $tbl
     * @return array $list
     * @throws errorException
     */
    function calculateSelectList(&$val, $row, $tbl = [])
    {
        throw new errorException('Это не поле селекта');

    }


    protected function getDefaultValue()
    {
        return $this->data['default'] ?? null;
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck)
    {
        if (is_object($modifyVal)) {
            $modifyVal = $modifyVal->val;
        }
        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {

    }

    protected
    function checkVal(&$newVal, $row, $isCheck = false)
    {
        if ($newVal['e'] ?? null) return;
        if (!isset($newVal['v'])) {
            $newVal['v'] = null;
        }


        $val = &$newVal['v'];

        if (!$isCheck && !empty($this->data['required']) && ($val === '' || $val === null)) {
            throw new criticalErrorException('Поле [[' . $this->data['title'] . ']] таблицы [[' . $this->table->getTableRow()['title'] . ']] должно быть заполнено');
        }

        if (!in_array($this->data['type'], ['string', 'text']) && $val === '' && $this->data['category'] !== 'filter') {
            $val = null;
        } else {
            $this->checkValByType($val, $row, $isCheck);
        }

    }

    protected
    function calculate(&$newVal, $oldRow, $row, $oldTbl, $tbl = [], $vars = [])
    {

        $diffStart = microtime(1);

        //Поле инсерта расчетных таблиц
        if ($this->data['name'] == 'insert' && is_subclass_of($this->table, JsonTables::class)) {
            $newVal['c'] = $row['insert']['c'] ?? null;
            if ($newVal['h'] ?? null) ;
            else $newVal['v'] = $newVal['c'];

        } elseif (array_key_exists('code', $this->data)) {


            $newVal['c'] = $this->CalculateCode->exec($this->data,
                $newVal,
                $oldRow,
                $row,
                $oldTbl,
                $tbl,
                $this->table,
                $vars);


            if ($error = $this->CalculateCode->getError()) {
                $newVal['c'] = $this->data['errorText'];
                $newVal['e'] = $error;

            } else {
                try {
                    $this->checkValByType($newVal['c'], $row);
                } catch (errorException $e) {
                    $newVal['c'] = $this->data['errorText'];
                    $error = $newVal['e'] = $e->getMessage();
                }
            }

            if (!empty($error)) {
                if (in_array($this->data['type'], static::typesWithNoErrorInValue)) {
                    $newVal['c'] = null;
                }
            }


            $this->addInControllerLog('c', $this->CalculateCode->getLogVar(), $row);

            if ($newVal['h'] ?? null) ;
            else $newVal['v'] = $newVal['c'];
        }

        $this->timeCalculating[__FUNCTION__] += microtime(1) - $diffStart;
    }

    /*Лог рассчетов для веб-интерфейса*/
    protected function addInControllerLog($type, $log, $row = null)
    {

        $path = [];
        if ($this->data['category'] == 'column') {
            $path[] = $row['id'] ?? 0;
        }
        $path[] = $this->data['name'];
        Controller::addLogVar($this->table, $path, $type, $log);
    }
}

