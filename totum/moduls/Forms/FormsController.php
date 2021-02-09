<?php


namespace totum\moduls\Forms;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculcateFormat;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\controllers\interfaceController;
use totum\common\tableSaveException;
use totum\common\Totum;
use totum\config\Conf;
use totum\config\totum\moduls\Forms\ReadTableActionsForms;
use totum\config\totum\moduls\Forms\WriteTableActionsForms;
use totum\fieldTypes\Select;
use totum\moduls\Table\Actions;
use totum\tableTypes\aTable;
use totum\tableTypes\tmpTable;

class FormsController extends interfaceController
{
    private static $path;

    /**
     * @var aTable
     */
    protected $Table;
    protected $onlyRead;
    private $css;
    /**
     * @var array
     */
    private $FormsTableData;
    private $_INPUT;
    private $clientFields;
    /**
     * @var array
     */
    private $sections;
    /**
     * @var CalculcateFormat
     */
    private $CalcTableFormat;
    /**
     * @var CalculcateFormat
     */
    private $CalcRowFormat;
    private $CalcFieldFormat;
    /**
     * @var Calculate
     */
    private $CalcSectionStatuses;
    /**
     * @var array|object|null
     */
    private $INPUT;
    private $totumTries = 0;

    public function __construct(Conf $Config, $totumPrefix = '')
    {
        $this->applyAllOrigins();
        parent::__construct($Config, $totumPrefix);
        static::$pageTemplate = __DIR__ . '/__template.php';
    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestTable = substr($requestUri, strlen($this->modulePath));


        if ($request->getMethod() === 'GET') {
            $action = "Main";
            $this->__addAnswerVar('path', $requestTable);
        } else {
            $this->isAjax = true;

            try {
                $this->FormsTableData = $this->checkTableByStr($requestTable);
                $User = Auth::loadAuthUser($this->Config, $this->FormsTableData['call_user'], false);

                if (!$User) {
                    throw new errorException('Ошибка авторизации пользователя форм');
                }

                try {
                    $this->Totum = new Totum($this->Config, $User);
                    $this->answerVars = $this->actions($request);
                } catch (tableSaveException $exception) {
                    if (++$this->totumTries < 5) {
                        $this->Config = $this->Config->getClearConf();
                        $this->Totum = new Totum($this->Config, $User);
                        $this->answerVars = $this->actions($request);
                    } else {
                        throw new \Exception('Ошибка одновременного доступа к таблице');
                    }
                }
            } catch (\Exception $e) {
                if (!$this->isAjax) {
                    static::$contentTemplate = $this->Config->getTemplatesDir() . '/__error.php';
                }
                $message = $e->getMessage();
                $this->__addAnswerVar('error', $message);
            }
            $action = "json";
        }
        if ($output) {
            $this->output($action);
        }
    }

