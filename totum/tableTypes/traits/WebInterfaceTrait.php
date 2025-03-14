<?php


namespace totum\tableTypes\traits;

use totum\common\calculates\CalculateFormat;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\models\TmpTables;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;

trait WebInterfaceTrait
{
    protected $insertRowSetData;

    public function changeFieldsSets($func = null)
    {
        if ($this->getTableRow()['type'] === 'calcs') {
            $tableVersions = $this->getTotum()->getTable('calcstable_versions');
            $vIdSet = $tableVersions->getByParams(
                ['where' => [['field' => 'table_name', 'operator' => '=', 'value' => $this->getTableRow()['name']],
                    ['field' => 'version', 'operator' => '=', 'value' => $this->getTableRow()['__version']]], 'field' => ['id', 'fields_sets']],
                'row'
            );
            $set = $vIdSet['fields_sets'] ?? [];

            if ($func) {
                $set = $func($set);
                $tableVersions->reCalculateFromOvers([
                    'modify' => [$vIdSet['id'] => ['fields_sets' => $set]]
                ]);
            }
        } else {
            $set = $this->getTableRow()['fields_sets'];
            $tableTables = $this->getTotum()->getTable('tables');
            if ($func) {
                $set = $func($set);
                $tableTables->reCalculateFromOvers([
                    'modify' => [$this->getTableRow()['id'] => ['fields_sets' => $set]]
                ]);
            }
        }
        return $set;
    }

    public function csvImport($tableData, $csvString, $answers, $visibleFields, $type)
    {
        $this->checkTableUpdated($tableData);
        $import = [];

        if ($errorAndQuestions = $this->prepareCsvImport($import, $csvString, $answers, $visibleFields, $type)) {
            return $errorAndQuestions;
        }
        $table = ['ok' => 1];

        $this->reCalculate(
            ['channel' => 'web', 'modifyCalculated' => ((int)($import['codedFields'] ?? null) === 2 ? 'all' : 'handled')
                , 'add' => ($import['add'] ?? [])
                , 'modify' => ($import['modify'] ?? [])
                , 'remove' => ($import['remove'] ?? [])
            ]
        );
        $oldUpdated = $this->updated;
        $this->isTblUpdated(0, function () {
        });
        if ($oldUpdated !== $this->updated) {
            $table['updated'] = $this->updated;
        }
        return $table;
    }

    public function csvExport($tableData, $idsString, $visibleFields, $type = 'full')
    {
        $this->checkTableUpdated($tableData);

        if ($idsString && $idsString !== '[]') {
            $ids = json_decode($idsString, true);
            if ($this->sortedFields['filter']) {
                $this->reCalculate();
            }
            $ids = $this->loadFilteredRows('web', $ids);

            $oldRows = $this->tbl['rows'];
            $this->tbl['rows'] = [];

            foreach ($ids as $id) {
                $this->tbl['rows'][$id] = $oldRows[$id];
            }
        }
        foreach ($this->filtersFromUser as $f => $val) {
            $this->tbl['params'][$f] = ['v' => $val];
        }
        $csv = $this->getCsvArray($visibleFields, $type);

        ob_start();
        $out = fopen('php://output', 'w');
        foreach ($csv as $fields) {

            fputcsv($out, $fields, ';', '"', '\\');
        }
        fclose($out);

        return ['csv' => ob_get_clean()];
    }

