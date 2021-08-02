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
use totum\common\Totum;
use totum\fieldTypes\Comments;
use totum\fieldTypes\Select;
use totum\models\TmpTables;
use totum\tableTypes\aTable;

class ReadTableActions extends Actions
{
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
            throw new errorException('У вас нет доступа для csv-выкрузки');
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

            $clc = new CalculcateFormat($field['format']);
            $tbl = $this->Table->getTbl();
            $item = $tbl['params'];
            if ($field['category'] === 'column') {
                $this->Table->checkIsUserCanViewIds('web', [$this->post['id']]);
                $item = $this->Table->getTbl()["rows"][$this->post['id']];
            }

            $result = $clc->getPanelFormat(
                $field['name'],
                $item,
                $tbl,
                $this->Table
            );
        }
        return ['panelFormats' => $result];
    }

    public function getValue()
    {
        $data = json_decode($this->post['data'], true) ?? [];

        if (empty($data['fieldName'])) {
            throw new errorException('Не задано имя поля');
        }
        if (empty($field = $this->Table->getVisibleFields('web')[$data['fieldName']])) {
            throw new errorException('Доступ к полю запрещен');
        }
        if (empty($data['rowId']) && $field['category'] === 'column') {
            throw new errorException('Не задана строка');
        }

        if (!empty($data['rowId'])) {
            $loadFilteredRows = $this->Table->loadFilteredRows('web', [$data['rowId']]);
            if ($loadFilteredRows && $row = ($this->Table->getTbl()['rows'][$data['rowId']] ?? null)) {
                $val = $row[$field['name']];
            }
        } else {
            $row = $this->Table->getTbl()['params'];
            $val = $row[$field['name']] ?? null;
        }

        if (!isset($val)) {
            throw new errorException('Ошибка доступа к полю');
        }
        if (is_string($val)) {
            $val = json_decode($val, true);
        }

        return ['value' => Field::init($field, $this->Table)->getFullValue($val['v'], $data['rowId'] ?? null)];
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
                if (key_exists('cycle_id', $data['env'])) {
                    $Table = $this->Totum->getTable($data['env']['table'], $data['env']['cycle_id']);
                } elseif (key_exists('hash', $data['env'])) {
                    $Table = $this->Totum->getTable($data['env']['table'], $data['env']['hash']);
                } else {
                    $Table = $this->Totum->getTable($data['env']['table']);
                }

                $row = [];
                if (key_exists('id', $data['env'])) {
                    if ($Table->loadFilteredRows('inner', [$data['env']['id']])) {
                        $row = $Table->getTbl()['rows'][$data['env']['id']];
                    }
                }


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
                throw new errorException('Ошибка интерфейса - выбрана несуществующая кнопка');
            }
        } else {
            throw new errorException('Предложенный выбор устарел.');
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
                        $item = $this->Table->getTbl()["rows"][$row['id']];
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
            throw new errorException('Предложенный выбор устарел.');
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
            if (key_exists('cycle_id', $data['env'])) {
                $Table = $this->Totum->getTable($data['env']['table'], $data['env']['cycle_id']);
            } elseif (key_exists('hash', $data['env'])) {
                $Table = $this->Totum->getTable($data['env']['table'], $data['env']['hash']);
            } else {
                $Table = $this->Totum->getTable($data['env']['table']);
            }

            $row = [];
            if (key_exists('id', $data['env'])) {
                if ($Table->loadFilteredRows('inner', [$data['env']['id']])) {
                    $row = $Table->getTbl()['rows'][$data['env']['id']];
                }
            }

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
            throw new errorException('Код обработки устарел.');
        }
        return ['ok' => 1];
    }

    public function loadPage()
    {
        if (key_exists('offset', $this->post) && !is_null($this->post['offset']) && $this->post['offset'] !== "") {
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
            ["params" => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );
        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }

        $data = $this->Table->getSortedFilteredRows('web', 'web', [], $lastId, $prevLastId, $onPage);
        $rows = [];
        foreach ($data['rows'] as $row) {
            $rows[] = $this->Table->getTbl()['rows'][$row['id']];
        }
        $data['f'] = $this->getTableFormat($rows);


        return $data;
    }


    public function loadPreviewHtml()
    {
        $data = json_decode($this->post['data'], true);

        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null)) {
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');
        }

        if (!in_array($field['type'], ['select'])) {
            throw new errorException('Ошибка - поле не типа select');
        }

        $this->Table->loadDataRow();
        $row = $data['item'];

        if ($field['category'] === 'column' && !isset($row['id'])) {
            $row['id'] = null;
        }
        foreach ($row as $k => &$v) {
            if (key_exists($k, $fields)) {
                if ($fields[$k]['type'] === 'date' && $v && $v = Calculate::getDateObject($v)) {
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

        return ['previews' => $Field->getPreviewHtml($data['val'], $row, $this->Table->getTbl())];
    }

    public function refresh()
    {
        $result = [];
        if ($this->post['recalculate'] ?? false) {
            try {
                $inVars = ['calculate' => aTable::CALC_INTERVAL_TYPES['changed'], 'channel' => 'web',
                    'modify' => ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]];
                $this->Totum->transactionStart();
                $this->Table->reCalculateFromOvers($inVars);
                $this->Totum->transactionCommit();
            } catch (errorException $e) {
                $error = $e->getMessage();
                if ($this->Totum->getUser()->isCreator()) {
                    $error .= ' <br/> ' . $e->getPathMess();
                    $result['error'] = $error;
                }
                $this->Totum->transactionRollback();
                throw new criticalErrorException($e->getMessage());
            }
        } else {
            $this->Table->reCalculateFilters(
                'web',
                false,
                false,
                ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
            );
        }


        switch ($pageViewType = $this->getPageViewType()) {
            case 'tree':
                $result += ['chdata' => $this->addValuesAndFormats(['params' => $this->Table->getTbl()['params']])];
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
                        [""]
                    )
                );
                break;
            default:
                $result += ['chdata' => $this->getTableClientData(
                    !empty($this->post['withoutIds']) ? null : json_decode($this->post['ids'], true),
                    $this->post['onPage'] ?? null,
                    false
                )];

                if ($pageViewType === 'panels' && $this->Table->getTableRow()['with_order_field']) {
                    $result['chdata']['nsorted_ids'] = array_column($result['chdata']['rows'], 'id');
                } elseif ($pageViewType === 'paging') {
                    $params = $this->Table->filtersParamsForLoadRows('web');
                    $result['allCount'] = $params === false ? 0 : $this->Table->countByParams($params);
                }
        }


        $result['updated'] = $this->Table->getUpdated();
        $result['refresh'] = true;

        $result['chdata']['rows'] = array_combine(
            array_column($result['chdata']['rows'], 'id'),
            $result['chdata']['rows']
        );
        if ($this->Table->getTableRow()['new_row_in_sort']) {
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
        $tableAll = ['<h1>' . $this->Table->getTableRow()['title'] . '</h1>'];

        $sosiskaMaxWidth = $settings['sosiskaMaxWidth'];
        $fields = array_intersect_key($this->Table->getFields(), $settings['fields']);

        $fieldNames = array_keys($fields);

        $ids = $this->Table->loadFilteredRows('web', $settings['ids'] ?? []);
        $data = ['params' => $this->Table->getTbl()['params'], 'rows' => []];
        foreach ($settings['ids'] as $id) {
            if (in_array($id, $ids)) {
                $data['rows'][$id] = $this->Table->getTbl()['rows'][$id];
            }
        }
        $result = $this->Table->getValuesAndFormatsForClient($data, 'print', $fieldNames);


        $getTdTitle = function ($field, $withWidth = true) {
            $title = htmlspecialchars($field['title']);
            if (!empty($field['unitType'])) {
                $title .= ', ' . $field['unitType'];
            }

            return '<td'
                . ($withWidth ? ' style="width: ' . $field['width'] . 'px;"' : '')
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
                        $tr .= '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $row[$field['name']]['v'] . '</span></td>';
                    }
                }
                $tr .= '</tr>';
                $table['body'][] = $tr;
            }


            if ($columnFooters = array_filter(
                $fields,
                function ($field) use ($fields) {
                    if ($field['category'] === 'footer' && $field['column'] && array_key_exists(
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


        $table = [];
        $width = 0;


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
                $this->Totum->transactionCommit();
            } catch (errorException $e) {
                $error = $e->getMessage();
                if ($this->Totum->getUser()->isCreator()) {
                    $error .= ' <br/> ' . $e->getPathMess();
                }
                $this->Totum->transactionRollback();
                throw new criticalErrorException($e->getMessage());
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
                $panelViewSettings = $this->Table->getTableRow()['panels_view'];


                $fields = array_column($panelViewSettings['fields'], 'field');

                if ($panelViewSettings['kanban'] && $kanban = $this->Table->getFields()[$panelViewSettings['kanban']]) {
                    if (!in_array($panelViewSettings['kanban'], $fields)) {
                        $fields[] = $panelViewSettings['kanban'];
                    }
                    $result["kanban"] = [];
                    $results = Field::init($kanban, $this->Table)->calculateSelectList(
                        $val,
                        [],
                        $this->Table->getTbl()
                    );
                    unset($results['previewdata']);

                    if ($kanban['withEmptyVal'] ?? false) {
                        $result["kanban"][] = ["", $kanban['withEmptyVal']];
                    }

                    foreach ($results as $k => $v) {
                        if (!$v[1]) {
                            $result["kanban"][] = [$k, $v[0]];
                        }
                    }
                }
                foreach ($result['fields'] as $k => $v) {
                    if ($v['category'] === 'column' && !in_array($k, $fields)) {
                        unset($result['fields'][$k]);
                    }
                }

                $result = array_merge(
                    $result,
                    $this->getTableClientData(
                        0,
                        null,
                        false,
                        $fields
                    )
                );
                break;
            case 'tree':
                $tree = $this->Table->getFields()['tree'];
                $result = array_merge(
                    $result,
                    $this->getTreeTopLevel($tree['treeViewLoad'] ?? null, $tree['treeViewOpen'] ?? null)
                );
                break;
            case 'paging':
                $result = array_merge($result, $this->getTableClientData(0, 0, false));
                break;
            case 'commonByCount':
                /*For off button on table head*/
                $result['panels'] = "off";
            // no break
            default:
                $result = array_merge($result, $this->getTableClientData(0, null, false));

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
            $rows = array_values($rows);
            $result['rows'] = array_merge($rows, $rows_other);
            unset($result['f']['order']);
        }

        return $result;
    }

    protected function getPageViewType(): string
    {
        if ($this->Request->getQueryParams()['iframe'] ?? false) ; elseif (($panelViewSettings = ($this->Table->getTableRow()['panels_view'] ?? null))
        ) {
            if (($this->post['panelsView'] ?? false) === 'true') {
                return 'panels';
            } elseif (empty($this->post)) {
                $allCount = $this->Table->countByParams($this->Table->filtersParamsForLoadRows('web'));
                if ($allCount <= ($panelViewSettings["panels_max_count"] ?? 100)) {
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
        if (($tree = $this->Table->getFields()['tree'] ?? null)
            && $tree['category'] === 'column'
            && $tree['type'] === 'tree'
            && !empty($tree['treeViewType'])) {
            return 'tree';
        }
        if (($this->Table->getTableRow()['pagination'] ?? '0/0') !== '0/0') {
            return 'paging';
        }

        return 'common';
    }

    protected function addValuesAndFormats($data)
    {
        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS']);
        {
            $f = $this->getTableFormat($data['rows'] ?? []);
            $data = $this->Table->getValuesAndFormatsForClient($data, 'web');
            $data['f'] = $f;
        }
        $this->Table->calcLog($Log, 'result', 'done');

        return $data;
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
        $data['rows'] = [];

        if (method_exists($this->Table, 'withoutNotLoaded')) {
            $this->Table->withoutNotLoaded();
        }

        if (is_null($onPage)) {
            $data['rows'] = $this->Table->getSortedFilteredRows(
                'web',
                'web',
                [],
                null,
                null,
                null,
                $onlyFields
            )['rows'];
        } elseif ($onPage > 0) {
            $data['rows'] = $this->Table->getSortedFilteredRows(
                'web',
                'web',
                [],
                $pageIds,
                0,
                $onPage,
                $onlyFields
            )['rows'];
        }

        $result = $this->addValuesAndFormats(['params' => $this->Table->getTbl()['params']]);
        $result['rows'] = $data['rows'];

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

                        if ($table['type'] !== 'calcs') {
                            $fields[$f['name']]['linkToSelectTable'] = ['link' => $this->modulePath . $table['top'] . '/' . $table['id'], 'title' => $table['title']];
                        } else {
                            $topTable = $this->Totum->getTableRow($table['tree_node_id']);
                            $fields[$f['name']]['linkToSelectTable'] =
                                ['link' => $this->modulePath . $topTable['top'] . '/' . $topTable['id'] . '/' . $this->Cycle->getId() . '/' . $table['id']
                                    , 'title' => $table['title']
                                ];
                        }
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
        $result['tableRow'] = $this->tableRowForClient($this->Table->getTableRow());
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
            throw new errorException('Ид пуст');
        }
        $this->Table->loadDataRow();
        if ($this->Table->loadFilteredRows('web', [$id])) {
            $res['row'] = $this->Table->getValuesAndFormatsForClient(
                ['rows' => [$this->Table->getTbl()['rows'][$id]]],
                'edit'
            )['rows'][0];
            $res['f'] = $this->getTableFormat([]);
            return $res;
        } else {
            throw new errorException('Строка недоступна для просмотра');
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

        if ($click = is_string($this->post['data']) ? (json_decode(
            $this->post['data'],
            true
        ) ?? []) : $this->post['data']) {
            if ($click['item'] === 'params') {
                $row = $this->Table->getTbl()['params'];
            } elseif ($click['item'][0] === 'i') {
                $row = TmpTables::init($this->Totum->getConfig())->getByHash(
                    '_insert_row',
                    $this->User,
                    $click['item']
                );
                if (is_null($row)) {
                    throw new errorException('Строка добавления устрарела');
                }
                $this->Table->setInsertRowHash($click['item']);
                foreach ($row as &$v) {
                    $v = ['v' => $v];
                }
            } else {
                /*Проверка не заблокирована ли строка для пользователя*/
                $ids = $this->Table->loadFilteredRows('web', [$click['item']]);
                if (!$ids || !($row = $this->Table->getTbl()['rows'][$click['item']] ?? null) || !empty($row['is_del'])) {
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
                }
            }

            try {
                $fields = $this->Table->getVisibleFields('web');
                /* Проверка доступа к нажатию кнопки */
                if (!key_exists($click['fieldName'], $fields)) {
                    throw new errorException('Поле недоступно для пользователя');
                } elseif (get_class($this) === ReadTableActions::class && empty($fields[$click['fieldName']]['pressableOnOnlyRead'])) {
                    throw new errorException('Кнопка недоступна для нажатия в режиме "только для чтения"');
                }
                $this->clickToButton($fields[$click['fieldName']], $row, ['ids' => $click['checked_ids'] ?? []]);
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
        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null)) {
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');
        }

        $this->Table->loadDataRow();
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
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
        if ($field['category'] === 'column') {
            if (array_key_exists('id', $row) && !is_null($row['id'])) {
                $this->Table->loadFilteredRows('web', [$row['id']]);
                $row = $row + $this->Table->getTbl()['rows'][$row['id']] ?? [];
            } else {
                $row = $row + $this->Table->checkInsertRow([], $data['item'], null, []);
            }
        } else {
            $row = $row + $this->Table->getTbl()['params'];
        }


        if (!in_array(
            $field['type'],
            ['select', 'tree']
        )) {
            throw new errorException('Ошибка - поле не типа select/tree');
        }

        /** @var Select $Field */
        $Field = Field::init($field, $this->Table);

        $list = $Field->calculateSelectList($row[$field['name']], $row, $this->Table->getTbl());

        return $Field->cropSelectListForWeb($list, $row[$field['name']]['v'], $q, $parentId);
    }


    public function edit()
    {
        $data = is_string($this->post['data']) ? json_decode($this->post['data'], true) : $this->post['data'];

        $filterFields = $this->Table->getVisibleFields('web', true)['filter'] ?? [];
        $filters = array_intersect_key($data["params"] ?? [], $filterFields);

        if ($filters) {
            return $this->editFilters($filters, $data["setValuesToDefaults"] ?? false);
        } elseif (!is_a($this, WriteTableActions::class)) {
            throw new errorException('Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений');
        } else {
            $clearFields = $data["params"] ?? [];
            if ($filters) {
                $clearFields = array_diff_key($clearFields, $filters);
            }
            $data["params"] = $clearFields;

            return $this->modify(['modify' => $data, "setValuesToDefaults" => $data["setValuesToDefaults"] ?? false]);
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
        $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS']);
        $changedData = $this->Table->getValuesAndFormatsForClient($filters, 'web');

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
            throw new errorException('Поле [[' . $field . ']] в этой таблице не найдено');
        }
        if (empty($fields[$field]['showInWeb']) || (!empty($fields[$field]['logRoles']) && !array_intersect(
            $fields[$field]['logRoles'],
            $this->User->getRoles()
        ))) {
            throw new errorException('Доступ к логам запрещен');
        }


        $logs = $this->Totum->totumActionsLogger()->getLogs(
            $this->Table->getTableRow()['id'],
            $this->Table->getCycle() ? $this->Table->getCycle()->getId() : null,
            $id,
            $field
        );

        $title = 'Лог ручных изменений по полю "' . $fields[$field]['title'] . '"';
        if ($id) {
            $title .= ' id ' . $id;
            if ($rowName) {
                $title .= ' "' . $rowName . '""';
            }
        }

        if (empty($logs)) {
            $this->Table->getTotum()->addToInterfaceDatas(
                'text',
                ['title' => $title, 'width' => '500', 'text' => 'Ручных изменений по полю не производилось']
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

        foreach ($fields as &$field) {
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

            foreach (Totum::FIELD_CODE_PARAMS as $param) {
                if (!empty($field[$param])) {
                    $field[$param] = true;
                }
            }

            if (!is_a($this, WriteTableActions::class)
                && !empty($field['CodeActionOnClick'])
                && empty($field['clickableOnOnlyRead'])) {
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
                    ['is_del' => false],
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
    protected function tableRowForClient($tableRow)
    {
        $fields = ['title', 'updated', 'type', 'id', 'tree_node_id', 'sess_hash', 'description', 'fields_sets', 'panel', 'order_field',
            'order_desc', 'fields_actuality', 'with_order_field', 'main_field', 'delete_timer', '__version', 'pagination',
            'panels_view', 'new_row_in_sort', 'rotated_view', 'deleting'];
        if ($this->User->isCreator()) {
            $fields = array_merge(
                $fields,
                [
                    'name', 'sort', 'actual'
                ]
            );
        } else {
            $tableRow['description'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $tableRow['description']);
        }
        $_tableRow = array_intersect_key($tableRow, array_flip($fields));

        if (($tableRow['panels_view'] ?? null) && $tableRow['panels_view']['state'] === 'panel') {
            $_tableRow['panels_view'] = $tableRow['panels_view'];
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
        }
        $_tableRow['description'] = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $_tableRow['description']);

        return $_tableRow;
    }

    public function dblClick()
    {
        $id = (int)($this->post['id'] ?? 0);
        $field = $this->post['field'] ?? '';


        if ($field && key_exists(
            $field,
            $this->Table->getFields()
        ) && (($fieldParams = $this->Table->getFields()[$field])['CodeActionOnClick'] ?? false)) {
            if (!is_a($this, WriteTableActions::class) && empty($fieldParams['clickableOnOnlyRead'])) {
                throw new errorException('Действие недоступно при просмотре');
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
            $this->clickToButton($fieldParams, $row, [], 'click');
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

    protected function getTableFormat($rows)
    {
        $tFormat = [];
        if ($this->Table->getTableRow()['table_format'] && $this->Table->getTableRow()['table_format'] != 'f1=:') {
            $Log = $this->Table->calcLog(['name' => 'Table format']);

            $calc = new CalculcateFormat($this->Table->getTableRow()['table_format']);
            $tFormat = $calc->getFormat(
                'TABLE',
                [],
                $this->Table->getTbl(),
                $this->Table,
                ['rows' => array_values($rows)]
            );
            $this->Table->calcLog($Log, 'result', $tFormat);
        }
        return $tFormat;
    }

    protected function modify($data)
    {
        $tableData = $this->post['tableData'] ?? [];
        $data['modify']['params'] = array_merge(
            $data['modify']['params'] ?? [],
            $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')
        );
        $this->Table->checkAndModify($tableData, $data);

        return $this->getTableClientChangedData($data);
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

            $Log = $this->Table->calcLog(['name' => 'SELECTS AND FORMATS']);
            $pageIds = json_decode($this->post['ids'], true);

            $return['chdata']['rows'] = [];

            $changedIds = $this->Table->getChangeIds();

            if ($changedIds['added']) {
                $return['chdata']['rows'] = array_intersect_key(
                    $this->Table->getTbl()['rows'],
                    $changedIds['added']
                );
            }

            if ($changedIds['deleted']) {
                $return['chdata']['deleted'] = array_keys($changedIds['deleted']);
            }
            if ($changedIds['restored']) {
                $return['chdata']['deleted'] = array_keys($changedIds['restored']);
            }


            $modify = $data['modify'] ?? [];
            unset($modify['params']);
            $sortedVisibleFields = $this->Table->getVisibleFields('web', true);


            if ($changedIds['changed'] += ($modify ?? [])) {
                //Подумать - а не дублируется ли с тем блоком, что ниже
                $selectOrFormatColumns = [];
                foreach ($sortedVisibleFields['column'] as $k => $v) {
                    if ((($v['type'] === 'select' || $v['type'] === 'tree') && !empty($v['codeSelectIndividual'])) || !empty($v['format'])) {
                        $selectOrFormatColumns[$k] = true;
                    }
                }
                $tbl = $this->Table->getTbl();
                foreach ($changedIds['changed'] as $id => $changes) {
                    if (empty($tbl['rows'][$id]) || !in_array($id, $pageIds)) {
                        continue;
                    }

                    if (empty($changes)) {
                        $return['chdata']['rows'][$id] = $tbl['rows'][$id];
                        continue;
                    }
                    $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key(
                        $tbl['rows'][$id],
                        $changes
                    ) + array_intersect_key($tbl['rows'][$id], $selectOrFormatColumns);
                    foreach ($changes as $k => $null) {
                        if (is_array($return['chdata']['rows'][$id][$k])) {
                            $return['chdata']['rows'][$id][$k]['changed'] = true;
                        }
                    }
                }
            }


            //Отправка на клиент селектов и форматов
            {
                $selectOrFormatColumns = [];
                foreach ($sortedVisibleFields['column'] as $k => $v) {
                    if (in_array($v['type'], ['select', 'tree']) || !empty($v['format'])) {
                        $selectOrFormatColumns[] = $k;
                    }
                }

                $pageIds = $this->Table->loadFilteredRows('web', $pageIds ?? []);

                if ($selectOrFormatColumns && !empty($pageIds)) {
                    $selectOrFormatColumns[] = 'id';
                    $selectOrFormatColumns = array_flip($selectOrFormatColumns);

                    $rows = $this->Table->getTbl()['rows'];
                    foreach ($pageIds as $id) {
                        if (!empty($rows[$id])) {
                            $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key(
                                $rows[$id],
                                $selectOrFormatColumns
                            );
                        }
                    }
                }
            }

            if ($this->getPageViewType() === 'tree' && $this->Table->getFields()['tree']['treeViewType'] === 'self') {
                $return['chdata']['tree'] = $this->getResultTree(
                    function ($k, $v) {
                        return 'child';
                    },
                    [""],
                    true
                )['tree'];
            }


            $return['chdata']['params'] = $this->Table->getTbl()['params'];
            $return['chdata']['f'] = $this->getTableFormat(array_intersect_key(
                $this->Table->getTbl()['rows'],
                array_flip($pageIds)
            ));

            if (!empty($return['chdata']['rows'])) {
                foreach ($return['chdata']['rows'] as $id => &$row) {
                    $row['id'] = $id;
                }
                unset($row);
            }

            $return['chdata']['params'] = $return['chdata']['params'] ?? [];

            $return['chdata'] = $this->Table->getValuesAndFormatsForClient($return['chdata'], 'web');

            if (empty($return['chdata']['params'])) {
                unset($return['chdata']['params']);
            }


            if ($this->Table->getTableRow()['pagination'] && $this->Table->getTableRow()['pagination'] !== '0/0') {
                $params = $this->Table->filtersParamsForLoadRows('web');
                $return['allCount'] = $params === false ? 0 : $this->Table->countByParams($params);
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
    protected function getPermittedFilters($filtersString)
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
        $result = $this->addValuesAndFormats(['params' => $this->Table->getTbl()['params']]);

        $result = array_merge(
            $result,
            $this->getResultTree(
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
            )
        );
        $result['filtersString'] = $this->getFiltersString();
        return $result;
    }

    protected function getResultTree($filterFunc, $loadingIds, $onlyTree = false)
    {
        $Tree = Field::init($this->Table->getFields()['tree'], $this->Table);
        $val = ["v" => null];

        $Tree->clearCachedLists();
        $list = $Tree->calculateSelectList($val, [], $this->Table->getTbl());


        $bids = [];
        $tree = [];
        $thisNodes = [];
        if (!is_array($loadingIds)) {
            if ($Tree->getData('treeViewType') !== 'self' && !is_null($t = $Tree->getData('withEmptyVal'))) {
                $tree[] = ['v' => null, 't' => $t];
            }
            $bids[] = "";
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

        if ($bids && !$onlyTree) {
            $treeIds = $this->Table->getByParams(
                (new FormatParamsForSelectFromTable)
                    ->where('tree', $bids)
                    ->field('id')
                    ->params(),
                'list'
            );
            if ($this->Table->getFields()['tree']['treeViewType'] === 'other'
                && key_exists('tree_category', $this->Table->getFields())) {
                $treeIds = array_merge(
                    $treeIds,
                    $this->Table->getByParams(
                        (new FormatParamsForSelectFromTable)
                            ->where('tree_category', $bids)
                            ->where('tree_category', "", "!=")
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
                        break;
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
                    foreach ($list as $k => $v) {
                        if (key_exists($k, $treeBranches)) {
                            switch ($filterFunc($k, $v, $thisNodes)) {
                                case 'this':
                                case 'parent':
                                    $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => true, 'p' => $v[3]];
                                    break;
                                case 'closed':
                                    $tree[] = ['v' => $k, 't' => $v[0], 'l' => true, 'opened' => false, 'p' => $v[3]];
                                    break;
                                case 'loaded':
                                case 'child':
                                    $tree[] = ['v' => $k, 't' => $v[0], 'p' => $v[3]];
                                    break;
                            }
                        }
                    }
                }
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
                    } elseif (in_array($k, $parentIds)) {
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
                    } elseif (in_array($v[3] ?? null, $branchIds) || in_array($v[3] ?? null, $parentIds)) {
                        return 'child';
                    }
                },
                $branchIds
            );
        } else {
            throw new errorException('Ошибка получения Id ветки');
        }
    }
}