    protected function actions(ServerRequestInterface $request)
    {
        $this->loadTable($this->FormsTableData, $request);

        $parsedRequest = json_decode((string)$request->getBody(), true);
        try {
            if (!($method = $parsedRequest['method'] ?? '')) {
                throw new errorException('Ошибка. Не указан метод');
            }
            $Actions = $this->getTableActions($request, $method);

            if (is_callable([$Actions, 'addFormsTableData'])) {
                $Actions->addFormsTableData($this->FormsTableData);
            }

            if (!in_array($method, ['checkForNotifications', 'checkTableIsChanged'])) {
                $this->Totum->transactionStart();
            }

            /** @var string $method */
            $result = $Actions->$method();

            if ($links = $this->Totum->getInterfaceLinks()) {
                $result['links'] = $links;
            }
            if ($panels = $this->Totum->getPanelLinks()) {
                $result['panels'] = $panels;
            }
            if ($links = $this->Totum->getInterfaceDatas()) {
                $result['interfaceDatas'] = $links;
            }
            $this->Totum->transactionCommit();
        } catch (errorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? "<br/>" . $exception->getPathMess() : '')];
        } catch (criticalErrorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? "<br/>" . $exception->getPathMess() : '')];
        }

        return $result;
    }

    public function getEditSelect($data, $q, $parentId, $viewtype = null)
    {
        $type = $viewtype;

        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null)) {
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');
        }
        if (!in_array(
            $field['type'],
            ['select', 'tree']
        )) {
            throw new errorException('Ошибка - поле не типа select/tree');
        }

        $this->Table->loadDataRow();


        $row = $data['item'];
        foreach ($row as $k => &$v) {
            if ($k !== 'id') {
                $v = ['v' => $v];
            }
        }

        $row = $row + $this->Table->getTbl()['params'];


        /** @var Select $Field */
        $Field = Field::init($field, $this->Table);

        if ($type) {
            $list = [];
            $indexed = [];
            foreach ($Field->calculateSelectListWithPreviews(
                $row[$field['name']],
                $row,
                $this->Table->getTbl()
            ) as $val => $data) {
                if (!empty($data['2'])) {
                    $data['2'] = $data['2']();
                }

                $val = strval($val);

                $list[] = $val;
                $indexed[$val] = $data;
                switch ($type) {
                    case 'checkboxpicture':
                        foreach ($indexed[$val][array_key_last($indexed[$val])] as $kPreview => $vls) {
                            switch ($vls[2]) {
                                case 'file':
                                    $pVal = $this->_getHttpFilePath() . $vls[1][0]['file'];
                                    break;
                                default:
                                    $pVal = $vls[1];
                            }
                            $indexed[$val][array_key_last($indexed[$val])][$kPreview] = $pVal;
                        }

                        break;
                    default:
                        foreach ($indexed[$val][array_key_last($indexed[$val])] as $name => &$vls) {
                            switch ($vls[2]) {
                                case 'file':
                                    foreach ($vls[1] as &$f) {
                                        $f = $this->_getHttpFilePath() . $f['file'];
                                    }
                                    unset($f);
                                    break;
                            }
                            array_unshift($vls, $name);
                        }
                        $indexed[$val][array_key_last($indexed[$val])] = array_values($indexed[$val][array_key_last($indexed[$val])]);
                        unset($vls);
                }
            }


            return ['indexed' => $indexed, 'list' => $list, 'sliced' => false];
        }
        $list = $Field->calculateSelectList($row[$field['name']], $row, $this->Table->getTbl());

        return $Field->cropSelectListForWeb($list, $row[$field['name']]['v'], $q, $parentId);
    }

    private static function _getHttpFilePath()
    {
        return static::$path ?? (static::$path = (
                (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']) ? 'https://' : 'http://') . \totum\config\Conf::getFullHostName() . '/fls/'
            ));
    }

    public function actionMain()
    {
        $this->__addAnswerVar('css', $this->FormsTableData['css']);
    }

    protected function checkTableByStr($form)
    {
        if ($form) {
            $Totum = new Totum($this->Config, Auth::ServiceUserStart($this->Config));
            $tableData = $Totum->getTable('ttm__forms')->getByParams(
                ['where' => [
                    ['field' => 'path_code', 'operator' => '=', 'value' => $form],
                    ['field' => 'on_off', 'operator' => '=', 'value' => true]],
                    'field' => ['table_name', 'call_user', 'css', 'format_static', 'fields_else_params', 'section_statuses_code']],
                'row'
            );

            if (!$tableData) {
                throw new errorException('Доступ к таблице запрещен');
            } else {
                return $tableData;
            }
        } else {
            throw new errorException('Неверный путь к таблице');
        }
    }

    protected function stch()
    {
        $this->CalcTableFormat = new CalculcateFormat($this->Table->getTableRow()['table_format']);
        $this->CalcRowFormat = new CalculcateFormat($this->Table->getTableRow()['row_format']);

        if ($this->FormsTableData['section_statuses_code'] && !preg_match(
                '/^\s*=\s*:\s*$/',
                $this->FormsTableData['section_statuses_code']
            )) {
            $this->CalcSectionStatuses = new Calculate($this->FormsTableData['section_statuses_code']);
        }
    }

    private function loadTable($tableData, ServerRequestInterface $request)
    {
        $tableRow = $this->Totum->getTableRow($tableData['table_name']);
        if (!key_exists($tableRow['id'], $this->Totum->getUser()->getTables())) {
            throw new errorException('Ошибка настройки формы - пользователю запрещен доступ к таблице');
        }

        $extradata = null;
        $post = json_decode((string)$request->getBody(), true) ?? [];
        $extradata = $extradata['sess_hash'] ?? null;
        if ($tableRow['type'] === 'tmp' && $extradata) {
            if (!tmpTable::checkTableExists($tableRow['name'], $extradata, $this->Totum)) {
                $extradata = null;
            }
        }

        $this->Table = $this->Totum->getTable($tableRow, $extradata);

        $this->onlyRead = ($this->Totum->getUser()->getTables()[$this->Table->getTableRow()['id']] ?? null) !== 1;

        if (!$extradata) {
            $add_tbl_data = [];
            $add_tbl_data["params"] = [];
            if (key_exists('h_get', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_get'] = $post['data']['get'] ?? [];
            }
            if (key_exists('h_post', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_post'] = $post['data']['post'] ?? [];
            }
            if (key_exists('h_input', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_input'] = $post['data']['input'] ?? '';
            }
            if (!empty($_GET['d']) && ($d = Crypt::getDeCrypted(
                    $_GET['d'],
                    false
                )) && ($d = json_decode($d, true))) {
                if (!empty($d['d'])) {
                    $add_tbl_data["tbl"] = $d['d'];
                }
                if (!empty($d['p'])) {
                    $add_tbl_data["params"] = $d['p'] + $add_tbl_data["params"];
                }
            }
            if ($add_tbl_data && $this->Table->getTableRow()['type'] === 'tmp') {
                $this->Table->addData($add_tbl_data);
            }
        }
    }

    private function modify(array $tableData, array $data)
    {
        $modify = $data['modify'] ?? [];
        $remove = $data['remove'] ?? [];
        $add = $data['add'] ?? null;
        $duplicate = $data['duplicate'] ?? [];
        $reorder = $data['reorder'] ?? [];

        $tableRow = $this->Table->getTableRow();

        if ($add && !Table::isUserCanAction(
                'insert',
                $tableRow
            )) {
            throw new errorException('Добавление в эту таблицу вам запрещено');
        }
        if ($remove && !Table::isUserCanAction(
                'delete',
                $tableRow
            )) {
            throw new errorException('Удаление из этой таблицы вам запрещено');
        }
        if ($duplicate && !Table::isUserCanAction(
                'duplicate',
                $tableRow
            )) {
            throw new errorException('Дублирование в этой таблице вам запрещено');
        }
        if ($reorder && !Table::isUserCanAction(
                'reorder',
                $tableRow
            )) {
            throw new errorException('Сортировка в этой таблице вам запрещена');
        }

        $click = $data['click'] ?? [];
        $refresh = $data['refresh'] ?? [];


        //checkTableUpdated($tableData);

        $inVars = [];
        $inVars['modify'] = [];
        $inVars['channel'] = $data['channel'] ?? 'web';
        if (!empty($modify['setValuesToDefaults'])) {
            unset($modify['setValuesToDefaults']);
            $inVars['setValuesToDefaults'] = $modify;
        } else {
            $inVars['modify'] = $modify;
        }


        $inVars['calculate'] = aTable::CalcInterval['changed'];

        if ($refresh) {
            $inVars['modify'] = $inVars['modify'] + array_flip($refresh);
        }
        $fieldFormatEditable = $this->getTableFormats(
            true,
            array_intersect_key($this->Table->getTbl()['rows'], $inVars['modify'])
        );

        if (empty($fieldFormatEditable['t']['blockadd'])) {
            $inVars['add'] = !is_null($add) ? [$add] : [];
        }
        if (empty($fieldFormatEditable['t']['blockdelete'])) {
            $inVars['remove'] = $remove;
        }
        if (empty($fieldFormatEditable['t']['blockduplicate'])) {
            $inVars['duplicate'] = $duplicate;
        }
        if (empty($fieldFormatEditable['t']['blockorder'])) {
            $inVars['reorder'] = $reorder;
        }

        if (!empty($data['addAfter']) && in_array(
                $data['addAfter'],
                $duplicate['ids']
            ) && !(empty($inVars['duplicate']))) {
            $inVars['addAfter'] = $data['addAfter'];
        }

        foreach ($inVars['modify'] as $itemId => &$editData) {//Для  saveRow
            if ($itemId == 'params') {
                foreach ($editData as $k => $v) {
                    if (!key_exists($k, $fieldFormatEditable)) {
                        unset($inVars['modify']['params'][$k]);
                    }
                }
                continue;
            }
            if (!is_array($editData)) {//Для  refresh
                $editData = [];
                continue;
            }
            if (!key_exists($itemId, $fieldFormatEditable)) {
                unset($inVars['modify'][$itemId]);
            }

            foreach ($editData as $k => &$v) {
                if (!key_exists($k, $fieldFormatEditable[$itemId])) {
                    unset($editData[$k]);
                    continue;
                }
                if (is_array($v) && array_key_exists('v', $v)) {
                    if (array_key_exists('h', $v)) {
                        if ($v['h'] == false) {
                            $inVars['setValuesToDefaults'][$itemId][$k] = true;
                            unset($editData[$k]);
                            continue;
                        }
                    }
                    $v = $v['v'];
                }
            }
        }
        unset($editData);
        $return = ['chdata' => []];
        if ($click) {
            $this->Table->reCalculateFilters('web');

            $clickItem = array_key_first($click);
            $clickFieldName = array_key_first($click[$clickItem]);
            if ($clickItem === 'params') {
                if (!key_exists(
                        $clickFieldName,
                        $fieldFormatEditable
                    ) && !($this->clientFields[$clickFieldName]['pressableOnOnlyRead'] ?? false)) {
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
                }
                $row = $this->Table->getTbl()['params'];
            } else {
                if (!key_exists($clickFieldName, $fieldFormatEditable[$clickItem] ?? [])) {
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
                }

                $row = $this->tbl['rows'][$clickItem] ?? null;
                if (!$row || !empty($row['is_del'])) {
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
                }
            }
            try {
                Field::init($this->Table->getFields()[$clickFieldName], $this->Table)->action(
                    $row,
                    $row,
                    $this->Table->getTbl(),
                    $this->Table->getTbl(),
                    ['ids' => $click['checked_ids'] ?? []]
                );
            } catch (\ErrorException $e) {
                throw $e;
            }

            $return['ok'] = 1;
        } else {
            $this->Table->reCalculateFromOvers($inVars);
        }


        $return['chdata']['rows'] = [];

        if ($this->Table->getChangeIds()['added']) {
            $return['chdata']['rows'] = array_intersect_key(
                $this->Table->getTbl()['rows'],
                $this->Table->getChangeIds()['added']
            );
        }

        if ($this->Table->getChangeIds()['deleted']) {
            $return['chdata']['deleted'] = array_keys($this->Table->getChangeIds()['deleted']);
        }
        $modify = $inVars['modify'];
        unset($modify['params']);


        $fieldFormatS = $this->getTableFormats(false, $this->Table->getTbl()['rows']);

        if (($modify += $this->Table->getChangeIds()['changed']) && $fieldFormatS['r']) {
            foreach ($modify as $id => $changes) {
                if (empty($this->Table->getTbl()['rows'][$id]) || !empty($fieldFormatS['r'][$id]['hidden'])) {
                    continue;
                }
                $return['chdata']['rows'][$id] = $this->Table->getTbl()['rows'][$id];
                $return['chdata']['rows'][$id]['id'] = $id;
            }
        }


        $return['chdata']['params'] = array_intersect_key($this->Table->getTbl()['params'], $fieldFormatS['p']);
        $return['chdata'] = $this->getValuesForClient($return['chdata'], $fieldFormatS);
        $return['chdata']['f'] = $fieldFormatS;

        $return['updated'] = $this->Table->getSavedUpdated();

        return $return;
    }

    private function getTableFormats(bool $onlyEditable, $rows)
    {
        $tableFormats = $this->CalcTableFormat->getFormat('TABLE', [], $this->Table->getTbl(), $this->Table);
        $tableJsonFromRow = $this->FormsTableData['format_static'];

        if ($this->CalcSectionStatuses) {
            $sectionFormats = $this->CalcSectionStatuses->exec(
                [],
                null,
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


        if (!empty($tableFormats['rowstitle'])) {
            if (preg_match('/^([a-z0-9_]{1,})\s*\:\s*(.*)/', $tableFormats['rowstitle'], $matches)) {
                $tableFormats['rowsTitle'] = $matches[2];
                $tableFormats['rowsName'] = $matches[1];
            } else {
                $tableFormats['rowsName'] = "";
                $tableFormats['rowsTitle'] = $tableFormats['rowstitle'];
            }
        }
        unset($tableFormats['rowstitle']);

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
                case 'view':
                    return 1;
                default:
                    return 0;
            }
        };


        if ($onlyEditable) {
            $result = [];
            if (!$this->onlyRead) {
                foreach ($this->sections as $category => $sec) {
                    switch ($category) {
                        case 'rows':
                            $section = $sec;
                            if ($st = $getSectionEditType($section['name'])) {

                                /*Если секция редактируемая*/
                                if ($st == 2) {
                                    foreach ($rows as $row) {
                                        $rowFormat = $this->CalcRowFormat->getFormat(
                                            'ROW',
                                            $row,
                                            $this->Table->getTbl(),
                                            $this->Table
                                        );
                                        if (empty($rowFormat['block'])) {
                                            foreach ($section['fields'] as $fieldName) {
                                                if (!key_exists($fieldName, $this->clientFields)) {
                                                    continue;
                                                }

                                                $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                                    ?? ($this->CalcFieldFormat[$fieldName]
                                                        = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                                $format = $FieldFormat->getFormat(
                                                    $fieldName,
                                                    $row,
                                                    $this->Table->getTbl(),
                                                    $this->Table
                                                );

                                                if (empty($format['block']) && empty($format['hidden'])) {
                                                    $result[$row['id']][$fieldName] = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            break;
                        default:
                            foreach ($sec as $section) {
                                if ($st = $getSectionEditType($section['name'])) {
                                    /*Если секция редактируемая*/
                                    if ($st == 2) {
                                        foreach ($section['fields'] as $fieldName) {
                                            if (!key_exists($fieldName, $this->clientFields)) {
                                                continue;
                                            }

                                            $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                                ?? ($this->CalcFieldFormat[$fieldName]
                                                    = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                            $format = $FieldFormat->getFormat(
                                                $fieldName,
                                                $this->Table->getTbl()['params'],
                                                $this->Table->getTbl(),
                                                $this->Table
                                            );

                                            if (empty($format['block']) && empty($format['hidden'])) {
                                                $result[$fieldName] = true;
                                            }
                                        }
                                    }
                                }
                            }
                            break;
                    }
                }
            }


            $result = ['t' => $tableFormats] + $result;
        } else {
            $result = ['t' => $tableFormats, 'r' => [], 'p' => []];

            foreach ($this->sections as $category => $sec) {
                switch ($category) {
                    /*case 'rows':
                        $section = $sec;
                        $sectionStatus = $getSectionEditType($section['name']);
                        foreach ($rows as $row) {
                            $rowFormat = $this->CalcRowFormat->getFormat('ROW',
                                $row,
                                $this->Table->getTbl(),
                                $this->Table);

                            $result['r'][$row['id']]['f'] = $rowFormat;

                            foreach ($section['fields'] as $fieldName) {

                                if (!key_exists($fieldName, $this->clientFields)) continue;
                                if (!$sectionStatus) {
                                    $result['r'][$row['id']][$fieldName] = [];
                                } else {

                                    $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                        ?? ($this->CalcFieldFormat[$fieldName]
                                            = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                    $format = $FieldFormat->getFormat($fieldName,
                                        $row,
                                        $this->Table->getTbl(),
                                        $this->Table);

                                    if (empty($format['hidden'])) {
                                        $result['r'][$row['id']][$fieldName] = $format;
                                    } else {
                                        $result['r'][$row['id']][$fieldName] = ['hidden' => true];
                                    }
                                }
                            }
                        }
                        break;*/
                    default:
                        foreach ($sec as $section) {
                            $sectionStatus = $getSectionEditType($section['name']);
                            foreach ($section['fields'] as $fieldName) {
                                if (!key_exists($fieldName, $this->clientFields)) {
                                    continue;
                                }

                                if ($sectionStatus) {
                                    $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                        ?? ($this->CalcFieldFormat[$fieldName]
                                            = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
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
                        break;
                }
            }
            $result['t']['s'] = $result['t']['sections'] ?? [];
            unset($result['t']['sections']);
        }
        return array_replace_recursive($tableJsonFromRow, $result);
    }

    private function getValuesForClient($data, &$formats, $isFirstLoad = false)
    {
        /*foreach (($data['rows'] ?? []) as $i => $row) {

            $newRow = ['id' => ($row['id'] ?? null)];
            if (!empty($row['InsDel'])) {
                $newRow['InsDel'] = true;
            }
            foreach ($row as $fName => $value) {
                if (key_exists($fName, $this->Table->getFields())) {
                    Field::init($this->Table->getFields()[$fName], $this->Table)->addViewValues('edit',
                        $value,
                        $row,
                        $this->Table->getTbl());
                    $newRow[$fName] = $value;
                }
            }
            $data['rows'][$i] = $newRow;
        }*/
        if (!empty($data['params'])) {
            foreach ($data['params'] as $fName => &$value) {
                $field = $this->Table->getFields()[$fName];

                switch ($field['type']) {
                    case 'file':
                        Field::init($field, $this->Table)->addViewValues(
                            'edit',
                            $value,
                            $this->Table->getTbl()['params'],
                            $this->Table->getTbl()
                        );

                        $value['v_'] = [];
                        foreach ($value['v'] as $val) {
                            $value['v_'][] = static::_getHttpFilePath() . $val['file'];
                        }
                        unset($val);
                        break;
                    case 'select':
                        switch (strval($formats['p'][$fName]['viewtype'])) {
                            case 'viewimage':
                                $Field = Field::init($this->Table->getFields()[$fName], $this->Table);
                                $fileData = $Field->getPreviewHtml(
                                    $data['params'][$fName]['v'],
                                    $this->Table->getTbl()['params'],
                                    $this->Table->getTbl(),
                                    true
                                );
                                $data['params'][$fName]['v_'] = static::_getHttpFilePath() . ($fileData[$formats['p'][$fName]['viewdata']['picture_name'] ?? ''][1][0]['file'] ?? '');
                                break 2;
                            case "":
                                Field::init($field, $this->Table)->addViewValues(
                                    'edit',
                                    $value,
                                    $this->Table->getTbl()['params'],
                                    $this->Table->getTbl()
                                );
                                break;
                            default:
                                if ($isFirstLoad || $field['codeSelectIndividual']) {
                                    $formats['p'][$fName]['selects'] = $this->getEditSelect(
                                        ['field' => $fName, 'item' => array_map(
                                            function ($val) {
                                                return $val['v'];
                                            },
                                            $this->Table->getTbl()['params']
                                        )],
                                        '',
                                        null,
                                        $formats['p'][$fName]['viewtype']
                                    );
                                }
                        }

                    // no break
                    default:
                        Field::init($field, $this->Table)->addViewValues(
                            'edit',
                            $value,
                            $this->Table->getTbl()['params'],
                            $this->Table->getTbl()
                        );
                }
            }
            unset($value);
        }

        return $data;
    }

    private function getTableData()
    {
        try {
            $inVars = ['calculate' => aTable::CalcInterval['changed']
                , 'channel' => 'web'
                , 'isTableAdding' => ($this->Table->getTableRow()['type'] === 'tmp' && $this->Table->isTableAdding())
            ];
            Sql::transactionStart();
            $tbl = $this->Table->getTbl();
            $this->Table->reCalculateFromOvers($inVars);
            $tbl = $this->Table->getTbl();
            Sql::transactionCommit();
        } catch (errorException $e) {
            Sql::transactionRollBack();
            $error = $e->getMessage() . ' <br/> ' . $e->getPathMess();
            $this->Table->reCalculateFilters('web', false, true);
        }

        $data = [];

        $formats = $this->getTableFormats(false, $tbl['rows']);
        $data['params'] = array_intersect_key($tbl['params'], $formats['p']);
        $data['rows'] = [];
        foreach ($tbl['rows'] as $row) {
            if (!empty($row['is_del'])) {
                continue;
            }
            if (key_exists($row['id'], $formats['r'])) {
                $newRow = ['id' => $row['id']];
                foreach ($row as $k => $v) {
                    if (key_exists($k, $formats['r'][$row['id']])) {
                        $newRow[$k] = $v;
                    }
                }
                $data['rows'][] = $newRow;
            }
        }
        $data = $this->getValuesForClient($data, $formats, true);

        $result = [
            'tableRow' => $this->getTableRowForClient($this->Table->getTableRow())
            , 'f' => $formats
            , 'c' => $this->getTableControls()
            , 'fields' => $this->clientFields
            , 'sections' => $this->sections
            , 'error' => $error ?? null
            /*, 'data' => $data['rows']*/
            , 'data_params' => $data['params']
            , 'updated' => $this->Table->getSavedUpdated()

        ];

        return $result;
    }

    private function getTableControls()
    {
        $result = [];
        $result['deleting'] = !$this->onlyRead && Table::isUserCanAction(
                'delete',
                $this->Table->getTableRow()
            );
        $result['adding'] = !$this->onlyRead && Table::isUserCanAction(
                'insert',
                $this->Table->getTableRow()
            );
        $result['duplicating'] = !$this->onlyRead && Table::isUserCanAction(
                'duplicate',
                $this->Table->getTableRow()
            );
        $result['sorting'] = !$this->onlyRead && Table::isUserCanAction(
                'reorder',
                $this->Table->getTableRow()
            );
        $result['editing'] = !$this->onlyRead;
        return $result;
    }

    private function applyAllOrigins()
    {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // should do a check here to match $_SERVER['HTTP_ORIGIN'] to a
            // whitelist of safe domains
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            die;
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $method
     * @throws errorException
     */
    protected function getTableActions(ServerRequestInterface $request, string $method)
    {
        if (!$this->Table) {
            $Actions = new Actions($request, $this->modulePath, null, $this->Totum);
            $error = 'Таблица не найдена';
        } elseif (!$this->onlyRead) {
            $Actions = new WriteTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = 'Метод [[' . $method . ']] в этом модуле не определен или имеет админский уровень доступа';
        } else {
            $Actions = new ReadTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';
        }

        if (!is_callable([$Actions, $method])) {
            throw new errorException($error);
        }
        return $Actions;
    }
}
