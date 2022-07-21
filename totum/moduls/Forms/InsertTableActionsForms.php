<?php

namespace totum\moduls\Forms;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Totum;
use totum\config\totum\moduls\Forms\FormsTrait;
use totum\config\totum\moduls\Forms\WriteTableActionsForms;
use totum\models\TmpTables;
use totum\moduls\Table\WriteTableActions;
use totum\tableTypes\aTable;

class InsertTableActionsForms extends WriteTableActionsForms
{
    use FormsTrait;
    use FormsTrait {
        FormsTrait::getEditSelect as formsGetEditSelect;
    }

    protected mixed $extraParams = null;

    /**
     * @var mixed|string
     */
    private string $insertHash;
    private $insertRowData = null;
    private bool $isJustCreated = false;

    public function __construct(ServerRequestInterface $Request, string $modulePath, aTable $Table = null, Totum $Totum = null)
    {
        parent::__construct($Request, $modulePath, $Table, $Totum);
        $this->post = json_decode((string)$Request->getBody(), true);

        $this->CalcTableFormat = new CalculcateFormat($this->Table->getTableRow()['table_format']);
        $this->CalcRowFormat = new CalculcateFormat($this->Table->getTableRow()['row_format']);
        $hash = $this->post['sess_hash'] ?? $Request->getQueryParams()['sess_hash'] ?? null;

        if (empty($hash) || !str_starts_with($hash,
                'i-') || !($this->insertRowData = $this->getData($hash))) {
            $this->createNewInsertRow();
        } else {
            $this->insertHash = $hash;
        }

        $this->setInsertRowData();
    }

    protected function setInsertRowData()
    {
        $this->insertRowData ??= $this->getData($this->insertHash);
        $this->insertRowData['_ihash'] = $this->insertHash;
        $this->Table->setInsertRowHash($this->insertHash);
    }

    protected function createNewInsertRow()
    {
        do {
            $hash = 'i-' . md5(microtime(true) . rand());
        } while (!TmpTables::init($this->Totum->getConfig())->saveByHash(
            TmpTables::SERVICE_TABLES['insert_row'],
            $this->User,
            $hash,
            [],
            true
        ));
        $this->insertRowData = ['__fixedData' => $this->insertRowData['__fixedData'] ?? []];
        $this->insertHash = $hash;
        $this->isJustCreated = true;
    }

    public function getEditSelect($data = null, $q = null, $parentId = null, $type = null)
    {
        $data = $this->post['data'] ?? [];
        $data['item'] = $this->getInsertRow($this->insertRowData);
        foreach ($data['item'] as &$v) {
            $v = $v['v'];
        }
        unset($v);
        return $this->formsGetEditSelect($data, $q, $parentId, $type);
    }

    public function checkMethodIsAvailable(string $method, string $error)
    {
        if (!in_array(strtolower($method), ['gettabledata', 'edit', 'geteditselect', 'click'])) {
            throw new errorException($error);
        }
    }

    public function edit()
    {
        $data = is_string($this->post['data']) ? json_decode($this->post['data'], true) : $this->post['data'];

        $data['params'] = $this->insertRowData['__fixedData'] + $data['params'];

        $data = ['rows' => [$this->getInsertRow($this->insertRowData,
            $data['params'] ?? [],
            [],
            $this->post['clearField'] ?? null)]];

        $formats = $this->getTableFormats([]);
        $data['params'] = $data['rows'][0];
        unset($data['rows']);
        $data = $this->getValuesForClient($data, $formats, []);
        $data['params'] = ['__save' => ['v' => null]] + $data['params'];


        $this->addLoadedSelects($data);
        $data['f'] = $formats;
        return ['chdata' => $data, 'sess_hash' => $this->insertHash];
    }