    public function checkAndModify($tableData, array $data)
    {
        $inVars = [];

        $remove = $data['remove'] ?? [];
        $restore = $data['restore'] ?? [];

        $duplicate = $data['duplicate'] ?? [];
        $reorder = $data['reorder'] ?? [];

        $refresh = $data['refresh'] ?? [];

        $this->checkTableUpdated($tableData);


        $inVars['add'] = [];
        if (!empty($data['add'])) {
            if ($data['add'] === 'new cycle') {
                $inVars['add'] = [[]];
            } elseif (is_array($data['add'])) {
                $inVars['add'] = [$data['add']];
            } elseif ($insertRowHash = $data['add']) {
                $this->insertRowSetData = TmpTables::init($this->getTotum()->getConfig())->getByHash(
                    TmpTables::SERVICE_TABLES['insert_row'],
                    $this->getUser(),
                    $data['add']
                );
                $inVars['add'] = [[]];
            }
        }

        $inVars['channel'] = $data['channel'] ?? 'web';

        $inVars['setValuesToDefaults'] = $data['setValuesToDefaults'] ?? [];

        $inVars['modify'] = $data['modify'] ?? [];
        $inVars['remove'] = $remove;
        $inVars['restore'] = $restore;


        $inVars['duplicate'] = $duplicate;
        if ($inVars['duplicate']) {
            foreach ($inVars['duplicate']['replaces'] ?? [] as $replaces) {
                foreach ($replaces ?? [] as $k => $v) {
                    if (!$this->isField('visible',
                            'web',
                            $k) || $this->getFields()[$k]['type'] !== 'unic') {
                        throw new errorException($this->translate('Access to edit %s field is denied', $k));
                    }
                }
            }
        }


        $inVars['reorder'] = $reorder;

        if (!empty($data['addAfter'])) {
            $inVars['addAfter'] = $data['addAfter'];
        }


        $inVars['calculate'] = aTable::CALC_INTERVAL_TYPES['changed'];
        if ($refresh) {
            $inVars['modify'] = $inVars['modify'] + array_flip($refresh);
        }
        foreach ($inVars['modify'] as $itemId => &$editData) {//Для  saveRow
            if ($itemId === 'params') {
                continue;
            }
            if (!is_array($editData)) {//Для  refresh
                $editData = [];
                continue;
            }

            foreach ($editData as $k => &$v) {
                if (is_array($v) && array_key_exists('v', $v)) {
                    if (array_key_exists('h', $v)) {
                        if ($v['h'] === false) {
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

        $Log = $this->calcLog(['name' => 'RECALC', 'table' => $this, 'inVars' => $inVars]);

        $this->reCalculate($inVars);

        if (!empty($insertRowHash)) {
            TmpTables::init($this->getTotum()->getConfig())->deleteByHash(
                TmpTables::SERVICE_TABLES['insert_row'],
                $this->User,
                $insertRowHash
            );
        }
        $isUpdated = $this->isTblUpdated(0, function ($result) use ($Log) {
            $this->calcLog($Log, 'result', $result !== false ? ['changed', $result] : 'not changed');
        });
        $this->calcLog($Log, 'result', $isUpdated !== false ? ['changed', $isUpdated] : 'not changed');
    }

    public function checkEditRow($editData, $tableData = null)
    {
        $data = $dataSetToDefault = [];
        foreach ($editData as $k => $v) {
            if (is_array($v) && array_key_exists('v', $v)) {
                if (array_key_exists('h', $v)) {
                    if ($v['h'] === false) {
                        $dataSetToDefault[$k] = true;
                        continue;
                    }
                }
                $data[$k] = $v['v'];
            }
        }
        $this->loadDataRow();
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }
        $id = $editData['id'] ?? 0;
        $this->checkIsUserCanViewIds('web', [$id]);
        $this->reCalculate(['channel' => 'web', 'modify' => [$id => $data], 'setValuesToDefaults' => [$id => $dataSetToDefault], 'isCheck' => true]);

        if (empty($this->tbl['rows'][$id])) {
            throw new errorException($this->translate('Row not found'));
        }
        return $this->tbl['rows'][$id];
    }


    protected function prepareCsvImport(&$import, $csvString, $answers, $visibleFields = [], $type = 'full')
    {
        $import['modify'] = [];
        $import['add'] = [];
        $import['remove'] = [];

        $NotCorrectFormat = $this->translate('Wrong format file') . ': ';
        $question = [];
        $checkQuestion = function ($number, $isTrue, $text) use ($answers, &$question) {
            if (!isset($answers[$number]) && $isTrue) {
                $question = ['question' => [$number, $text]];
                return true;
            }
            return false;
        };
        $getCsvVal = function ($val, $field) {
            return Field::init($field, $this)->getValueFromCsv($val);
        };


        $csvString = stream_get_contents(fopen($csvString, 'r'));

        if (substr($csvString, 0, 3) === pack('CCC', 0xef, 0xbb, 0xbf)) {
            $csvString = substr($csvString, 3);
        } else {
            if (!mb_check_encoding($csvString, 'utf-8') && mb_check_encoding($csvString, 'windows-1251')) {
                $csvString = mb_convert_encoding($csvString, 'utf-8', 'windows-1251');
            }
            if (!mb_check_encoding($csvString, 'utf-8')) {
                return ['error' => $this->translate('Incorrect encoding of the file (should be utf-8 or windows-1251)')];
            }
        }


        $csvString = str_replace("\r\n", PHP_EOL, $csvString);
        $csvArray = [];

        foreach (explode(PHP_EOL, $csvString) as $row) {
            $row = str_getcsv(trim($row), ';', '"', '\\');
            foreach ($row as &$c) {
                $c = trim($c);
            }
            $csvArray[] = $row;
        }
        unset($row);


        $sortedVisibleFields = $this->getVisibleFields('web', true);

        switch ($type) {
            case 'full':

                $rowNumName = 0;
                $rowNumCodes = 1;
                $rowNumProject = 2;
                $rowNumSectionHandl = 4;
                $rowNumSectionHeader = 8;
                $rowNumFilter = 13;
                $rowNumSectionRows = 16;

//Проверка та ли таблица
                if ($checkQuestion(
                    1,
                    $csvArray[$rowNumName][0] !== $this->tableRow['title'],
                    $this->translate('Loading file of table %s into table [[%s]]',
                        [$csvArray[$rowNumName][0], $this->tableRow['title']])
                )) {
                    return $question;
                }

//Не была ли таблица изменена
                if (!isset($csvArray[$rowNumCodes][1]) || !preg_match(
                        '/^code:(\d+)$/',
                        $csvArray[$rowNumCodes][1],
                        $matchCode
                    )
                ) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumCodes + 1) . ' ' . $this->translate('no table change code')];
                } else {
                    $updated = json_decode($this->updated, true);

                    if ($checkQuestion(2,
                        $matchCode[1] !== (string)$updated['code'],
                        $this->translate('Table was changed'))) {
                        return $question;
                    }
                }
//Не была ли  изменена структура
                if (!isset($csvArray[$rowNumCodes][2]) || !preg_match(
                        '/^structureCode:(\d+)$/',
                        $csvArray[$rowNumCodes][2],
                        $matchCode
                    )
                ) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumCodes + 1) . ' ' . $this->translate('no structure change code')];
                } elseif ($checkQuestion(
                    3,
                    $matchCode[1] !== (string)$this->getStructureUpdatedJSON()['code'],
                    $this->translate('The structure of the table was changed. Possibly a field order mismatch.')
                )) {
                    return $question;
                }

//Тот ли проект
                if (!isset($csvArray[$rowNumProject][0]) || !preg_match(
                        '/^(\d+|' . $this->translate('Out of cycles') . ')$/',
                        $csvArray[$rowNumProject][0],
                        $matchCode
                    )
                ) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumProject + 1) . ' ' . $this->translate('no indication of a cycle')];
                } elseif ($checkQuestion(
                    4,
                    strval(isset($this->Cycle) && $this->Cycle->getId() ? $this->Cycle->getId() : $this->translate('Out of cycles')) !== $matchCode[1],
                    $this->translate('Table from another cycle or out of cycles')
                )) {
                    return $question;
                }


