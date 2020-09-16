<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 26.04.17
 * Time: 16:32
 */

namespace totum\moduls\Xml;

/*
 * php://import
 *
<?xml version="1.0" encoding="UTF-8"?>
<request type="import">
	<authorization login="xml" password="1234"/>
	<import>
	<header><h_data2 h="1">55</h_data2></header>
	</import>
</request>

 */

use totum\common\Auth;
use totum\common\Controller;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Sql;
use totum\common\tableSaveException;
use totum\config\Conf;
use totum\models\Table;
use totum\models\User;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;
use totum\tableTypes\tableTypes;

use SimpleXMLElement;

class XmlController extends Controller
{
    /**
     * @var SimpleXMLElement
     */
    protected $xmlObject, $outXmlObject;
    protected $inModuleUri;
    /**
     * @var aTable
     */
    protected $Table;
    /**
     * @var Auth
     */
    protected $aUser;


    function __construct($modulName, $inModuleUri, Conf $Config)
    {
        parent::__construct($modulName, $inModuleUri, $Config);
        $this->inModuleUri = $inModuleUri;
    }

    function doIt($action)
    {

        $xmlString = file_get_contents('php://input');

        $this->outXmlObject = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><totumXml></totumXml>');
        try {
            $this->parseXml($xmlString);
            $this->authUser();
            $type = $this->checkRequestType();
            $this->checkTable($type);




            switch ($type) {
                case 'export':
                    $filters = [];
                    foreach ($this->xmlObject->xpath('export/filters')[0] ?? [] as $k => $v) {
                        if (!empty($this->Table->getFields()[$k]['multiple']) || ($k == 'id' && $v->count() > 0)) {
                            $filters[$k] = $filters[$k] ?? [];
                            foreach ($v->xpath('value') as $v) {
                                $filters[$k][] = strval($v);
                            }
                        } else {
                            $filters[$k] = strval($v);
                        }
                    }
                    $this->Table->setFilters($filters, false);
                    $this->xmlExport($filters);
                    break;
                case 'import':
                    $this->xmlImport();
                    break;
                case 'recalc':
                    $this->xmlRecalc();
                    break;
            }


        }
        /*catch (tableSaveException $e){
            tableTypes::$tables=[];
            Table::clearCaches();
            Sql::$PDO=null;
            $this->doIt($action);
            return;
        }*/
        catch (errorException $e) {
            $error = $e->getCode();
            $errorDescription = $e->getMessage();

        }

        $this->sendXml($error ?? 0, $errorDescription ?? '');

        foreach (Controller::getLinks()??[] as $link){

            $data =http_build_query($link['postData']);

            $context = stream_context_create(
                array(
                    'http'=>array(
                        'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                        'method' => 'POST',
                        'content' => $data
                    )
                )
            );
            $contents = file_get_contents($link['uri'], false ,$context);
        }
    }

    protected function xmlRecalc(){
        $recalcXmlObject = $this->xmlObject->xpath('recalc')[0];
        $inVars = [];
        $inVars['modify']=[];
        foreach ($recalcXmlObject->xpath('ids/id') as $idObj){
            $inVars['modify'][strval($idObj)]=[];
        }
        $updatedOld = $this->Table->updated;

        $this->Table->reCalculateFromOvers($inVars);
        $this->outXmlObject->addChild('recalc');

        if ($updatedOld != $this->Table->updated) {
            $this->outXmlObject->addAttribute('updated', json_decode($this->Table->updated, true)['dt']);
        }

    }
    protected function getXmlData()
    {
        $import = [];

    }