    public function getTableData($withRecalculate = true)
    {

        $post = json_decode($this->Request->getBody(), true);

        if (($post['method'] ?? null) === 'getTableData') {
            $get = $post['data']['get'];
            if (!empty($get['d']) && ($params = @Crypt::getDeCrypted($get['d'],
                    $this->Totum->getConfig()->getCryptSolt()
                ))) {
                $this->extraParams = json_decode($params, true);

                if (($this->extraParams['t'] ?? false) !== $this->FormsTableData['path_code']) {
                    throw new errorException('Неверные параметры ссылки');
                }
            }

            if (($this->FormsTableData['format_static']['t']['f']['p'] ?? false)) {
                if (empty($this->extraParams)) {
                    throw new errorException('Для работы формы необходимы параметры ссылки');
                }
            }
        }

        if (!empty($error)) {
            $result['error'] = $error;
        }
        $row = $this->checkInsertRow()['row'];
        $formats = $this->getTableFormats([]);

        $fields = [];
        foreach ($this->clientFields as $field) {
            if ($field['category'] === 'column') {
                $fields[$field['name']] = ['category' => 'param'] + $field;
            }
        }

        $data['params'] = array_intersect_key($row, $this->clientFields);

        $result = [
            'tableRow' => ['sess_hash' => $this->insertHash, 'type' => 'tmp'] + $this->tableRowForClient($this->Table->getTableRow())
            , 'f' => $formats
            , 'c' => $this->getTableControls()
            , 'fields' => $fields
            , 'sections' => $this->sections
            , 'error' => $error ?? null
            , 'data_params' => $data['params']
            , 'updated' => $this->Table->getSavedUpdated()
            , 'lang'=>[
                'name'=>$this->Table->getTotum()->getConfig()->getLang()
            ]

        ];
        return $result;
    }

    protected function getTableFormats($rows)
    {
        $tableFormats = $this->CalcTableFormat->getFormat('TABLE', [], $this->Table->getTbl(), $this->Table);
        $tableJsonFromRow = $this->FormsTableData['format_static'];

        $tableFormats['sections'] = $tableFormats['sections'] ?? [];

        if ($this->CalcSectionStatuses) {
            $sectionFormats = $this->CalcSectionStatuses->exec(
                ['name' => 'CALC SECTION FORMATS'],
                ['v' => null],
                [],
                $this->Table->getTbl()['params'],
                [],
                $this->Table->getTbl(),
                $this->Table
            );
            if ($sectionFormats && is_array($sectionFormats)) {
                foreach ($sectionFormats as $k => $status) {
                    $tableFormats['sections'][$k]['status'] = $status;
                }
            }
        }

        /*2-edit; 1- view; 0 - hidden*/
        $getSectionEditType = function ($sectionName) use ($tableFormats) {
            if (!$sectionName || !key_exists(
                    $sectionName,
                    $tableFormats['sections']
                )) {
                return 2;
            }
            switch ($tableFormats['sections'][$sectionName]['status'] ?? null) {
                case 'edit':
                    return 2;
                default:
                    return 0;
            }
        };


        $result = ['t' => $tableFormats, 'r' => [], 'p' => []];

        foreach ($this->sections as $category => $sec) {
            if ($category == 'param') {
                foreach ($sec as $section) {
                    foreach ($section['fields'] as $fieldName) {
                        if (!key_exists($fieldName, $this->clientFields)) {
                            continue;
                        }
                        if ($fieldName === 'test') {
                            var_dump($section['fields']);
                            die;
                        }

                        if ($getSectionEditType($section['name']) && ($code = $this->FormsTableData['field_code_formats'][$fieldName] ?? $this->Table->getFields()[$fieldName]['format'] ?? null)) {
                            $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                ?? ($this->CalcFieldFormat[$fieldName]
                                    = new CalculcateFormat($code));
                            $format = $FieldFormat->getFormat(
                                $fieldName,
                                $this->Table->getTbl()['params'],
                                $this->Table->getTbl(),
                                $this->Table
                            );

                            if (empty($format['hidden'])) {
                                $result['p'][$fieldName] = $format;
                            } else {
                                $result['p'][$fieldName] = ['hidden' => true];
                            }
                        } else {
                            $result['p'][$fieldName] = [];
                        }
                    }
                }
            }
        }
        $result['t']['s'] = $result['t']['sections'] ?? [];
        unset($result['t']['sections']);

        $tableJsonFromRow['p'] = array_intersect_key(($tableJsonFromRow['p'] ?? []), $this->clientFields);

        unset($tableJsonFromRow['t']['s']['quickMain']['title'], $tableJsonFromRow['t']['s']['quickMain']['ok_message']);
        return array_replace_recursive($tableJsonFromRow, $result);
    }