//Ручные значения
                if (($string = $csvArray[$rowNumSectionHandl][0] ?? '') !== $this->translate('Manual Values')) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumSectionHandl + 1) . ' ' . $this->translate('no section header %s',
                            $this->translate('Manual Values'))];
                }
                if (!in_array(
                    ($string = strtolower($csvArray[$rowNumSectionHandl + 2][0] ?? '')),
                    [0, 1, 2]
                )
                ) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumSectionHandl + 1) . ' ' . $this->translate('no 0/1/2 edit switch')];
                }
                $import['codedFields'] = $string;

//Хэдер
                if (($string = $csvArray[$rowNumSectionHeader][0] ?? '') !== $this->translate('Header')) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumSectionHeader + 1) . ' ' . $this->translate('no section header %s',
                            $this->translate('Header'))];
                }
                $headerFields = $csvArray[$rowNumSectionHeader + 2];

                foreach ($headerFields as $i => $fieldName) {
                    if (!$fieldName) {
                        continue;
                    }
                    if (($field = $this->fields[$fieldName]) && !in_array($field['type'], ['comments', 'button'])) {
                        if ($import['codedFields'] === '0' && !empty($field['code']) && empty($field['codeOnlyInAdd'])) ; else {
                            $import['modify']['params'][$field['name']] = $getCsvVal(
                                $csvArray[$rowNumSectionHeader + 3][$i],
                                $field
                            );
                        }
                    }
                }

//Фильтр
                if (($string = $csvArray[$rowNumFilter][0] ?? '') !== $this->translate('Filter')) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumFilter + 1) . ' ' . $this->translate('no section header %s',
                            $this->translate('Filter'))];
                }
                if (!empty($sortedVisibleFields['filter'])) {
                    if (empty($filterData = $csvArray[$rowNumFilter + 1][0])) {
                        return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                                $rowNumFilter + 2) . ' ' . $this->translate('no filter data')];
                    }
                    //Вероятно не нужно. Блокирующие фильтры все равно не пропустят
                    //$this->setFilters($filterData, true);
                }