    protected function xmlImport()
    {
        $import = [];


        $import['modify'] = [];
        $import['setValuesToDefaults'] = [];
        $import['add'] = [];
        $import['remove'] = [];
        $importOutXmlObject = $this->outXmlObject->addChild('import');
        $importOutXmlObject->addAttribute('table', $this->Table->getTableRow()['name']);

        $importXmlObject = $this->xmlObject->xpath('import')[0];

        $checkStringFromImport = function ($v, $field) {
            if (!ctype_digit($v)) {
                $v = base64_decode($v);
            }
            if ($field['type'] == 'checkbox') {
                $v = $v === 'true' ? true : false;
            }
            return $v;
        };


        $addValToImportNoColumn = function (SimpleXMLElement $v, $field) use (&$import, $checkStringFromImport) {

            if (empty($field['apiEditable'])) throw new errorException('Поле [[' . $field['name'] . ']] запрещено для редактирования через Api',
                11);

            $attributes = [];
            foreach ($v->attributes() as $kA => $vA) {
                $attributes[$kA] = strval($vA);
            }

            if ($field['type'] === 'select' || $field['type'] === 'tree') {
                if (!empty($field['multiple'])) {
                    $vTmp = [];
                    if (strval($v) !== '') throw new errorException('Поле [[' . $field['name'] . ']] должно содержать множественный селект',
                        11);

                    if ($vVals = $v->xpath('value')) {
                        foreach ($vVals as $kS => $vS) {
                            $vTmp[] = strval($vS);
                        }
                    }
                    $v = $vTmp;
                } else {
                    if ($v->count()) throw new errorException('Поле [[' . $field['name'] . ']]  должно содержать значение',
                        11);
                    $v = strval($v);
                }
            } else {
                $v = strval($v);
                $v = $checkStringFromImport($v, $field);
            }

            if (!empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                if (!array_key_exists('h', $attributes)) {
                    throw new errorException('Поле [[' . $field['name'] . ']] требует указания атрибута h', 11);
                }
                if ($attributes['h'] == 0) {
                    $import['setValuesToDefaults']['params'][$field['name']] = null;
                } else {
                    $import['modify']['params'][$field['name']] = $v;
                }
            } else {
                $import['modify']['params'][$field['name']] = $v;
            }
        };

        $fields = $this->Table->getFields();
        foreach (['header' => 'param', 'footer' => 'footer'] as $path => $category) {
            if ($header = $importXmlObject->xpath($path)[0] ?? null) {
                foreach ($header->children() as $k => $v) {
                    if (empty($fields[$k]) || $fields[$k]['category'] !== $category) throw new errorException('Поля [[' . $k . ']] в ' . $path . ' таблицы не существует',
                        11);
                    $field = $fields[$k];
                    if (empty($field['showInXml']) || empty($field['apiEditable'])) throw new errorException('Поля [[' . $k . ']] недоступно для изменения из Api',
                        11);
                    $addValToImportNoColumn($v, $field);
                };
            }
        }
        $columnFooters = $importXmlObject->xpath('column-footers');
        if ($columnFooters[0] ?? false) {
            /** @var SimpleXMLElement $v */
            foreach ($columnFooters[0]->children() as $columnName => $v) {
                if (empty($fields[$columnName]) || $fields[$columnName]['category'] !== 'column')
                    throw new errorException('Колонки [[' . $columnName . ']] в таблице не существует', 11);

                foreach ($v->children() as $fName => $vals) {
                    if (empty($fields[$fName]) || $fields[$fName]['category'] !== 'footer' || empty($fields[$fName]['column']) || $fields[$fName]['column'] != $columnName) throw new errorException('Поля [[' . $fName . ']] в футере колонки [[' . $columnName . ']] таблицы не существует',
                        11);
                    $addValToImportNoColumn($vals, $fields[$fName]);
                }
            }
        }
        foreach ($importXmlObject->xpath('rows/row') as $v) {
            $id = strval($v->xpath('id')[0] ?? 0);
            $attributes = [];
            foreach ($v->attributes() as $kA => $vA) {
                $attributes[$kA] = strval($vA);
            }
            if ($id == '0') {
                $modifyTmp = &$import['add'][];
                if (!empty($attributes['remove'])) {
                    throw new errorException('Нельзя удалить строку без id', 11);
                }
            } else {
                if (!empty($attributes['remove'])) {
                    if ($attributes['remove'] === 'true') {
                        $import['remove'][] = $id;
                        continue;

                    } else {
                        throw new errorException('Некорректное значение аттрибута "remove"', 11);
                    }

                }
                $modifyTmp = &$import['modify'][$id];
                $defaultTmp = &$import['setValuesToDefaults'][$id];
            }
            /** @var SimpleXMLElement $v */
            foreach ($v->children() as $fName => $v) {

                if ($fName == 'id') continue;

                if (empty($fields[$fName]) || $fields[$fName]['category'] !== 'column') throw new errorException('Колонки [[' . $fName . ']] не существует',
                    11);
                $field = $fields[$fName];

                if ($id == '0') {
                    if (empty($field['apiInsertable'])) throw new errorException('Поле [[' . $field['name'] . ']] запрещено для добавления через Api',
                        11);
                } else {
                    if (empty($field['apiEditable'])) throw new errorException('Поле [[' . $field['name'] . ']] запрещено для редактирования через Api',
                        11);
                }

                $attributes = [];
                foreach ($v->attributes() as $kA => $vA) {
                    $attributes[$kA] = strval($vA);
                }

                if ($field['type'] === 'select' || $field['type'] === 'tree') {
                    if (!empty($field['multiple'])) {
                        $vTmp = [];
                        if (strval($v) !== '') throw new errorException('Поле [[' . $field['name'] . ']] должно содержать множественный селект',
                            11);

                        if ($vVals = $v->xpath('value')) {
                            foreach ($vVals as $kS => $vS) {
                                $vS = $checkStringFromImport(strval($vS), $field);
                                $vTmp[] = $vS;
                            }
                        }
                        $v = $vTmp;
                    } else {
                        if ($v->count()) throw new errorException('Поле [[' . $field['name'] . ']]  должно содержать значение',
                            11);
                        $v = strval($v);
                        $v = $checkStringFromImport($v, $field);
                    }
                } else {
                    $v = strval($v);
                    $v = $checkStringFromImport($v, $field);
                }

                if (!empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                    if (!array_key_exists('h', $attributes)) {
                        throw new errorException('Поле [[' . $field['name'] . ']] требует указания атрибута h', 11);
                    }
                    if ($attributes['h'] == 0) {
                        if ($id != 0) {
                            $defaultTmp[$field['name']] = null;
                        }

                    } else {
                        $modifyTmp[$field['name']] = $v;
                    }
                } else {
                    $modifyTmp[$field['name']] = $v;
                }
            }
        }
        $import['channel'] = 'xml';

        $updatedOld = $this->Table->getLastUpdated();


        if ($import['add'] && !Table::isUserCanAction( 'insert', $this->Table->getTableRow())) throw new errorException('Добавление в эту таблицу вам запрещено');
        if ($import['remove'] && !Table::isUserCanAction( 'delete', $this->Table->getTableRow())) throw new errorException('Удаление из этой таблицы вам запрещено');


        $this->Table->reCalculateFromOvers($import);
        $addedIds = $this->Table->addedIds;
        if (!empty($addedIds)) {
            $added_row_id = $importOutXmlObject->addChild('addedRowIds');
            foreach ($addedIds as $id) $added_row_id->addChild('id', $id);
        }
        $deleted = $this->Table->deletedIds;
        if (!empty($deleted)) {
            $added_row_id = $importOutXmlObject->addChild('deletedRowIds');
            foreach ($deleted as $id) $added_row_id->addChild('id', $id);
        }
        if ($updatedOld != $this->Table->getLastUpdated()) {
            $importOutXmlObject->addAttribute('updated', json_decode($this->Table->getLastUpdated(), true)['dt']);
        }
    }