    public function addTtmFormsData()
    {
        $visibleFields = $this->Table->getVisibleFields('web', false);
        $clientFields = $this->fieldsForClient($visibleFields);

        $clientFields['__save'] = [
            'type' => 'button',
            'insertable' => true,
            'title' => 'Сохранить',
            'buttonText' => 'Сохранить',
            'name' => '__save',
            'category' => 'column',
            'ord' => 100000,
            'width' => 200,
        ];

        foreach ($clientFields as $f => &$field) {
            $field = array_replace($field, $this->FormsTableData['fields_else_params'][$field['name']] ?? []);

            $field['width'] = (int)$field['width'];

            if ($field['showInWebOtherPlacement'] ?? null) {
                $field['category'] = $field['showInWebOtherPlacement'];
                unset($field['showInWebOtherPlacement']);
            }
            if ($field['showInWebOtherOrd'] ?? null) {
                $field['ord'] = $field['showInWebOtherOrd'];
                unset($field['showInWebOtherOrd']);
            }
            $field['editable'] = $field['insertable'];

            if ($field['type'] === 'button' && $field['name'] !== '__save') {
                unset($clientFields[$f]);
            }

            if (!empty($field['help'])) {
                $field['help'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $field['help']);
                $field['help'] = preg_replace('`\s*<hide/?>\s*`su', '', $field['help']);
            }

        }
        unset($field);


        $fields = array_column($clientFields, 'ord');
        array_multisort($fields, SORT_ASC, SORT_NUMERIC, $clientFields);


        $this->clientFields = $clientFields;
        $sections = [];
        foreach ($this->clientFields as $field) {
            if ($field['category'] == 'column') {
                if (($field['tableBreakBefore'] ?? false) && ($field['sectionTitle'] ?? false)) {
                    $name = null;
                    if (preg_match('/\*\*.*?name\s*:\s*([a-z_\-0-9]+)/i', $field['sectionTitle'], $matches)) {
                        $name = $matches[1];
                    }
                    $sections['param'][] = ['name' => $name, 'title' => $field['sectionTitle'], 'fields' => []];
                } elseif (empty($sections['param'])) {
                    $sections['param'][] = ['name' => 'quickMain',
                        'title' => $this->FormsTableData['format_static']['t']['s']['quickMain']['title'] ?? '**name:quickMain;maxwidth:620;nextline:true;fill:true',
                        'fields' => []];
                }

                $sections['param'][count($sections['param']) - 1]['fields'][] = $field['name'];
            }
        }
        $this->sections = $sections;
    }

    public function click()
    {
        $this->Table->checkAndModify(null, [
            'add' => $this->insertHash
        ]);

        if ($this->FormsTableData['format_static']['t']['s']['quickMain']['code_when_saved']) {
            $CA = new CalculateAction($this->FormsTableData['format_static']['t']['s']['quickMain']['code_when_saved']);
            $CA->execAction('CODE',
                [],
                [],
                [],
                [],
                $this->Table,
                'exec',
                ['rowId' => array_key_first($this->Table->getChangeIds()['added'])]);
        } else {

            $CA = new CalculateAction('=: linktodatahtml(title: ""; html: $#html)');
            $CA->execAction('CODE',
                [],
                [],
                [],
                [],
                $this->Table,
                'exec',
                ['html' => $this->FormsTableData['format_static']['t']['s']['quickMain']['ok_message'] ?? 'OK']);
        }
        $this->createNewInsertRow();
        $this->setInsertRowData();

        $data = ['rows' => [$this->getInsertRow($this->insertRowData,
            $this->insertRowData['__fixedData']['f'] + $this->insertRowData['__fixedData']['x'],
            [])]];

        $data = $this->Table->getValuesAndFormatsForClient($data, 'edit', []);
        $data['params'] = ['__save' => ['v' => null]] + $data['rows'][0];
        unset($data['rows']);

        $this->addLoadedSelects($data);
        $data['f'] = $this->getTableFormats([]);
        $data['sess_hash'] = $this->insertHash;
        return ['chdata' => $data, 'sess_hash' => $this->insertHash];
    }

    public function checkInsertRow()
    {
        $insertData = $this->post['data'];

        if ($this->extraParams) {
            if ($this->isJustCreated) {
                if ($this->extraParams['f'] ?? false) {
                    $insertData = $this->extraParams['f'] + $insertData;
                }
            }
            $this->insertRowData['__fixedData'] = ['f' => $this->extraParams['f'] ?? [], 'x' => $this->extraParams['x'] ?? []];
            $insertData = $this->insertRowData['__fixedData']['f'] + $this->insertRowData['__fixedData']['x'] + $insertData;
        }

        $data = ['rows' => [$this->getInsertRow($this->insertRowData,
            $insertData,
            [],
            $this->post['clearField'] ?? null)]];

        $data = $this->Table->getValuesAndFormatsForClient($data, 'edit', []);
        $row = $data['rows'][0];
        $row['__save'] = ['v' => null];
        $res = ['row' => $row, 'hash' => $this->insertHash];
        $this->addLoadedSelects($res);
        return $res;
    }

    private function getData(string $hash)
    {
        try {
            return TmpTables::init($this->Totum->getConfig())->getByHash(
                TmpTables::SERVICE_TABLES['insert_row'],
                $this->User,
                $hash,
                true
            );
        } catch (errorException) {
            return null;
        }
    }
}