//Строчная часть
                if (($string = $csvArray[$rowNumSectionRows][0] ?? '') !== $this->translate('Rows part')) {
                    return ['error' => $NotCorrectFormat . $this->translate('in row %s',
                            $rowNumSectionRows + 1) . ' ' . $this->translate('no section header %s',
                            $this->translate('Rows part'))];
                }
                $numRow = $rowNumSectionRows + 3;
                $rowCount = count($csvArray);

                $rowFields = $csvArray[$rowNumSectionRows + 2];

                while ($numRow < $rowCount && (count($csvArray[$numRow]) > 1) && ($csvArray[$numRow][1] ?? '') !== 'f0H') {
                    $csvRow = $csvArray[$numRow];

                    $isDel = ($csvRow[0] ?? '') !== '';

                    //Проверка на пустые строки в импорте
                    $isAllFieldsEmpty = true;
                    foreach ($csvRow as $k => $v) {
                        if ($v !== '') {
                            $isAllFieldsEmpty = false;
                            break;
                        }
                    }
                    $numRow++;

                    if ($isAllFieldsEmpty) {
                        break;
                    }

                    $id = $csvRow[1];
                    $csvRowColumns = [];
                    if ($isDel && $id) {
                        $import['remove'][] = $id;
                    } else {
                        foreach ($rowFields as $i => $fieldName) {
                            if ($i < 2) {
                                continue;
                            }
                            if (($field = ($this->fields[$fieldName] ?? null)) && !in_array(
                                    $field['type'],
                                    ['comments', 'button']
                                )) {
                                if (!empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                                    if ($import['codedFields'] === '0') {
                                        continue;
                                    }
                                    if ($import['codedFields'] === '1' && empty($id)) {
                                        continue;
                                    }
                                }

                                $val = $csvRow[$i] ?? '';
                                if (!in_array($field['type'], ['comments', 'button'])) {
                                    $csvRowColumns[$field['name']] = $getCsvVal($val, $field);
                                }
                            }
                        }

                        if ($id) {
                            $import['modify'][$id] = $csvRowColumns;
                        } else {
                            $import['add'][] = $csvRowColumns;
                        }
                    }
                }
//Футеры колонок
                if (preg_match('/f\d+H/', $csvArray[$numRow][1] ?? '')) {
                    while (preg_match('/f\d+H/', $csvArray[$numRow][1] ?? '')) {
                        //Переводим на строку с names
                        $numRow++;
                        foreach ($rowFields as $i => $fName) {
                            if ($i < 2) {
                                continue;
                            }
                            if ($footerName = ($csvArray[$numRow][$i] ?? null)) {
                                if (($field = ($this->fields[$footerName] ?? null)) && !in_array(
                                        $field['type'],
                                        ['comments', 'button']
                                    )) {
                                    if ($field['category'] === 'footer') {
                                        if ($import['codedFields'] === '0' && !empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                                            continue;
                                        }
                                        $val = $csvArray[$numRow + 1][$i];
                                        $import['modify']['params'][$field['name']] = $getCsvVal($val, $field);
                                    }
                                }
                            }
                        }
                        $numRow = $numRow + 2;
                    }
                    $numRow++;
                }


