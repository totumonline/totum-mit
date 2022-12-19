<?php


namespace totum\moduls\Table;

use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\Services\ServicesConnector;
use totum\common\Totum;
use totum\fieldTypes\Comments;
use totum\fieldTypes\File;
use totum\fieldTypes\Select;
use totum\models\TmpTables;
use totum\tableTypes\aTable;
use totum\tableTypes\tmpTable;

class ReadTableActions extends Actions
{
    protected bool $creatorCommonView = false;
    protected $kanban_bases = [];

    protected function getTableFormat(array $rowIds): array
    {
        $tFormat = [];
        if ($this->Table->getTableRow()['table_format'] && $this->Table->getTableRow()['table_format'] != 'f1=:') {
            $Log = $this->Table->calcLog(['name' => 'TABLE FORMAT']);

            $calc = new CalculcateFormat($this->Table->getTableRow()['table_format']);
            $tFormat = $calc->getFormat(
                'TABLE',
                [],
                $this->Table->getTbl(),
                $this->Table,
                ['rows' => $this->Table->getRowsForFormat($rowIds)]
            );
            $this->Table->calcLog($Log, 'result', $tFormat);
        }
        if ($this->Table->getChangeIds()['reordered']) {
            $tFormat['refreshOrder'] = true;
        }

        if (($this->Table->getTableRow()['panels_view']['kanban_html_type'] ?? false) === 'show' && !empty($this->Table->getTableRow()['panels_view']['kanban'])) {
            $tFormat['kanban_html'] = $this->__getKanbanHtml();
        }

        return $tFormat;
    }