    protected function xmlExport($filters)
    {
        $table = $this->Table->getDataForXml();
        $exportXmlObject = $this->outXmlObject->addChild('export');
        $exportXmlObject->addAttribute('table', $table['name']);
        $exportXmlObject->addAttribute('updated', json_decode($table['updated'], true)['dt']);

        $sortedXmlFields = $table['fields'];

        $addFieldToXml = function (SimpleXMLElement $simpleXMLElement, $field, $fVar) {
            $Field = Field::init($field, $this->Table);
            $Field->addXmlExport($simpleXMLElement, $fVar);
        };

        //header
        $xmlElement = $exportXmlObject->addChild('header');
        foreach ($sortedXmlFields['param'] ?? [] as $fName => $field) {
            $addFieldToXml($xmlElement, $field, $table['params'][$fName]);
        }

        //filters
        $xmlElement = $exportXmlObject->addChild('filters');
        foreach ($sortedXmlFields['filter'] ?? [] as $fName => $field) {
            $addFieldToXml($xmlElement, $field, $table['params'][$fName]);
        }

        if (!empty($filters['id'])) {
            $xmlElement->addChild('id', $filters['id']);
            //$addFieldToXml($xmlElement, ['type' => 'integer', 'name' => 'id'], ['v' => $filters['id']]);
        }


        //rows
        $xmlElement = $exportXmlObject->addChild('rows');
        foreach ($table['data'] as $row) {
            $xmlRow = $xmlElement->addChild('row');

            $xmlRow->addChild('id', $row['id']);
            if($this->Table->getTableRow()['order_field']==='n'){
                $xmlRow->addChild('n', $row['n']);
            }
            foreach ($sortedXmlFields['column'] ?? [] as $fName => $field) {
                $addFieldToXml($xmlRow, $field, $row[$fName]);
            }
        }

        //footers
        if (is_a($this, JsonTables::class)) {
            $footerColumns = $this->Table->getFooterColumns($sortedXmlFields['footer'] ?? []);

            $xmlColumnFooters = $exportXmlObject->addChild('column-footers');
            foreach ($footerColumns as $column => $footers) {
                if ($column != '') {
                    $xmlElement = $xmlColumnFooters->addChild($column);
                    foreach ($footers ?? [] as $fName => $field) {
                        $addFieldToXml($xmlElement, $field, $table['params'][$fName]);
                    }
                }
            }

            $xmlElement = $exportXmlObject->addChild('footer');
            foreach ($footerColumns[''] ?? [] as $fName => $field) {
                $addFieldToXml($xmlElement, $field, $table['params'][$fName]);
            }
        }

    }