//Футер
                if (is_a($this, JsonTables::class)) {
                    if (($string = $csvArray[$numRow][0] ?? '') !== $this->translate('Footer')) {
                        return ['error' => $NotCorrectFormat . $this->translate('on the line one line after the Rows part is missing the header of the Footer section') . var_export(
                                $csvArray[$numRow],
                                1
                            )];
                    }
                    $numRow += 2;

                    foreach ($csvArray[$numRow] ?? [] as $i => $fieldName) {
                        if (!$fieldName) {
                            continue;
                        }
                        if (($field = ($this->fields[$fieldName] ?? null)) && !in_array(
                                $field['type'],
                                ['comments', 'button']
                            )) {
                            if ($import['codedFields'] === '0' && !empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                                continue;
                            }
                            $import['modify']['params'][$field['name']] = $getCsvVal(
                                $csvArray[$numRow + 1][$i],
                                $field
                            );
                        }
                    }
                }
                break;
            case 'rows':
                $rowFields = [];
                foreach ($sortedVisibleFields['column'] as $k => $field) {
                    if (!in_array($field['name'], $visibleFields)) {
                        continue;
                    }
                    $rowFields[] = $field['name'];
                }
                foreach ($csvArray as $csvRow) {
                    //Проверка на пустые строки в импорте
                    $isAllFieldsEmpty = true;
                    foreach ($csvRow as $v) {
                        if ($v !== '') {
                            $isAllFieldsEmpty = false;
                            break;
                        }
                    }
                    if ($isAllFieldsEmpty) {
                        continue;
                    }

                    $csvRowColumns = [];
                    foreach ($rowFields as $i => $fieldName) {
                        if (($field = ($this->fields[$fieldName] ?? null)) && !in_array(
                                $field['type'],
                                ['comments', 'button']
                            )) {
                            if (!empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                                continue;
                            }
                            if (!in_array($field['type'], ['comments', 'button'])) {
                                $val = $csvRow[$i] ?? '';
                                $csvRowColumns[$field['name']] = $getCsvVal($val, $field);
                            }
                        }
                    }
                    $import['add'][] = $csvRowColumns;
                }
                break;
        }
        return false;
    }

    protected function getCsvArray($visibleFields, $type)
    {
        $csv = [];

        $useThisField = function ($field) use ($visibleFields) {
            return in_array($field['name'], $visibleFields) && !in_array($field['type'], ['file', 'button', 'chart']);
        };

        $getAndCheckVal = function ($valArray, $fieldName, $id = null) {
            if (is_array($valArray['v'])) {
                $field = $this->fields[$fieldName];
                if ($id) {
                    throw new errorException($this->translate('Value format error in id %s row field %s',
                        [$id, $field['title']]));
                } else {
                    throw new errorException($this->translate('Value format error in field %s', $field['title']));
                }

            }
            return $valArray['v'];
        };

        $addTop = function () use (&$csv) {
            //Название таблицы
            $csv[] = [$this->tableRow['title']];
            //Апдейтед
            $updated = json_decode($this->updated, true);
            $csv[] = ['date: ' . date_create($updated['dt'])->format('d.m H:i') . '', 'code:' . $updated['code'] . '', 'structureCode:' . $this->getStructureUpdatedJSON()['code']];

            //id Проекта    Название проекта
            if ($this->tableRow['type'] === 'calcs') {
                $csv[] = [$this->Cycle->getId(), $this->Cycle->getRowName()];
            } else {
                $csv[] = [$this->translate('Out of cycles')];
            }

            $csv[] = ['', '', ''];

            $csv[] = [$this->translate('Manual Values')];
            $csv[] = [$this->translate('[0: do not modify calculated fields] [1: change values of calculated fields already set to manual] [2: change calculated fields]')];
            $csv[] = [0];

            $csv[] = ['', '', ''];
        };

        $prepareCategoryDataData = function ($categoriFields) use ($useThisField) {
            $data = [];
            foreach ($categoriFields as $field) {
                if (!$useThisField($field)) {
                    continue;
                }
                $data[$field['name']] = $this->tbl['params'][$field['name']];
            }
            return $this->getValuesAndFormatsForClient(['params' => $data],
                'csv',
                array_keys($this->tbl['rows']))['params'] ?? [];
        };

        $addRowsByCategory = function ($categoriFields, $categoryTitle) use ($prepareCategoryDataData, $useThisField, $getAndCheckVal, &$csv, $visibleFields) {
            $csv[] = [$categoryTitle];

            $paramNames = [];
            $paramValues = [];
            $paramTitles = [];

            foreach ($prepareCategoryDataData($categoriFields) as $name => $valArray) {
                $paramTitles[] = '' . $this->fields[$name]['title'] . '';
                $paramNames[] = '' . $this->fields[$name]['name'] . '';
                $paramValues[] = '' . $getAndCheckVal($valArray, $name) . '';
            }

            $csv[] = $paramTitles;
            $csv[] = $paramNames;
            $csv[] = $paramValues;
            $csv[] = ['', '', ''];
        };
        $addFilter = function ($categoriFields) use (&$csv) {
            $csv[] = [$this->translate('Filter')];
            $_filters = [];
            foreach ($categoriFields as $field) {
                $_filters[$field['name']] = $this->tbl['params'][$field['name']]['v'] ?? null;
            }
            $csv[] = [empty($_filters) ? '' : Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE))];
            $csv[] = ['', '', ''];
        };
        $addFooter = function ($rowParams) use ($prepareCategoryDataData, $useThisField, $getAndCheckVal, &$csv, $addRowsByCategory, $visibleFields) {
            /******Футеры колонок - только в json-таблицах******/
            if (is_a($this, JsonTables::class)) {
                $columnsFooters = [];
                $withoutColumnsFooters = [];
                $maxCountInColumn = 0;
                foreach ($this->getVisibleFields('web', true)['footer'] as $field) {
                    if (!empty($field['column'])) {
                        if (empty($columnsFooters[$field['column']])) {
                            $columnsFooters[$field['column']] = [];
                        }
                        $columnsFooters[$field['column']][] = $field;
                        if (count($columnsFooters[$field['column']]) > $maxCountInColumn) {
                            $maxCountInColumn++;
                        }
                    } else {
                        $withoutColumnsFooters[] = $field;
                    }
                }


                for ($iFooter = 0; $iFooter < $maxCountInColumn; $iFooter++) {
                    $iFooterCsvHead = ['', 'f' . $iFooter . 'H'];
                    $iFooterCsvName = ['', 'f' . $iFooter . 'N'];
                    $iFooterCsvVals = ['', 'f' . $iFooter . 'V'];
                    foreach ($rowParams as $fName) {
                        if (isset($columnsFooters[$fName][$iFooter])) {
                            $field = $columnsFooters[$fName][$iFooter];

                            if (!$useThisField($field)) {
                                continue;
                            }

                            $valArray = $prepareCategoryDataData([$this->fields[$field['name']]])[$field['name']];

                            $iFooterCsvHead [] = $field['title'];
                            $iFooterCsvName [] = $field['name'];
                            $iFooterCsvVals [] = $getAndCheckVal($valArray, $fName);
                        } else {
                            $iFooterCsvHead [] = '';
                            $iFooterCsvName [] = '';
                            $iFooterCsvVals [] = '';
                        }
                    }
                    $csv[] = $iFooterCsvHead;
                    $csv[] = $iFooterCsvName;
                    $csv[] = $iFooterCsvVals;
                }

                $csv[] = ['', '', ''];
                $addRowsByCategory($withoutColumnsFooters, $this->translate('Footer'));
            }
        };

        /*Определение полей*/
        $paramTitles = [$this->translate('Deleting'), 'id'];
        $paramNames = ['', ''];
        $rowParams = [];
        foreach ($this->getVisibleFields('web', true)['column'] as $k => $field) {
            if (!$useThisField($field)) {
                continue;
            }

            $paramTitles[] = $field['title'];
            $paramNames[] = $field['name'];
            $rowParams[] = $k;
        }


        $prepareRowsData = function () use ($rowParams) {
            $data = [];
            foreach ($this->tbl['rows'] as $row) {
                $_row = ['id' => $row['id']];
                foreach ($rowParams as $fName) {
                    $_row[$fName] = $row[$fName];
                }
                $data[] = $_row;
            }
            return $this->getValuesAndFormatsForClient(['rows' => $data],
                'csv',
                array_keys($this->tbl['rows']))['rows'] ?? [];
        };


        switch ($type) {
            case 'full':

                /***Top****/
                $addTop();
                /******Хэдер******/
                $sortedVisibleFields = $this->getVisibleFields('web', true);

                $addRowsByCategory($sortedVisibleFields['param'], $this->translate('Header'));
                /******Фильтр******/
                $addFilter($sortedVisibleFields['filter']);

                /******Строчная часть ******/
                $csv[] = [$this->translate('Rows part')];
                $csv[] = $paramTitles;
                $csv[] = $paramNames;

                foreach ($prepareRowsData() as $row) {
                    $csvRow = ['', $row['id']];
                    foreach ($rowParams as $fName) {
                        $csvRow [] = $getAndCheckVal($row[$fName], $fName, $row['id']);
                    }
                    $csv[] = $csvRow;
                }


                $addFooter($rowParams);

                break;
            case 'rows':
                foreach ($prepareRowsData() as $row) {
                    $csvRow = [];
                    foreach ($rowParams as $fName) {
                        $csvRow [] = $getAndCheckVal($row[$fName], $fName, $row['id']);
                    }
                    $csv[] = $csvRow;
                }
                break;
        }

        return $csv;
    }

    protected function getStructureUpdatedJSON()
    {
        $jsonUpdated = $this->Totum->getTable('tables_fields')->updated;
        $jsonUpdated = json_decode($jsonUpdated, true);
        return $jsonUpdated;
    }
}