    public function getKanbanHtml()
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );
        return ['kanban_html' => $this->__getKanbanHtml()];

    }

    protected function __getKanbanHtml()
    {
        if (!$this->kanban_bases) {
            $this->getKanbanData();
        }
        $fCalc = new CalculcateFormat($this->Table->getTableRow()['panels_view']['kanban_html_code']);
        $fCalc->setStartSections(['=']);
        $Log = $this->Table->calcLog(['name' => 'Kanban format']);
        try {
            $htmls = [];
            foreach ($this->kanban_bases as $base) {
                $htmls[$base] = $fCalc->exec(['name' => '_KANBAN_FORMAT'],
                    [],
                    [],
                    [],
                    $this->Table->getTbl(),
                    $this->Table->getTbl(),
                    $this->Table,
                    ['kanban' => $base]);
            }
            $this->Table->calcLog($Log, 'result', $htmls);
        } catch (errorException $e) {
            $this->Table->calcLog($Log, 'error', $e->getMessage());
        }
        return $htmls ?? [];
    }

    public function csvExport()
    {
        if ($this->Table->isUserCanAction('csv')) {
            return $this->Table->csvExport(
                $this->post['tableData'] ?? [],
                $this->post['sorted_ids'] ?? '[]',
                json_decode($this->post['visibleFields'] ?? '[]', true),
                $this->post['type']
            );
        } else {
            throw new errorException($this->translate('Csv download of this table is not allowed for your role.'));
        }
    }

    public function setTableFavorite()
    {
        if ($this->post['status']) {
            $status = json_decode($this->post['status'], true);
            if (key_exists(
                    $this->Table->getTableRow()['id'],
                    $this->User->getTreeTables()
                ) && in_array(
                    $this->Table->getTableRow()['id'],
                    $this->User->getFavoriteTables()
                ) !== $status) {
                $Users = $this->Table->getTotum()->getTable('users');
                if ($status) {
                    $favorite = array_merge(
                        $this->User->getFavoriteTables(),
                        [$this->Table->getTableRow()['id']]
                    );
                } else {
                    $favorite = array_diff(
                        $this->User->getFavoriteTables(),
                        [$this->Table->getTableRow()['id']]
                    );
                }
                $Users->reCalculateFromOvers(['modify' => [$this->User->getId() => ['favorite' => $favorite]]]);
            }
            return ['status' => $this->post['status'] === 'true'];
        }
    }

    public function getPanelFormats()
    {
        $result = null;
        if (!empty($this->post['field']) && !empty($field = $this->Table->getFields()[$this->post['field']])) {
            $this->Table->reCalculateFilters(
                'web',
                false,
                false,
                ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
            );


            $tbl = $this->Table->getTbl();
            $item = $tbl['params'];

            if ($field['category'] === 'column') {
                if (is_numeric($this->post['id'])) {
                    $this->Table->checkIsUserCanViewIds('web', [$this->post['id']]);
                    $item = $this->Table->getTbl()['rows'][$this->post['id']];
                } else {
                    $item = $this->getInsertRow($this->post['id']);
                }
            }
            if (!($field = $this->Table->getVisibleFields('web')[$field['name']] ?? null)) {
                throw new errorException($this->translate('Field [[%s]] is not found.', $field['title']));
            }

            $Field = Field::init($field, $this->Table);
            $result = $Field->getPanelFormat($item,
                $tbl);
        }
        return ['panelFormats' => $result];
    }

    public function saveLinkToEdit()
    {
        /** @var TmpTables $model */
        $model = $this->Totum->getNamedModel(TmpTables::class);

        if (!empty($this->post['shash']) && ($data = $model->getByHash(TmpTables::SERVICE_TABLES['linktoedit'],
                $this->User,
                $this->post['shash']))) {

            $LinkedTable = $this->Totum->getTable($data['table']['name'], $data['table']['extra'] ?? null);
            $LinkedTable->setWithALogTrue('linkToEdit');

            $fieldName = $data['table']['field'];
            $fieldData = $LinkedTable->getFields()[$fieldName];

            if (!empty($this->post['search'])) {

                if (!empty($data['table']['id'])) {
                    $LinkedTable->loadFilteredRows('inner', [$data['table']['id']]);
                    $item = $LinkedTable->getTbl()['rows'][$data['table']['id']];
                } else {
                    $item = $LinkedTable->getTbl()['params'];
                }

                if (!empty($this->post['search']['comment'])) {
                    if ($this->post['search']['comment'] === 'getValues') {
                        return ['value' => Field::init($fieldData,
                            $LinkedTable)->getFullValue($item[$fieldName]['v'] ?? [],
                            $item['id'] ?? null)];
                    }
                } else {
                    foreach ($item as $k => &$v) {
                        if (is_array($v)) {
                            $v = $v['v'];
                        }
                    }
                    unset($v);

                    if ($this->post['search']['checkedVals'] ?? false) {
                        $item[$fieldName] = $this->post['search']['checkedVals'];
                    }

                    return $this->getEditSelectFromTable(['field' => $fieldName, 'item' => $item],
                        $LinkedTable,
                        'inner',
                        [],
                        ($this->post['search']['q'] ?? ''),
                        ($this->post['search']['parentId'] ?? null));
                }

            } else {

                $value = $this->post['data'];

                if (is_string($value) && Field::isFieldListValues($fieldData['type'],
                        $fieldData['multiple'] ?? false)) {
                    $val = json_decode($value, true);
                    if (!json_last_error()) {
                        $value = $val;
                    }
                }

                $item = [];
                if ($data['table']['id'] ?? false) {
                    $item[$data['table']['id']] = [$fieldName => $value];
                } else {
                    $item['params'] = [$fieldName => $value];
                }


                $modifyData = [
                    match ($this->post['special'] ?? false) {
                        'setValuesToDefaults', 'setValuesToPinned' => $this->post['special'],
                        default => 'modify'
                    } => $item
                ];

                $LinkedTable->reCalculateFromOvers($modifyData);
            }

        } else {
            throw new errorException($this->translate('Temporary table storage time has expired'));
        }

        return ['ok' => 1];
    }

    public function getValue()
    {
        $data = json_decode($this->post['data'], true) ?? [];

        if (empty($data['fieldName'])) {
            throw new errorException($this->translate('The name of the field is not set.'));
        }
        if (empty($field = ($this->Table->getVisibleFields('web')[$data['fieldName']] ?? null))) {
            throw new errorException($this->translate('Access to the field is denied'));
        }
        if (empty($data['rowId']) && $field['category'] === 'column') {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'rowId'));
        }

        if (!empty($data['rowId'])) {
            $loadFilteredRows = $this->Table->loadFilteredRows('web', [$data['rowId']]);
            if ($loadFilteredRows && $row = ($this->Table->getTbl()['rows'][$data['rowId']] ?? null)) {
                $val = $row[$field['name']];
            } else {
                throw new errorException($this->translate('The row %s does not exist or is not available for your role.'));
            }
        } else {
            $row = $this->Table->getTbl()['params'];
            $val = $row[$field['name']] ?? null;
        }

        if (is_string($val)) {
            $val = json_decode($val, true);
        }

        return ['value' => Field::init($field, $this->Table)->getFullValue($val['v'], $data['rowId'] ?? null)];
    }

    /**
     * Нажатие кнопки в linktodatajson
     *
     * @throws errorException
     */
    public function linkJsonClick()
    {
        if (empty($this->post['hash']) || !is_string($this->post['hash'])) {
            throw new errorException($this->translate('Interface Error'));
        }

        /** @var TmpTables $model */
        $model = $this->Totum->getNamedModel(TmpTables::class);
        $data = $model->getByHash(TmpTables::SERVICE_TABLES['linktodatajson'], $this->User, $this->post['hash'], true);
        if (!$data) {
            throw new errorException($this->translate('Temporary table storage time has expired'));
        }
        list($Table, $row) = $this->loadEnvirement($data);


        $vars = $data['var'];
        $vars['value'] = json_decode($this->post['json'], true);

        $CA = new CalculateAction($data['code']);
        $CA->execAction(
            'CODE FROM JSON LINK',
            [],
            $row,
            $Table->getTbl(),
            $Table->getTbl(),
            $Table,
            'exec',
            $vars
        );
        $model->deleteByHash(TmpTables::SERVICE_TABLES['linktodatajson'], $this->User, $this->post['hash']);
        return ['ok' => 1];

    }

    /**
     * Нажатие кнопки на панели кнопок
     *
     * @throws errorException
     */
    public function linkButtonsClick()
    {
        $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

        $key = ['table_name' => '_linkToButtons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];

        if ($data = $model->getField('tbl', $key)) {
            $data = json_decode($data, true);
            if (key_exists('index', $this->post) && $data['buttons'][$this->post['index']] ?? null) {
                list($Table, $row) = $this->loadEnvirement($data);


                if ($Table->getFields()[$data['buttons'][$this->post['index']]['code']] ?? false) {
                    $data['buttons'][$this->post['index']]['code'] = $Table->getFields()[$data['buttons'][$this->post['index']]['code']]['codeAction'] ?? '';
                }
                $CA = new CalculateAction($data['buttons'][$this->post['index']]['code']);
                $CA->execAction(
                    'CODE FROM BUTTONS LINK',
                    [],
                    $row,
                    $Table->getTbl(),
                    $Table->getTbl(),
                    $Table,
                    'exec',
                    $data['buttons'][$this->post['index']]['vars'] ?? []
                );
                return ["ok" => 1];
            } else {
                throw new errorException($this->translate('Interface Error'));
            }
        } else {
            throw new errorException($this->translate('The choice is outdated.'));
        }
    }

    /**
     * Удаление кнопок панели из временных таблиц
     *
     * @throws \Exception
     */
    public function panelButtonsClear()
    {
        $model = $this->Totum->getModel('_tmp_tables', true);
        $key = ['table_name' => '_panelbuttons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];
        $model->delete($key);
        return ['ok' => 1];
    }

    /**
     * Клик по кнопке в панельке поля
     *
     * @throws errorException
     */
    public function panelButtonsClick()
    {
        $model = $this->Totum->getModel('_tmp_tables', true);
        $key = ['table_name' => '_panelbuttons', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];
        if ($data = $model->getField('tbl', $key)) {
            $data = json_decode($data, true);
            foreach ($data as $row) {
                if ($row['ind'] === ($this->post['index'] ?? null)) {
                    if (is_string($row['code']) && key_exists($row['code'], $this->Table->getFields())) {
                        $row['code'] = $this->Table->getFields()[$row['code']]['codeAction'];
                    }
                    $CA = new CalculateAction($row['code']);
                    if ($row['id']) {
                        $this->Table->checkIsUserCanViewIds('web', [$row['id']]);
                        $item = $this->Table->getTbl()['rows'][$row['id']];
                    } else {
                        $item = $this->Table->getTbl()['params'];
                    }

                    $CA->execAction(
                        $row['field'],
                        [],
                        $item,
                        [],
                        $this->Table->getTbl(),
                        $this->Table,
                        'exec',
                        $row['vars'] ?? []
                    );
                    break;
                }
            }
        } else {
            throw new errorException($this->translate('The choice is outdated.'));
        }
        return ['ok' => 1];
    }

    public function filesUpload()
    {
        $model = $this->Totum->getModel('_tmp_tables', true);
        $key = ['table_name' => '_linkToFileUpload', 'user_id' => $this->User->getId(), 'hash' => $this->post['hash'] ?? null];
        if ($data = $model->getField('tbl', $key)) {
            $data = json_decode($data, true);
            $files = json_decode($this->post['files'] ?? '[]', true);
            foreach ($files as &$file) {
                $file['filestring'] = base64_decode($file['base64']);
                unset($file['base64']);
            }
            list($Table, $row) = $this->loadEnvirement($data);

            $vars = $data['vars'] ?? [];
            $vars['input'] = $files;

            $CA = new CalculateAction($data['code']);
            $CA->execAction(
                'CODE UPLOAD FILES',
                [],
                $row,
                $Table->getTbl(),
                $Table->getTbl(),
                $Table,
                'exec',
                $vars
            );
            $model->delete($key);
        } else {
            throw new errorException($this->translate('The proposed input is outdated.'));
        }
        return ['ok' => 1];
    }

    public function loadPage()
    {
        if (key_exists('offset', $this->post) && !is_null($this->post['offset']) && $this->post['offset'] !== '') {
            $lastId = ['offset' => $this->post['offset']];
        } else {
            $lastId = $this->post['lastId'] ?? 0;
        }
        $prevLastId = (int)($this->post['prevLastId'] ?? 0);
        $onPage = $this->post['pageCount'] ?? 0;
        $this->Table->reCalculateFilters(
            'web',
            true,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );
        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }

        $data = $this->Table->getSortedFilteredRows('web', 'web', [], $lastId, $prevLastId, $onPage);

        $rowIds = array_column($data['rows'], 'id');
        $data['f'] = $this->getTableFormat($rowIds);

        if ($this->post['recFormats'] ?? false) {
            $data['params'] = $this->addValuesAndFormatsOfParams($this->Table->getTbl()['params'], $rowIds)['params'];
        }

        return $data;
    }

    public function loadTreeRows()
    {
        $this->Table->reCalculateFilters(
            'web',
            true,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }
        $onPage = $this->post['onPage'] ?? 0;
        $params = $this->Table->filtersParamsForLoadRows('web');
        $data['rows'] = [];
        $data['treeCounts'] = [];
        foreach (json_decode($this->post['branches'], true) as $branch) {
            $offset = $onPage * ($branch['p']);
            $treeIds = $params === false ? [] : $this->Table->getByParams(
                [
                    'where' => [...$params, ['field' => 'tree', 'value' => $branch['v'], 'operator' => '=']],
                    'field' => 'id',
                    'offset' => $offset,
                    'limit' => $onPage,
                    'order' => $this->Table->orderParamsForLoadRows()
                ],
                'list'
            );

            $data['treeCounts'][$branch['v']] = $this->Table->countByParams([...$params, ['field' => 'tree', 'value' => $branch['v'], 'operator' => '=']]);
            if ($treeIds) {
                foreach ($this->Table->getVisibleFields('web', true)['column'] as $f) {
                    if ($f['type'] === 'select') {
                        Field::init($f, $this->Table)->emptyCommonSelectViewList();
                    }
                }
                $rows = $this->Table->getSortedFilteredRows('web', 'web', $treeIds)['rows'];
                $data['rows'] = array_merge($data['rows'], $rows);
            }
        }
        $data['order'] = array_keys($this->Table->getTbl()['rows']);

        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS ROWS']);
        $this->Table->getValuesAndFormatsForClient($data, 'web', array_keys($data['rows']));
        $this->Table->calcLog($Log, 'result', 'done');
        $data['f'] = $this->getTableFormat(array_column($data['rows'], 'id'));

        return $data;
    }


    public function loadPreviewHtml()
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        $data = json_decode($this->post['data'], true);

        $fields = $this->Table->getFields();

        if (!($field = $this->Table->getVisibleFields('web')[$data['field']] ?? null)) {
            throw new errorException($this->translate('Field [[%s]] is not found.', $data['field']));
        }

        if (!in_array($field['type'], ['select'])) {
            throw new errorException($this->translate('Field not of type select/tree'));
        }

        $this->Table->loadDataRow();
        $row = $data['item'];

        if ($field['category'] === 'column') {
            if (!isset($row['id'])) {
                $row['id'] = null;
            } else {
                /*Проверка не заблокирована ли строка для пользователя*/
                $this->Table->checkIsUserCanViewIds('web', [$row['id']]);
            }
        }

        foreach ($row as $k => &$v) {
            if (key_exists($k, $fields)) {
                if ($fields[$k]['type'] === 'date' && $v && $v = Calculate::getDateObject($v,
                        $this->Totum->getLangObj())) {
                    if (!empty($fields[$k]['dateTime'])) {
                        $v = $v->format('Y-m-d H:i');
                    } else {
                        $v = $v->format('Y-m-d');
                    }
                }
                $v = ['v' => $v];
            }
        }
        /** @var Select $Field */
        $Field = Field::init($field, $this->Table);

        return ['previews' => $Field->getPreviewHtml(['v' => $data['val']], $row, $this->Table->getTbl())];
    }

    public function refresh()
    {
        $result = [];
        $filterParams = ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')];

        if ($this->post['recalculate'] ?? false) {
            try {
                $inVars = ['calculate' => aTable::CALC_INTERVAL_TYPES['changed'], 'channel' => 'web',
                    'modify' => $filterParams];
                $this->Totum->transactionStart();
                $this->Table->reCalculateFromOvers($inVars);
                $this->Table->reCalculateFilters('web', false, $filterParams);
                $this->Totum->transactionCommit();
            } catch (errorException $e) {
                $error = $e->getMessage();
                if ($this->Totum->getUser()->isCreator()) {
                    $error .= ' <br/> ' . $e->getPathMess();
                    $result['error'] = $error;
                }
                errorException::criticalException($e, $this->Totum);
            }
        } else {
            $this->Table->reCalculateFilters(
                'web',
                false,
                false,
                $filterParams
            );
        }


        switch ($pageViewType = $this->getPageViewType()) {
            case 'tree':
                $result += ['chdata' => []];

                if (!is_array($this->post) || !key_exists('tree', $this->post)) {
                    throw new errorException($this->translate('The tree index is not passed'));
                }
                $treeIndex = json_decode($this->post['tree'], true);
                $result['chdata'] = array_merge(
                    $result['chdata'],
                    $this->getResultTree(
                        function ($k, $v) use ($treeIndex) {
                            if (key_exists($k, $treeIndex)) {
                                if ($treeIndex[$k]) {
                                    return 'loaded';
                                } else {
                                    return 'child';
                                }
                            }
                        },
                        ['']
                    )
                );

                if ($this->isPagingView('tree') && $ids = json_decode($this->post['ids'], true)) {
                    $result['chdata']['rows'] = $this->Table->getSortedFilteredRows('web', 'web', $ids)['rows'];
                }

                $pageIds = array_column($result['chdata']['rows'] ?? [], 'id');
                $result['chdata']['params'] = $this->addValuesAndFormatsOfParams($this->Table->getTbl()['params'],
                    $pageIds)['params'];
                $result['chdata']['f'] = $this->getTableFormat($pageIds);
                break;
            default:

                $result += ['chdata' => $this->getTableClientData(
                    match ($this->post['withoutIds'] ?? null) {
                        null => json_decode($this->post['ids'], true),
                        'page' => ['offset' => $this->post['offset'] ?? 0],
                        default => (int)$this->post['withoutIds']
                    },
                    $this->post['onPage'] ?? null,
                    false
                )];

                switch ($pageViewType) {
                    case 'panels':
                        if ($this->Table->getTableRow()['with_order_field']) {
                            $result['chdata']['nsorted_ids'] = array_column($result['chdata']['rows'], 'id');
                        }
                        break;
                    default:
                        if ($this->isPagingView()) {
                            $params = $this->Table->filtersParamsForLoadRows('web');
                            $result['allCount'] = $params === false ? 0 : $this->Table->countByParams($params);
                        }
                        break;
                }
        }


        $result['updated'] = $this->Table->getSavedUpdated();
        $result['refresh'] = true;

        if (($this->post['getList'] ?? false) !== 'true') {
            $result['chdata']['rows'] = array_combine(
                array_column($result['chdata']['rows'], 'id'),
                $result['chdata']['rows']
            );
        }
        if ($this->Table->getTableRow()['new_row_in_sort'] || $this->Table->getChangeIds()['reordered']) {
            $result['chdata']['order'] = array_column($result['chdata']['rows'], 'id');
        }
        return $result;
    }

    public function panelsViewCookie()
    {
        $switcher = $this->post['switcher'];
        list($name, $path) = $this->getPanelsCookieName();
        setcookie(
            $name,
            $switcher ? 1 : 0,
            [
                'path' => $path,
                'httponly' => true
            ]
        );
        return ['ok' => 1];
    }

    protected function getEditSelectFromTable(array $data, aTable $Table, $channel, $filters, $q = '', $parentId = null): array
    {
        $fields = $Table->getFields();

        if (!($field = $fields[$data['field']] ?? null)) {
            throw new errorException($this->translate('Field [[%s]] is not found.', $data['field']));
        }

        $Table->loadDataRow();
        $Table->reCalculateFilters(
            $channel,
            false,
            false,
            ['params' => $filters]
        );

        $row = $data['item'];

        if ($field['category'] === 'column' && !isset($row['id'])) {
            $row['id'] = null;
        }
        foreach ($row as $k => &$v) {
            if ($k !== 'id') {
                $v = ['v' => $v];
            }
        }
        $cleareRow = function ($row, $isInsert = false) {
            $resultRow = [];
            foreach ($row as $k => $value) {
                try {
                    if (Model::isServiceField($k) || $this->Table->isField($isInsert ? 'insertable' : 'editable',
                            'web',
                            $k)) {
                        $resultRow[$k] = $value;
                    }
                } catch (\Exception) {
                }
            }
            return $resultRow;
        };


        if ($field['category'] === 'column') {
            if (array_key_exists('id', $row) && !is_null($row['id'])) {
                $row = !empty($data['hash']) ? $this->getEditRow($data['hash'],
                    [],
                    []) : $Table->checkEditRow(['id' => $row['id']]);
            } else {
                $row = $Table->checkInsertRow([], $data['item'], $data['hash'] ?? null, []);
            }
        } else {
            if ($field['category'] !== 'filter') {
                $row = [];
            } else {
                $row = $cleareRow($row);
            }
            $row = $row + $Table->getTbl()['params'];
        }

        if (!in_array(
            $field['type'],
            ['select', 'tree']
        )) {
            throw new errorException($this->translate('Field not of type select/tree'));
        }

        /** @var Select $Field */
        $Field = Field::init($field, $Table);

        $list = $Field->calculateSelectList($row[$field['name']], $row, $Table->getTbl());

        return $Field->cropSelectListForWeb($list, $row[$field['name']]['v'], $q, $parentId);
    }

    protected function getInsertRow($hash, $addData = [], $tableData = [], $clearField = null)
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        $visibleFields = $this->Table->getVisibleFields('web', true);


        $columnFilter = [];
        foreach ($this->Table->getSortedFields()['filter'] as $k => $f) {
            if (($f['showInWeb'] ?? false) && ($f['column'] ?? false)) {
                $columnFilter[$f['column']] = $k;
            }
        }
        foreach ($visibleFields['column'] as $v) {
            $filtered = null;
            if (key_exists($v['name'], $columnFilter) && empty($v['code'])) {
                $val = $this->Table->getTbl()['params'][$columnFilter[$v['name']]]['v'];

                if (isset($columnFilter[$v['name']])
                    && $val !== '*ALL*'
                    && $val !== ['*ALL*']
                    && $val !== '*NONE*'
                    && $val !== ['*NONE*']
                ) {
                    $filtered = $val ?? null;
                }
                if (!empty($filtered)) {
                    $filtersData[$v['name']] = $filtered;
                }
            }
        }

        return $this->Table->checkInsertRow(
            $tableData,
            $addData,
            $hash,
            [],
            $clearField,
            $filtersData ?? []
        );
    }

    protected function getPanelsCookieName()
    {
        if ($this->Table->getTableRow()['type'] === 'calcs') {
            $cookieName = 'panelSwitcher' . $this->Table->getTableRow()['id'];
            $path = preg_replace('/\d+\/\d+\?.*$/', '', $_SERVER['REQUEST_URI']);
        } else {
            $path = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);
            $cookieName = 'panelSwitcher';
        }
        return [$cookieName, $path];
    }

    public function printTable()
    {
        $template = $this->Totum->getModel('print_templates')->executePrepared(
            true,
            ['name' => 'main'],
            'styles, html'
        )->fetch();

        $template = $template ?? ['styles' => '@import url("https://fonts.googleapis.com/css?family=Open+Sans:400,600|Roboto:400,400i,700,700i,500|Roboto+Mono:400,700&amp;subset=cyrillic");
body { font-family: \'Roboto\', sans-serif;}
table{ border-spacing: 0; border-collapse: collapse; margin-top: 20px;table-layout: fixed; width: 100%}
table tr td{ border: 1px solid gray; padding: 3px; overflow: hidden;text-overflow: ellipsis}
table tr td.title{font-weight: bold}', 'html' => '{table}'];


        $settings = json_decode($this->post['settings'], true);


        $sosiskaMaxWidth = $settings['sosiskaMaxWidth'];
        $fields = array_intersect_key($this->Table->getFields(), $settings['fields']);

        $fieldNames = array_keys($fields);
        $ids = $this->Table->loadFilteredRows('web', $settings['ids'] ?? []);
        $title = $this->Table->getTableRow()['title'];
        $tableFormat = $this->getTableFormat($ids);
        if ($tableFormat['tabletitle'] ?? false) {
            $title = $tableFormat['tabletitle'];
        }

        $tableAll = ['<h1>' . htmlspecialchars($title) . '</h1>'];

        $data = ['params' => $this->Table->getTbl()['params'], 'rows' => []];
        foreach ($settings['ids'] ?? [] as $id) {
            if (in_array($id, $ids)) {
                $data['rows'][$id] = $this->Table->getTbl()['rows'][$id];
            }
        }
        $result = $this->Table->getValuesAndFormatsForClient($data, 'print', array_keys($data['rows']), $fieldNames);


        $getTdTitle = function ($field, $withWidth = true, $width = null) {
            $title = htmlspecialchars($field['title']);
            if (!empty($field['unitType'])) {
                $title .= ', ' . $field['unitType'];
            }

            return '<td'
                . ($withWidth ? ' style="width: ' . ($field['width'] ?? $width) . 'px;"' : '')
                . ' class="title">' . $title . '</td>';
        };


        foreach (['param', 'filter'] as $category) {
            $table = [];
            $width = 0;

            foreach ($fields as $field) {
                if ($field['category'] === $category) {
                    if (!$table || $field['tableBreakBefore'] || $width > $sosiskaMaxWidth) {
                        $width = $settings['fields'][$field['name']];
                        if ($table) {
                            $tableAll[] = $table[0] . $width . $table[1] . implode(
                                    '',
                                    $table['head']
                                ) . $table[2] . implode(
                                    '',
                                    $table['body']
                                ) . $table[3];
                        }
                        $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];
                    } else {
                        $width += $settings['fields'][$field['name']];
                    }

                    $table['head'][] = $getTdTitle($field);
                    $table['body'][] = '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $result['params'][$field['name']]['v'] . '</span></td>';
                }
            }
            if ($table) {
                $tableAll[] = $table[0] . $width . $table[1] . implode(
                        '',
                        $table['head']
                    ) . $table[2] . implode(
                        '',
                        $table['body']
                    ) . $table[3];
            }
        }


        /*Строчная часть*/
        if ($settings['rotated'] ?? null) {

            $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];
            $table['head'][] = '<td style="width: 120px;" class="title"></td>';
            $width = 120;

            foreach ($fields as $field) {
                if ($field['category'] === 'column') {
                    $table['body'][$field['name']][] = $getTdTitle($field);
                } elseif ($field['category'] === 'footer') {
                    $field['column'] = '';
                }
            }
            foreach ($result['rows'] as $id => $row) {
                $title = '';
                if ($this->Table->getTableRow()['main_field']) {
                    $mainField = $this->Table->getFields()[$this->Table->getTableRow()['main_field']];
                    switch ($mainField['type']) {
                        case 'date':
                            if ($row[$mainField['name']]['v']) {
                                $title = $this->Totum->getLangObj()->dateFormat(date_create($row[$mainField['name']]['v']),
                                    $mainField['dateFormat']);
                            }
                            break;
                        default:
                            $title = $row[$mainField['name']]['v'];
                    }
                }
                if (array_key_exists('id', $settings['fields']) && (empty($title) || strip_tags($title) === '')) {
                    $title = $id;
                }


                $table['head'][] = '<td style="width: ' . $settings['rotated'] . 'px;" class="title">' . $title . '</td>';
                foreach ($fields as $field) {
                    if ($field['category'] === 'column') {
                        $table['body'][$field['name']][] = '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $row[$field['name']]['v'] . '</span></td>';
                    }
                }
            }

            foreach ($table['body'] as &$row) {
                $row = '<tr>' . implode('', $row) . '</tr>';
            }
            unset($row);

            $tableAll[] = $table[0] . $width . $table[1] . implode(
                    '',
                    $table['head']
                ) . $table[2] . implode(
                    '',
                    $table['body']
                ) . $table[3];


        } else {
            $table = [];
            $width = 0;
            foreach ($fields as $field) {
                if ($field['category'] === 'column') {
                    if (!$table) {
                        $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];
                        if (array_key_exists('id', $settings['fields'])) {
                            $table['head'][] = '<td style="width: ' . $settings['fields']['id'] . 'px;" class="title">id</td>';
                            $width += $settings['fields']['id'];
                        }
                    }
                    $table['head'][] = $getTdTitle($field);
                    $width += $settings['fields'][$field['name']];
                }
            }
            if ($table) {
                foreach ($result['rows'] as $id => $row) {
                    $tr = '<tr>';
                    if (array_key_exists('id', $settings['fields'])) {
                        $tr .= '<td class="f-id"><span>' . $id . '</span></td>';
                    }
                    foreach ($fields as $field) {
                        if ($field['category'] === 'column') {
                            $tr .= '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . (is_array($row[$field['name']]['v']) ? json_encode($row[$field['name']]['v'],
                                    JSON_UNESCAPED_UNICODE) : $row[$field['name']]['v']) . '</span></td>';
                        }
                    }
                    $tr .= '</tr>';
                    $table['body'][] = $tr;
                }


                if ($columnFooters = array_filter(
                    $fields,
                    function ($field) use ($fields) {
                        if ($field['category'] === 'footer' && !empty($field['column']) && array_key_exists(
                                $field['column'],
                                $fields
                            )) {
                            return true;
                        }
                    }
                )) {
                    while ($columnFooters) {
                        $tr_names = '<tr>';
                        $tr_values = '<tr>';
                        foreach ($fields as $field) {
                            if ($field['category'] === 'column') {
                                $column = $field['name'];

                                if ($thisColumnFooters = array_filter(
                                    $columnFooters,
                                    function ($field) use ($column) {
                                        if ($field['column'] === $column) {
                                            return true;
                                        }
                                    }
                                )) {
                                    $name = array_keys($thisColumnFooters)[0];
                                    $thisColumnFooter = $columnFooters[$name];

                                    $tr_names .= $getTdTitle($thisColumnFooter, false);
                                    $tr_values .= '<td class="f-' . $thisColumnFooter['type'] . ' n-' . $thisColumnFooter['name'] . '">' . $result['params'][$thisColumnFooter['name']]['v'] . '</td>';

                                    unset($columnFooters[$name]);
                                } else {
                                    $tr_names .= '<td></td>';
                                    $tr_values .= '<td></td>';
                                }
                            }
                        }
                        $tr_names .= '</tr>';
                        $tr_values .= '</tr>';
                        $table['body'][] = $tr_names;
                        $table['body'][] = $tr_values;
                        unset($tr_names);
                        unset($tr_values);
                    }
                }

                $tableAll[] = $table[0] . $width . $table[1] . implode(
                        '',
                        $table['head']
                    ) . $table[2] . implode(
                        '',
                        $table['body']
                    ) . $table[3];
            }
        }
        /*/строчная часть*/


        $table = [];
        $width = 0;


        /*Общие футеры*/
        foreach ($fields as $field) {
            if ($field['category'] === 'footer' && empty($field['column'])) {
                if (!$table || $field['tableBreakBefore'] || $width > $sosiskaMaxWidth) {
                    if ($table) {
                        $tableAll[] = $table[0] . $width . $table[1] . implode(
                                '',
                                $table['head']
                            ) . $table[2] . implode(
                                '',
                                $table['body']
                            ) . $table[3];
                    }

                    $width = $settings['fields'][$field['name']];
                    $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];
                } else {
                    $width += $settings['fields'][$field['name']];
                }

                $table['head'][] = $getTdTitle($field);
                $table['body'][] = '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $result['params'][$field['name']]['v'] . '</span></td>';
            }
        }
        if ($table) {
            $tableAll[] = $table[0] . $width . $table[1] . implode(
                    '',
                    $table['head']
                ) . $table[2] . implode(
                    '',
                    $table['body']
                ) . $table[3];
        }

        $style = $template['styles'];
        $body = str_replace(
            '{table}',
            '<div class="table-' . $this->Table->getTableRow()['name'] . '">' . implode('', $tableAll) . '</div>',
            $template['html']
        );

        if ($settings['pdf'] ?? false) {
            if (!$this->isTableWithPDF()) {
                throw new errorException($this->translate('PDF printing for this table is switched off'));
            }
            $data = [
                'type' => 'html',
                'file' => base64_encode(File::replaceImageSrcsWithEmbedded($this->Table->getTotum()->getConfig(),
                    '<html><head><style>' . $style . '</style></head><body>' . $body . '</body></html>')),
                'pdf' => $settings['pdf']
            ];
            $file = ServicesConnector::init($this->Totum->getConfig())->serviceRequestFile('pdf', $data);
            $this->Table->getTotum()->addToInterfaceDatas('files',
                ['files' => [
                    ['name' => 'table.pdf', 'type' => 'application/pdf', 'string' => base64_encode($file)]
                ]
                ]
            );
            return;
        }

        $this->Totum->addToInterfaceDatas(
            'print',
            [
                'styles' => $style,
                'body' => $body
            ]
        );
    }

    protected function deCryptFilters($filtersIn)
    {
        $filters = [];
        if ($filtersIn) {
            if (in_array($this->Table->getTableRow()['id'], [1, 2]) && is_array($filtersIn)) {
                $filters = $filtersIn;
            } elseif ($filtersDecrypt = Crypt::getDeCrypted(strval($filtersIn))) {
                $filters = json_decode($filtersDecrypt, true);
            }
        }
        return $filters;
    }

    public function getFullTableData($withRecalculate = true)
    {
        $addFilters = $this->getPermittedFilters($this->Request->getQueryParams()['f'] ?? '');

        if ($withRecalculate) {
            try {
                $inVars = ['calculate' => aTable::CALC_INTERVAL_TYPES['changed'], 'channel' => 'web', 'addFilters' => $addFilters];

                $this->Totum->transactionStart();
                $this->Table->reCalculateFromOvers($inVars);
                $this->Table->reCalculateFilters('web', false, $addFilters);
                $this->Totum->transactionCommit();

            } catch (errorException $e) {
                $error = $e->getMessage();
                if ($this->Totum->getUser()->isCreator()) {
                    $error .= ' <br/> ' . $e->getPathMess();
                }
                $this->Totum->transactionRollback();
                $Conf = $this->Totum->getConfig()->getClearConf();
                $this->Totum = new Totum($Conf, $this->User);
                $this->Table->setNewTotum($this->Totum);
                throw new criticalErrorException($error);
            }
        } else {
            $this->Table->reCalculateFilters('web', true, $addFilters);
        }

        $result = $this->getTableClientForm();
        if (!empty($error)) {
            $result['error'] = $error;
        }

        switch ($this->getPageViewType()) {
            case 'panels':
                $result['viewType'] = 'panels';
                $result['kanban'] = $this->getKanbanData($fields);

                foreach ($result['fields'] as $k => $v) {
                    if ($v['category'] === 'column' && !in_array($k, $fields)) {
                        unset($result['fields'][$k]);
                    }
                }

                $result = array_merge(
                    $result,
                    $this->getTableClientData(
                        0,
                        $this->isPagingView() ? 0 : null,
                        false,
                        $fields
                    )
                );
                break;
            case 'tree':
                $tree = $this->Table->getFields()['tree'];
                $result = array_merge(
                    $result,
                    $this->getTreeTopLevel($tree['treeViewLoad'] ?? null,
                        $tree['treeViewOpen'] ?? null)
                );
                break;
            case 'commonByCount':
                /*For off button on table head*/
                $result['panels'] = 'off';
            // no break
            default:
                $result = array_merge($result, $this->getTableClientData(0, $this->isPagingView() ? 0 : null, false));

        }
        if ($result['rows'] && ($result['f']['order'] ?? false)) {
            $rows = [];
            $rows_other = [];
            foreach ($result['rows'] as $row) {
                $k = array_search($row['id'], $result['f']['order']);
                if ($k !== false) {
                    $rows[$k] = $row;
                } else {
                    $rows_other[] = $row;
                }
            }
            ksort($rows);
            $rows = array_values($rows);
            $result['rows'] = array_merge($rows, $rows_other);
            unset($result['f']['order']);
        }

        if ($this->isPagingView() && $this->Totum->getMessenger()->isFormatUseRows()) {
            $result['formatUseRows'] = true;
        }
        if ($this->Totum->getConfig()->getSettings('h_hide_teh_plate')) {
            $result['hide_teh_plate'] = true;
        }

        return $result;
    }

    protected function getPageViewType(): string
    {
        if (($this->post['restoreView'] ?? false) || ($this->User->isCreator() && ($this->Cookies['ttm__commonTableView'] ?? false))) {
            $this->creatorCommonView = true;
            return 'common';
        }

        if (($tree = $this->Table->getFields()['tree'] ?? null)
            && $tree['category'] === 'column'
            && $tree['type'] === 'tree'
            && !empty($tree['treeViewType'])) {
            return 'tree';
        }

        if ($this->Request->getQueryParams()['iframe'] ?? false) ; elseif (($panelViewSettings = ($this->Table->getTableRow()['panels_view'] ?? null))
        ) {
            if (($this->post['panelsView'] ?? false) === 'true') {
                return 'panels';
            } elseif (empty($this->post)) {
                $params = $this->Table->filtersParamsForLoadRows('web');
                $allCount = $params === false ? 0 : $this->Table->countByParams($params);
                if ($allCount <= ($panelViewSettings['panels_max_count'] ?? 100)) {
                    $checkCookies = function () use ($panelViewSettings) {
                        $name = $this->getPanelsCookieName()[0];
                        if (key_exists($name, $_COOKIE)) {
                            return $_COOKIE[$name] === '1';
                        }
                        return $panelViewSettings['panels_view_first'];
                    };

                    if ($panelViewSettings['state'] === 'panel'
                        || (
                            $panelViewSettings['state'] === 'both'
                            && $checkCookies()
                        )) {
                        return 'panels';
                    }
                } else {
                    return 'commonByCount';
                }
            }
        }

        return 'common';
    }

    protected function isPagingView($type = null): bool
    {
        if (($this->Table->getTableRow()['pagination'] ?? '0/0') === '0/0') {
            return false;
        }
        if ($type === 'tree') {
            return $this->getPageViewType() === 'tree' && $this->Table->getFields()['tree']['treeViewType'] === 'other';
        }
        return true;
    }

    protected function addValuesAndFormatsOfParams($params, array $rowIds)
    {
        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS OF OTHER NON-ROWS PARTS']);
        {
            $params = $this->Table->getValuesAndFormatsForClient(['params' => $params], 'web', $rowIds);
        }
        $this->Table->calcLog($Log, 'result', 'done');

        return $params;
    }

    protected function getTableClientData($pageIds = 0, $onPage = null, $calcFilters = true, $onlyFields = null)
    {
        if ($calcFilters) {
            $this->Table->reCalculateFilters(
                'web',
                false,
                false,
                ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
            );
        }
        $data = [];

        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }

        if (is_null($onPage)) {
            $data = $this->Table->getSortedFilteredRows(
                'web',
                'web',
                [],
                null,
                null,
                null,
                $onlyFields
            );
        } elseif ($onPage > 0) {
            $data = $this->Table->getSortedFilteredRows(
                'web',
                'web',
                [],
                lastId: is_array($pageIds) || $pageIds >= 0 ? $pageIds : 0,
                prevLastId: $pageIds < 0 ? $pageIds : 0,
                onPage: $onPage,
                onlyFields: $onlyFields
            );
        }

        $pageIds = array_column($data['rows'] ?? [], 'id');
        $result = $this->addValuesAndFormatsOfParams($this->Table->getTbl()['params'], $pageIds);
        $result['f'] = $this->getTableFormat($pageIds);
        $result['rows'] = $data['rows'] ?? [];
        $result['offset'] = $data['offset'] ?? null;

        $result['filtersString'] = $this->getFiltersString();
        return $result;
    }

    protected function getFiltersString()
    {
        $_filters = [];
        $fields = $this->Table->getSortedFields();
        foreach ($fields['filter'] as $k => $field) {
            if (!empty($field['showInWeb']) && $this->Table->isField('editable', 'web', $field)) {
                $val = $this->Table->getTbl()['params'][$k];
                if (key_exists('h', $val)
                    || key_exists('c', $val) || !key_exists('code', $field) || ($field['codeOnlyInAdd'] ?? false)) {
                    $_filters[$k] = $val['v'];
                }
            }
        }
        if ($_filters) {
            return Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE));
        }
    }

    public function checkTableIsChanged()
    {
        /*TODO FOR MY TEST SERVER*/
        if ($_SERVER['HTTP_HOST'] === 'localhost:8080') {
            die('test');
        }
        $this->withLog = false;

        $table_id = (int)$this->post['table_id'];
        $cycle_id = ($this->post['cycle_id'] ?? $this->post['tableData']['sess_hash'] ?? 0);

        $Table = $this->Totum->getTable($table_id, $cycle_id, true);
        $i = 0;
        do {
            if ($i > 0) {
                sleep(3);
            }
            $isChanged = $Table->getChangedString($this->post['code']);
        } while (!empty($isChanged['no']) && $i++ < 20);
        return $isChanged;
    }

    /**
     *
     * getTableDataForInterface
     *
     * @return array
     * @throws errorException
     */
    protected function getTableClientForm(): array
    {
        $result['f'] = [];
        $result['rows'] = [];
        $result['params'] = [];
        $result['type'] = $this->Table->getTableRow()['type'];

        $visibleFields = $this->Table->getVisibleFields("web");
        $result['filtersString'] = $this->getFiltersString();

        if ($this->User->isCreator()) {
            $result['ROLESLIST'] = $this->Totum->getModel('roles')->getFieldIndexedById(
                'title',
                ['is_del' => false]
            );
        } else {
            $result['ROLESLIST'] = [];
        }


        $addLinkToSelectTableSinFields = function (&$fields) {
            foreach ($fields as $f) {
                if (!in_array($f['type'], ['select', 'tree']) || $f['category'] === 'filter') {
                    continue;
                }
                if (!empty($f['selectTable'])) {
                    if ($table = $this->Totum->getTableRow($f['selectTable'])) {
                        if (array_key_exists($table['id'], $this->User->getTables())) {
                            if ($this->User->getTables()[$table['id']] === 1) {
                                $fields[$f['name']]['changeSelectTable'] = 1;
                                if ($table['insertable'] === true) {
                                    $fields[$f['name']]['changeSelectTable'] = 2;
                                }
                            } elseif (key_exists($table['id'], $this->User->getTables())) {
                                $fields[$f['name']]['viewSelectTable'] = 1;
                            }
                        }

                        $fields[$f['name']]['selectTableId'] = $table['id'];
                        $fields[$f['name']]['linkToSelectTable'] = ['link' => $this->modulePath . $table['top'] . '/' . $table['id'], 'title' => $table['title']];
                    }
                }
            }
        };
        $addLinkToSelectTableSinFields($visibleFields);

        $readOnly = !is_a($this, WriteTableActions::class);

        $result['control'] = [
            'editing' => !$readOnly,
            'adding' => !$readOnly && $this->Table->isUserCanAction('insert')
            , 'deleting' => !$readOnly && $this->Table->isUserCanAction('delete')
            , 'restoring' => !$readOnly && $this->Table->isUserCanAction('restore')
            , 'duplicating' => !$readOnly && $this->Table->isUserCanAction('duplicate')
        ];

        $result['withCsvButtons'] = $this->Table->isUserCanAction('csv');
        $result['withCsvEditButtons'] = $this->Table->isUserCanAction('csv_edit');
        $result['tableRow'] = $this->tableRowForClient($this->Table->getTableRow(), $visibleFields);


        $result['fields'] = $this->fieldsForClient($visibleFields);


        if ($this->Table->getTableRow()['type'] === 'calcs') {
            $result['tableRow']['fields_sets'] = $this->Table->changeFieldsSets();
            $result['tableRow']['cycle_id'] = $this->Table->getCycle()->getId();
        }

        $result['updated'] = $this->Table->getSavedUpdated();
        return $result;
    }

    public function viewRow()
    {
        $id = (int)($this->post['id'] ?? null);
        if (!$id) {
            throw new errorException($this->translate('ID is empty'));
        }
        $this->Table->loadDataRow();
        if ($this->Table->loadFilteredRows('web', [$id])) {
            $res['row'] = $this->Table->getValuesAndFormatsForClient(
                ['rows' => [$this->Table->getTbl()['rows'][$id]]],
                'edit', []
            )['rows'][0];
            $res['f'] = $this->getTableFormat([]);
            return $res;
        } else {
            throw new errorException($this->translate('Row not found'));
        }
    }

    public function getTableData()
    {
        $this->Table->reCalculateFilters('web');
        $table = $this->getTableClientForm();
        $table['checkIsUpdated'] = 0;
        return $table;
    }

    public function click()
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );
        $vars = [];

        if ($click = is_string($this->post['data']) ? (json_decode(
                $this->post['data'],
                true
            ) ?? []) : $this->post['data']) {
            if ($click['item'] === 'params') {
                $row = $this->Table->getTbl()['params'];
            } elseif (is_string($click['item']) && $click['item'][0] === 'i') {

                $row = $this->getInsertRow($click['item']);
                if (is_null($row)) {
                    throw new errorException($this->translate('Add row out of date'));
                }
                $this->Table->setInsertRowHash($click['item']);
                unset($v);
            } else {
                /*Проверка не заблокирована ли строка для пользователя*/
                $ids = $this->Table->loadFilteredRows('web', [$click['item']]);
                if (!$ids || !($row = $this->Table->getTbl()['rows'][$click['item']] ?? null) || !empty($row['is_del'])) {
                    throw new errorException($this->translate('Table [[%s]] was changed. Update the table to make the changes.',
                        ''));
                }
                if (!empty($click['hash'])) {
                    $vars['__edit_hash'] = $click['hash'];
                }
            }

            try {
                $fields = $this->Table->getVisibleFields('web');
                /* Проверка доступа к нажатию кнопки */
                if (!key_exists($click['fieldName'], $fields)) {
                    throw new errorException($this->translate('Access to the field is denied'));
                } elseif (get_class($this) === ReadTableActions::class && empty($fields[$click['fieldName']]['pressableOnOnlyRead'])) {
                    throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
                }

                if ($click['checked_ids'] ?? null) {
                    $vars['ids'] = function () use ($click) {
                        return $this->Table->checkIsUserCanViewIds('web', $click['checked_ids']);
                    };
                } else {
                    $vars['ids'] = [];
                }
                if ($ids = json_decode($this->post['ids'] ?? '[]', true)) {

                    $vars['rows'] = function () use ($ids) {
                        $ids = $this->Table->loadFilteredRows('web', $ids);
                        $vars['rows'] = array_intersect_key($this->Table->getTbl()['rows'],
                            array_flip($ids));
                        foreach ($vars['rows'] as &$_row) {
                            unset($_row['_E']);
                            foreach ($_row as $k => $v) {
                                $_row[$k] = match ($k) {
                                    'id' => $v,
                                    default => $v['v'] ?? null
                                };
                            }
                        }
                        unset($_row);
                        return $vars['rows'];
                    };

                } else {
                    $vars['rows'] = [];
                }

                $this->clickToButton($fields[$click['fieldName']], $row, $vars);


            } catch (\ErrorException $e) {
                throw $e;
            }
        }

        return $this->getTableClientChangedData([]);
    }

    protected function clickToButton($fieldParams, $row, $vars, $type = 'exec')
    {
        $Log = $this->Table->calcLog(['name' => 'CLICK']);
        Field::init($fieldParams, $this->Table)->action(
            $row,
            $row,
            $this->Table->getTbl(),
            $this->Table->getTbl(),
            $type,
            $vars
        );
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        $this->Table->calcLog($Log, 'result', 'done');
    }

    public function setCommentsViewed()
    {
        if ($this->User->getId()) {
            $field = $this->Table->getFields()[$this->post['field_name']] ?? null;
            if ($field && $field['type'] === 'comments') {
                /** @var Comments $Field */
                $Field = Field::init($field, $this->Table);
                $Field->setViewed($this->post['nums'], $this->post['id'] ?? null);
            }
        }
        return ['ok' => true];
    }

    public function getEditSelect($data = null, $q = null, $parentId = null)
    {
        $data = $data ?? json_decode($this->post['data'] ?? '[]', true) ?? [];
        $q = $q ?? $this->post['q'] ?? '';
        $parentId = $parentId ?? $this->post['parentId'] ?? null;
        return $this->getEditSelectFromTable($data,
            $this->Table,
            'web',
            $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? ''),
            $q,
            $parentId);
    }


    public function edit()
    {
        $data = is_string($this->post['data']) ? json_decode($this->post['data'], true) : $this->post['data'];

        $filterFields = $this->Table->getVisibleFields('web', true)['filter'] ?? [];
        $filters = array_intersect_key($data['params'] ?? [], $filterFields);

        if ($filters) {
            return $this->editFilters($filters, $data['setValuesToDefaults'] ?? false);
        } elseif (!is_a($this, WriteTableActions::class)) {
            throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
        } else {
            $clearFields = $data['params'] ?? [];
            if ($filters) {
                $clearFields = array_diff_key($clearFields, $filters);
            }
            $data['params'] = $clearFields;

            return $this->modify(['modify' => $data, 'setValuesToDefaults' => $data['setValuesToDefaults'] ?? false]);
        }
    }

    protected function editFilters($filters, $toDefaults = false)
    {
        $fields = $this->Table->getVisibleFields('web', true)['filter'];
        $fromString = $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '');
        $vars = [];
        $defs = [];

        foreach ($fromString as $k => $v) {
            if (!key_exists($k, $vars) && !in_array($k, $defs)) {
                $vars[$k] = $v;
            }
        }
        $this->Table->reCalculateFilters('web', true, false, ['params' => $vars], $defs);


        $vars = [];
        foreach ($filters as $fName => $val) {
            $field = $fields[$fName];
            if ($this->Table->isField('editFilterByUser', 'web', $field)) {
                if ($toDefaults) {
                    $defs[] = $fName;
                } else {
                    $vars[$fName] = $val;
                }
            }
        }
        foreach ($fromString as $k => $v) {
            if (!key_exists($k, $vars) && !in_array(
                    $k,
                    $defs
                )) {
                if (key_exists('h', $this->Table->getTbl()['params'][$k]) || !key_exists(
                        'code',
                        $this->Table->getFields()[$k]
                    ) || ($this->Table->getFields()[$k]['codeOnlyInAdd'] ?? false)
                ) {
                    $vars[$k] = $v;
                }
            }
        }

        $Log = $this->Table->calcLog(['name' => 'EDIT FILTERS', 'table' => $this, 'inVars' => ['setValuesToDefaults' => $defs, 'modify' => $vars]]);
        $this->Table->reCalculateFilters('web', true, false, ['params' => $vars], $defs);
        $this->Table->calcLog($Log, 'result', 'done');


        $filters = [
            'params' => []
        ];

        $params = $this->Table->getTbl()['params'];

        foreach ($this->Table->getVisibleFields('web', true)['filter'] as $fName => $sortedVisibleField) {
            $filters['params'][$fName] = $params[$fName];
        }
        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS OF FILTERS']);
        $changedData = $this->Table->getValuesAndFormatsForClient($filters, 'web', []);

        $this->Table->calcLog($Log, 'result', $changedData);

        $changedData['filtersString'] = $this->getFiltersString();

        return $changedData;
    }

    public function getFieldLog()
    {
        $field = $this->post['field'];
        $id = $this->post['id'] ?? null;
        $rowName = $this->post['rowName'] ?? null;

        $fields = $this->Table->getFields();
        if (empty($fields[$field])) {
            throw new errorException($this->translate('Function [[%s]] is not found.', $field));
        }
        if (empty($fields[$field]['showInWeb']) || (!empty($fields[$field]['logRoles']) && !array_intersect(
                    $fields[$field]['logRoles'],
                    $this->User->getRoles()
                ))) {
            throw new errorException($this->translate('Access to the logs is denied'));
        }


        $logs = $this->Totum->totumActionsLogger()->getLogs(
            $this->Table->getTableRow()['id'],
            $this->Table->getCycle() ? $this->Table->getCycle()->getId() : null,
            $id,
            $field
        );

        $title = $this->translate('Log of manual changes by field "%s"', $fields[$field]['title']);
        if ($id) {
            $title .= ' id ' . $id;
            if ($rowName) {
                $title .= ' "' . $rowName . '""';
            }
        }

        if (empty($logs)) {
            $this->Table->getTotum()->addToInterfaceDatas(
                'text',
                ['title' => $title, 'width' => '500', 'text' => $this->translate('No manual changes were made in the field')]
            );
        } else {
            $tmp = $this->Totum->getTable('log_structure');
            $tmp->addData(['tbl' => $logs]);
            $width = 130;
            foreach ($tmp->getVisibleFields('web', true)['column'] as $field) {
                $width += $field['width'];
            }
            $table = [
                'title' => $title,
                'table_id' => $tmp->getTableRow()['id'],
                'sess_hash' => $tmp->getTableRow()['sess_hash'],
                'data' => [],
                'data_params' => [],
                'width' => $width
            ];

            $this->Table->getTotum()->addToInterfaceDatas('table', $table);
        }
    }


    /**
     * @param $fields
     * @return array
     */
    protected function fieldsForClient($fields)
    {
        $anchorFilters = $this->Table->getAnchorFilters() ?? [];

        foreach ($fields as $fName => &$field) {

            if (!$this->User->isCreator() && $field['category'] === 'column' && $field['type'] === 'button' && $this->Table->getTableRow()['type'] === 'cycles' && str_starts_with($field['name'],
                    'tab_')) {
                unset($fields[$fName]);
                continue;
            }


            if (!$this->Table->isField('editable', 'web', $field)) {
                $field['editable'] = false;
            } elseif (key_exists($field['name'], $anchorFilters)) {
                $field['editable'] = false;
            }

            if (!$this->Table->isField('insertable', 'web', $field)) {
                $field['insertable'] = false;
            }


            if (key_exists('showInWebOtherName', $field)) {
                $field['column'] = $field['showInWebOtherName'];
                unset($field['showInWebOtherName']);
            }

            if (key_exists('format', $field)) {
                $panelFormatExists = false;
                foreach ($field['format'] as $k => $c) {
                    if (preg_match('/^p\d+=$/', $k)) {
                        $panelFormatExists = true;
                        break;
                    }
                }
                if ($panelFormatExists) {
                    $field['formatInPanel'] = true;
                }
                if ($this->User->isCreator()) {
                    $field['formatCode'] = true;
                }
            }

            if ($field['type'] === 'select') {
                foreach ($field['codeSelect'] ?? [] as $code) {
                    if (is_string($code) && preg_match('/(selectRowListForSelect|selectListAssoc)\([^)]*(preview|previewscode)\s*:/i',
                            $code)) {
                        $field['withPreview'] = true;
                        break;
                    }
                }
            } elseif ($field['type'] === 'number') {
                $field['dectimalSeparator'] = $field['dectimalSeparator'] ?? $this->Totum->getConfig()->getSettings('numbers_format')['dectimalSeparator'] ?? ',';
            }


            foreach (Totum::FIELD_CODE_PARAMS as $param) {
                if (!empty($field[$param])) {
                    $field[$param] = true;
                }
            }

            if (!is_a($this, WriteTableActions::class)
                && !empty($field['CodeActionOnClick'])
                && empty($field['AllowActionClickOnRead'])) {
                unset($field['CodeActionOnClick']);
            }


            if ($field['logButton'] = $field['logging'] ?? true) {
                if ($field['type'] === 'button' || $field['category'] === 'filter') {
                    $field['logButton'] = false;
                } elseif (!empty($field['logRoles']) && !array_intersect(
                        $this->User->getRoles(),
                        $field['logRoles']
                    )) {
                    $field['logButton'] = false;
                }
            }
            if (!$this->User->isCreator()) {
                foreach (Totum::FIELD_ROLES_PARAMS as $param) {
                    unset($field[$param]);
                }
                unset($field['logging']);
                unset($field['showInXml']);
                unset($field['copyOnDuplicate']);
                if (!empty($field['help'])) {
                    $field['help'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $field['help']);
                }
            }
            $field['help'] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $field['help'] ?? '');


        }
        if ($this->Table->getTableRow()['name'] === 'tables_fields') {
            if ($this->Totum->getUser()->isCreator()) {
                $fields['data_src']['jsonFields']['fieldSettings']['editRoles']['values']
                    = $fields['data_src']['jsonFields']['fieldSettings']['addRoles']['values']
                    = $fields['data_src']['jsonFields']['fieldSettings']['logRoles']['values']
                    = $fields['data_src']['jsonFields']['fieldSettings']['webRoles']['values']
                    = $fields['data_src']['jsonFields']['fieldSettings']['xmlRoles']['values']
                    = $fields['data_src']['jsonFields']['fieldSettings']['xmlEditRoles']['values']
                    = $this->Totum->getModel('roles')->getFieldIndexedById(
                    'title',
                    ['is_del' => false],
                    'title->>\'v\''
                );
                $fields['data_src']['jsonFields']['fieldSettings']['selectTable']['values'] = $this->Totum->getModel('tables')->getFieldIndexedByField(
                    ['is_del' => false, 'type' => ['globcalcs', 'simple']],
                    'name',
                    'title',
                    'title->>\'v\''
                );
            }
        }


        unset($field);
        return $fields;
    }

    /**
     * @param $tableRow
     * @return array
     */
    protected function tableRowForClient($tableRow, $visibleFields = null)
    {
        $fields = ['title', 'updated', 'type', 'id', 'tree_node_id', 'sess_hash', 'description', 'fields_sets', 'panel', 'order_field',
            'order_desc', 'fields_actuality', 'with_order_field', 'main_field', 'delete_timer', '__version', 'pagination',
            'panels_view', 'new_row_in_sort', 'rotated_view', 'deleting', 'on_duplicate'];
        if ($this->User->isCreator()) {
            $fields = array_merge(
                $fields,
                [
                    'name', 'sort', 'actual', 'default_action', 'row_format', 'table_format'
                ]
            );

        } else {
            $tableRow['description'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $tableRow['description']);
        }
        $_tableRow = array_intersect_key($tableRow, array_flip($fields));
        foreach (Totum::TABLE_CODE_PARAMS as $name) {
            if (key_exists($name, $_tableRow)) {
                $_tableRow[$name] = trim($_tableRow[$name]);
                $_tableRow[$name] = !!preg_match('/^\s*[a-z0-9]*\=\:\s*[^\s]+/mu', $_tableRow[$name]);
            }
        }


        if (($tableRow['panels_view'] ?? null) && $visibleFields) {
            if ($tableRow['panels_view']['state'] === 'panel') {
                $_tableRow['panels_view'] = $tableRow['panels_view'];
            }
            $_tableRow['panels_view']['fields'] = array_filter($_tableRow['panels_view']['fields'],
                function ($field) use ($visibleFields) {
                    return key_exists($field['field'], $visibleFields);
                });
        }
        if ($this->Table->getTableRow()['type'] === 'calcs') {
            $_tableRow['fields_sets'] = $this->Table->changeFieldsSets();
            $_tableRow['cycle_id'] = $this->Table->getCycle()->getId();
        } else {
            $_tableRow  ['__is_in_favorites'] =
                !key_exists(
                    $this->Table->getTableRow()['id'],
                    $this->User->getTreeTables()
                ) ? null : in_array(
                    $this->Table->getTableRow()['id'],
                    $this->User->getFavoriteTables()
                );
            if ($this->User->isCreator() && in_array($this->Table->getTableRow()['type'], ['tmp', 'simple'])) {
                $_tableRow  ['__is_in_forms'] = !!$this->Totum->getModel('ttm__forms')->get(['table_name' => $tableRow['name']],
                    'id');
            }

        }
        $_tableRow['description'] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $_tableRow['description']);
        $_tableRow['__withPDF'] = $this->isTableWithPDF();


        return $_tableRow;
    }

    public function dblClick()
    {
        $id = (int)($this->post['id'] ?? 0);
        $field = $this->post['field'] ?? '';

        $vars = [];
        if (!empty($this->post['hash'])) {
            $vars['__edit_hash'] = $this->post['hash'];
        }

        if ($field && key_exists(
                $field,
                $this->Table->getFields()
            ) && (($fieldParams = $this->Table->getFields()[$field])['CodeActionOnClick'] ?? false)) {
            if (!is_a($this, WriteTableActions::class) && empty($fieldParams['AllowActionClickOnRead'])) {
                throw new errorException($this->translate('Your access to this table is read-only. Contact administrator to make changes.'));
            }
            if ($id) {
                if ($this->Table->loadFilteredRows('web', [$id])) {
                    $row = $this->Table->getTbl()['rows'][$id];
                } else {
                    return ['result' => 'Row not found'];
                }
            } else {
                $row = $this->Table->getTbl()['params'];
            }
            $this->clickToButton($fieldParams, $row, $vars, 'click');
        } elseif ($this->Table->getTableRow()['type'] === 'cycles') {
            if (!empty($id)) {
                if ($this->Table->loadFilteredRows('web', [$id])) {
                    if (key_exists('button_to_cycle', $this->Table->getFields())) {
                        $this->clickToButton(
                            $this->Table->getFields()['button_to_cycle'],
                            $this->Table->getTbl()['rows'][$id],
                            []
                        );
                    } else {
                        if ($CalcsId = $this->Totum->getModel('tables')->getField(
                            'id',
                            ['type' => 'calcs', 'tree_node_id' => ($treeNodeId = $this->Table->getTableRow()['id']), 'id' => array_keys($this->User->getTables())],
                            'sort'
                        )) {
                            $topId = $this->Table->getTableRow()['top'];
                            $this->Totum->addToInterfaceLink(
                                "{$this->modulePath}$topId/$treeNodeId/$id/$CalcsId",
                                'self'
                            );
                        }
                    }
                }
            }
        }
        return $this->getTableClientChangedData([]);
    }

    protected function modify($data)
    {
        $tableData = $this->post['tableData'] ?? [];
        $data['modify']['params'] = array_merge(
            $data['modify']['params'] ?? [],
            $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')
        );
        $this->Table->checkAndModify($tableData, $data);

        return $this->getTableClientChangedData($data, true);
    }

    protected function getTableClientChangedData($data, $force = false)
    {
        $return = [];
        if ($force || $this->Table->getTableRow()['type'] === 'tmp' || $this->Totum->isAnyChages() || !empty($data['refresh']) || $this->Totum->getConfig()->procVar()) {


            $this->Table->reCalculateFilters(
                'web',
                false,
                false,
                ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
            );


            $pageIds = json_decode($this->post['ids'], true);
            if ($pageIds) {
                $pageIds = $this->Table->loadFilteredRows('web', $pageIds ?? []);
            }

            $return['chdata']['rows'] = [];

            $changedIds = $this->Table->getChangeIds();


            $displaced = [];
            if ($changedIds['added']) {
                $return['chdata']['rows'] = array_intersect_key(
                    $this->Table->getTbl()['rows'],
                    $changedIds['added']
                );


                if (($this->post['onPage'] ?? false) && (count($pageIds) + count($changedIds['added'])) > (int)$this->post['onPage']) {
                    $displaceOffset = $this->post['onPage'] - (count($pageIds) + count($changedIds['added']));
                    $displaced = array_slice($pageIds, $displaceOffset);
                }

                array_push($pageIds, ...array_keys($changedIds['added']));
            }

            if ($changedIds['deleted'] || $displaced) {
                $return['chdata']['deleted'] = array_merge(array_keys($changedIds['deleted'] ?? []), $displaced);
                if ($pageIds) {
                    $pageIds = array_diff($pageIds, $return['chdata']['deleted']);
                }
            }
            if ($changedIds['restored']) {
                $return['chdata']['deleted'] = array_keys($changedIds['restored']);
                $pageIds = array_diff($pageIds, array_keys($changedIds['restored']));
            }

            //Отправка на клиент изменений, селектов и форматов
            {
                if (!empty($pageIds)) {

                    $sortedVisibleFields = $this->Table->getVisibleFields('web', true);
                    $selectOrFormatColumns = [];
                    foreach ($sortedVisibleFields['column'] as $k => $v) {
                        if ((($v['type'] === 'select' || $v['type'] === 'tree') && !empty($v['codeSelectIndividual'])) || !empty($v['format'])) {
                            $selectOrFormatColumns[$k] = true;
                        }
                    }


                    $modify = $data['modify'] ?? [];
                    unset($modify['params']);
                    $changedIds['changed'] += ($modify ?? []);
                    $selectOrFormatColumns['id'] = true;
                    if ($this->getPageViewType() === 'tree') {
                        $selectOrFormatColumns['n'] = true;
                    }

                    $rows = $this->Table->getTbl()['rows'];
                    foreach ($pageIds as $id) {
                        if (!empty($rows[$id])) {

                            if (key_exists($id, $changedIds['changed'])) {
                                $changes = $changedIds['changed'][$id];
                                if (empty($changes)) {
                                    $return['chdata']['rows'][$id] = $rows[$id];
                                    continue;
                                } else {
                                    $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key(
                                            $rows[$id],
                                            $changes
                                        );
                                    foreach ($changes as $k => $_) {
                                        if (is_array($return['chdata']['rows'][$id][$k])) {
                                            $return['chdata']['rows'][$id][$k]['changed'] = true;
                                        }
                                    }
                                }
                            }

                            $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key(
                                    $rows[$id],
                                    $selectOrFormatColumns
                                );
                        }
                    }
                }

                if ($this->Table->getChangeIds()['reordered']) {
                    $return['chdata']['order'] = array_column($return['chdata']['rows'], 'id');
                }
            }
            if ($this->getPageViewType() === 'tree' && $this->Table->getFields()['tree']['treeViewType'] === 'self') {
                $return['chdata']['tree'] = $this->getResultTree(
                    function ($k, $v) {
                        return 'child';
                    },
                    [''],
                    true
                )['tree'];
            }

            $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS']);

            $return['chdata']['params'] = $this->Table->getTbl()['params'] ?? [];
            $return['chdata']['f'] = $this->getTableFormat($pageIds ?: []);
            $return['chdata'] = $this->Table->getValuesAndFormatsForClient($return['chdata'], 'web', $pageIds ?: []);

            if (empty($return['chdata']['params'])) {
                unset($return['chdata']['params']);
            }

            if ($this->isPagingView()) {
                if ($this->isPagingView('tree')) {
                    $branches = [];
                    foreach ($return['chdata']['rows'] ?? [] as $row) {
                        $branches[$row['tree']['v']] = 1;
                    }
                    foreach ($branches as $b => $_) {
                        $params = $this->Table->filtersParamsForLoadRows('web');
                        $return['chdata']['treeCounts'][$b] = $params === false ? 0 : $this->Table->countByParams([...$params, ['field' => 'tree', 'value' => $b, 'operator' => '=']]);
                    }
                } else {
                    $params = $this->Table->filtersParamsForLoadRows('web');
                    $return['allCount'] = $params === false ? 0 : $this->Table->countByParams($params);
                }
            }
            $return['updated'] = $this->Table->getSavedUpdated();


            $this->Table->calcLog($Log, 'result', 'done');
        } else {
            $return = ['ok' => 1];
        }
        return $return;
    }

    /**
     * @param string|array $filtersString
     * @return array
     * @throws errorException
     */
    protected function getPermittedFilters($filtersString): array
    {
        $permittedFilters = [];
        $deCryptFilters = $this->deCryptFilters($filtersString);
        foreach ($deCryptFilters as $fName => $val) {
            if (key_exists(
                    $fName,
                    $this->Table->getFields()
                ) && $this->Table->getFields()[$fName]['category'] === 'filter') {
                if ($this->Table->isField('editable', 'web', $this->Table->getFields()[$fName])) {
                    $permittedFilters[$fName] = $val;
                }
            }
        }

        return $permittedFilters;
    }

    protected function getTreeTopLevel($load, $open)
    {


        $result = $this->getResultTree(
            function ($k, $v) use ($load, $open) {
                if ($v[3] === null || $load || $open) {
                    if (!$open) {
                        return 'closed';
                    }
                    return 'this';
                } elseif (is_null($v['path'][3])) {
                    return 'child';
                }
            },
            null
        );

        $pageIds = array_column($result['rows'] ?? [], 'id');
        $result['params'] = $this->addValuesAndFormatsOfParams($this->Table->getTbl()['params'], $pageIds)['params'];
        $result['filtersString'] = $this->getFiltersString();
        $result['f'] = $this->getTableFormat($pageIds);
        return $result;
    }

    protected function getResultTree($filterFunc, $loadingIds, $withoutRows = false)
    {
        $rowsSwitcher = $withoutRows ? false : ($this->isPagingView('tree') && ($this->Table->getFields()['tree_category']['category'] ?? false) !== 'column' ? 'counts' : 'rows');

        $Tree = Field::init($this->Table->getFields()['tree'], $this->Table);
        $val = ['v' => null];

        $Tree->clearCachedLists();
        $list = $Tree->calculateSelectList($val, [], $this->Table->getTbl());


        $bids = [];
        $tree = [];
        $thisNodes = [];
        if (!is_array($loadingIds)) {
            if ($Tree->getData('treeViewType') !== 'self' && !is_null($t = $Tree->getData('withEmptyVal'))) {
                $tree[] = ['v' => null, 't' => $t];
            }
            $bids[] = '';
        } else {
            $bids = $loadingIds;
            $thisNodes = array_intersect_key($list, array_flip($loadingIds));
        }
        foreach ($list as $k => $v) {
            /*Без удаленных*/
            if ($v[1] === 0) {
                switch ($filterFunc($k, $v, $thisNodes)) {
                    case 'this':
                    case 'parent':
                        $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => true, 'p' => $v[3]];
                        $bids[] = (string)$k;
                        break;
                    case 'closed':
                        $bids[] = (string)$k;
                        $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => false, 'p' => $v[3]];
                        break;
                    case 'child':
                        $tree[] = ['v' => $k, 't' => $v[0], 'p' => $v[3]];
                        break;
                    case 'loaded':
                        $bids[] = (string)$k;
                        $tree[] = ['v' => $k, 't' => $v[0], 'p' => $v[3]];
                        break;
                }
            }
        }
        $result['rows'] = [];

        if ($bids) {
            switch ($rowsSwitcher) {
                case 'rows':
                    $treeIds = $this->Table->getByParams(
                        (new FormatParamsForSelectFromTable)
                            ->where('tree', $bids)
                            ->field('id')
                            ->params(),
                        'list'
                    );

                    /*add tree_category rows*/
                    if ($this->Table->getFields()['tree']['treeViewType'] === 'other'
                        && ($this->Table->getFields()['tree_category']['category'] ?? false) === 'column') {
                        $treeIds = array_merge(
                            $treeIds,
                            $this->Table->getByParams(
                                (new FormatParamsForSelectFromTable)
                                    ->where('tree_category', $bids)
                                    ->where('tree_category', '', '!=')
                                    ->field('id')
                                    ->params(),
                                'list'
                            )
                        );
                    }

                    if ($treeIds) {
                        $result['rows'] = $this->Table->getSortedFilteredRows('web', 'web', $treeIds)['rows'];

                        /*TreeBranchesFilter*/
                        $TreeBranchesFilter = false;
                        foreach ($this->Table->getSortedFields()['filter'] ?? [] as $fName => $field) {
                            if (!empty($field['showInWeb']) && !empty($field['column'])
                                && !empty($this->Table->getTbl()['params'][$field['name']]['v'])
                                && $this->Table->getTbl()['params'][$field['name']]['v'] !== '*ALL*'
                                && $this->Table->getTbl()['params'][$field['name']]['v'] !== ['*ALL*']
                            ) {
                                $TreeBranchesFilter = true;
                                break 1;
                            }
                        }

                        if ($TreeBranchesFilter) {
                            $rowIds = [];
                            foreach ($result['rows'] as $row) {
                                $rowIds[$row['tree']['v']] = true;
                            }

                            $treeBranches = [];

                            $addBranch = function ($treeId) use (&$addBranch, $list, &$treeBranches) {
                                if (!key_exists($treeId, $treeBranches)) {
                                    $treeBranches[$treeId] = true;
                                    if (!empty($list[$treeId][3])) {
                                        $addBranch($list[$treeId][3]);
                                    }
                                }
                            };

                            foreach ($rowIds as $treeId => $_) {
                                if (key_exists($treeId, $list)) {
                                    $addBranch($treeId);
                                }
                            }

                            $tree = [];
                            if ($Tree->getData('treeViewType') !== 'self' && !is_null($t = $Tree->getData('withEmptyVal'))) {
                                $tree[] = ['v' => null, 't' => $t];
                            }
                            foreach ($list as $k => $v) {
                                /*Без удаленных*/
                                if ($v[1] === 0 && key_exists($k, $treeBranches)) {
                                    switch ($filterFunc($k, $v, $thisNodes)) {
                                        case 'this':
                                        case 'parent':
                                            $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => true, 'p' => $v[3]];
                                            break 1;
                                        case 'closed':
                                            $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => false, 'p' => $v[3]];
                                            break 1;
                                        case 'loaded':
                                        case 'child':
                                            $tree[] = ['v' => $k, 't' => $v[0], 'p' => $v[3]];
                                            break 1;
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'counts':

                    if ($tree) {
                        $this->Table->reCalculateFilters('web');
                        $params = $this->Table->filtersParamsForLoadRows('web');
                        foreach ($tree as &$b) {
                            $b['count'] = $params === false ? 0 : $this->Table->countByParams([...$params, ['field' => 'tree', 'operator' => '=', 'value' => $b['v']]]);
                        }
                        unset($b);

                        /*TreeBranchesFilter*/
                        $TreeBranchesFilter = false;
                        foreach ($this->Table->getSortedFields()['filter'] ?? [] as $fName => $field) {
                            if (!empty($field['showInWeb']) && !empty($field['column'])
                                && !empty($this->Table->getTbl()['params'][$field['name']]['v'])
                                && $this->Table->getTbl()['params'][$field['name']]['v'] !== '*ALL*'
                                && $this->Table->getTbl()['params'][$field['name']]['v'] !== ['*ALL*']
                            ) {
                                $TreeBranchesFilter = true;
                                break 1;
                            }
                        }

                        if ($TreeBranchesFilter) {
                            $rowIds = [];
                            foreach ($tree as $b) {
                                if ($b['count']) {
                                    $rowIds[$b['v']] = true;
                                }
                            }

                            $treeBranches = [];

                            $treeOld = $tree;
                            $addBranch = function ($treeId) use (&$addBranch, $list, &$treeBranches) {
                                if (!key_exists($treeId, $treeBranches)) {
                                    $treeBranches[$treeId] = true;
                                    if (!empty($list[$treeId][3])) {
                                        $addBranch($list[$treeId][3]);
                                    }
                                }
                            };

                            foreach ($tree as $_) {
                                if ($_['count']) {
                                    $addBranch($_['v']);
                                }
                            }

                            foreach ($tree as $k => $v) {
                                /*Без удаленных*/
                                if (!key_exists($v['v'], $treeBranches)) {
                                    unset($tree[$k]);
                                }
                            }
                            $tree = array_values($tree);
                        }
                    }
                    break;
            }
        }
        $result['tree'] = $tree;

        return $result;
    }

    public function loadTreeBranches()
    {
        if ($branchIds = $this->Request->getParsedBody()['branchIds'] ?? 0) {
            $this->Table->reCalculateFilters(
                'web',
                true,
                false,
                ["params" => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
            );

            $parentIds = ($this->Request->getParsedBody()['withParents'] ?? null) ? null : [];
            $recurcive = ($this->Request->getParsedBody()['recurcive'] ?? null) === 'true';
            return $this->getResultTree(
                function ($k, $v, $thisNodes) use (&$parentIds, $branchIds, $recurcive) {
                    if ($thisNodes && is_null($parentIds)) {
                        foreach ($thisNodes as $thisNode) {
                            $path = $thisNode;
                            while ($path) {
                                if ($path[3] ?? null) {
                                    $parentIds[] = $path[3];
                                }
                                $path = $path['path'] ?? null;
                            }
                        }
                        $parentIds = array_unique($parentIds ?? []);
                    }

                    if (in_array($k, $branchIds)) {
                        return 'this';
                    } elseif ($parentIds && in_array($k, $parentIds)) {
                        return 'parent';
                    } elseif ($recurcive) {
                        $path = $v;
                        while ($path) {
                            if (in_array($path[3] ?? null, $branchIds)) {
                                return 'parent';
                            }
                            $path = $path['path'] ?? null;
                        }
                        return false;
                    } elseif (in_array($v[3] ?? null, $branchIds) || in_array($v[3] ?? null, $parentIds ?? [])) {
                        return 'child';
                    }
                },
                $branchIds
            );
        } else {
            throw new errorException($this->translate('Failed to get branch Id'));
        }
    }

    protected function getKanbanData(&$fields = null)
    {
        $panelViewSettings = $this->Table->getTableRow()['panels_view'];
        $fields = array_column($panelViewSettings['fields'], 'field');

        if ($panelViewSettings['kanban'] && $kanban = $this->Table->getFields()[$panelViewSettings['kanban']]) {
            if (!in_array($panelViewSettings['kanban'], $fields)) {
                $fields[] = $panelViewSettings['kanban'];
            }
            $kanban_data = [];

            $val = [];
            $results = Field::init($kanban, $this->Table)->calculateSelectList(
                $val,
                [],
                $this->Table->getTbl()
            );
            unset($results['previewdata']);

            if ($kanban['withEmptyVal'] ?? false) {
                $kanban_data[] = ['', $kanban['withEmptyVal']];
            }

            foreach ($results as $k => $v) {
                if (!$v[1]) {
                    $kanban_data[] = [$k, $v[0]];
                }
            }
            $this->kanban_bases = array_column($kanban_data, 0);

            return $kanban_data;
        }
        return null;
    }

    protected function isTableWithPDF()
    {
        /*TODO Check it*/
        return true;
    }
}