    protected function checkRequestType()
    {
        if (!in_array($type = (string)$this->xmlObject['type'], ['import', 'export', 'recalc'])) {
            throw new errorException('Некорректный тип запроса', 8);
        }
        return $type;
    }

    protected function checkTable($type)
    {
        try {
            if (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $this->inModuleUri, $match)) {
                $cyclesTableId = $match[1];
                $cycleId = $match[2];
                $cycleTableId = $match[3];
                if (!($tableRow = Table::getTableRowById($cycleTableId))) {
                    throw new errorException('');
                }

                $Cycle = Cycle::init($cycleId, $cyclesTableId);
                $this->Table = $Cycle->getTable($tableRow);

            } elseif (preg_match('/^(\d+)$/', $this->inModuleUri, $match)) {
                $tableId = $match[1];

                if (!($tableRow = Table::getTableRowById($tableId))) {
                    throw new errorException('');
                }

                $this->Table = tableTypes::getTable($tableRow);

            } else {
                throw new errorException('');
            }

        } catch (errorException $errorException) {
            throw new errorException('Путь не верный', 6);
        }

        $userTables = $this->aUser->getTables();
        if (!isset($userTables[$tableRow['id']])) {
            throw new errorException('Доступ к таблице запрещен', 9);
        }
        if ($type == 'import' && empty($userTables[$tableRow['id']])) {
            throw new errorException('Доступ к таблице на запись запрещен', 10);
        }


    }

    protected function parseXml($xmlString)
    {
        $xmlString = trim($xmlString);
        if (!is_a($xmlObject = @simplexml_load_string($xmlString), SimpleXMLElement::class)) {
            throw new errorException('Получен невалидный xml', 1);
        }

        $this->xmlObject = $xmlObject;
    }

    protected function authUser()
    {

        if (!($authorization = $this->xmlObject->xpath('authorization')))
            throw new errorException('Узел authorization не найден', '2');
        $authorization = $authorization[0];
        if (!isset($authorization['login'])) throw new errorException('Атрибут login не найден', 3);
        if (!isset($authorization['password'])) throw new errorException('Атрибут password не найден', 4);

        if (!($userId = User::init()->getField('id',
            ['login' => (string)$authorization['login'], 'pass' => md5((string)$authorization['password']), 'interface' => 'xmljson', 'is_del' => false]))
        ) {
            throw new errorException('Пользователь с такими данными не найден. Возможно, ему не включен доступ к xml-интерфейсу',
                5);
        }

        $this->aUser = Auth::xmlInterfaceAuth($userId);


    }

    protected function sendXml($error, $errorDescription)
    {

        $this->outXmlObject->addAttribute('error', $error);
        $this->outXmlObject->addAttribute('errorDescription', $errorDescription);

        header('Content-type: text/xml');
        echo $this->outXmlObject->asXML();
    }
}