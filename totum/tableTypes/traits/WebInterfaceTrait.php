<?php


namespace totum\tableTypes\traits;

use totum\common\Crypt;
use totum\common\Field;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;

trait WebInterfaceTrait
{
    public function changeFieldsSets($func = null)
    {
        if ($this->getTableRow()['type'] == 'calcs') {
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
            ['channel' => 'web', 'modifyCalculated' => (($import['codedFields'] ?? null) == 2 ? 'all' : 'handled')
                , 'add' => ($import['add'] ?? [])
                , 'modify' => ($import['modify'] ?? [])
                , 'remove' => ($import['remove'] ?? [])
            ]
        );
        $oldUpdated = $this->updated;
        $this->isTblUpdated(0);
        if ($oldUpdated != $this->updated) {
            $table['updated'] = $this->updated;
        }
        return $table;
    }

    public function csvExport($tableData, $idsString, $visibleFields, $type = "full")
    {
        $this->checkTableUpdated($tableData);

        if ($idsString && $idsString != '[]') {
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
            fputcsv($out, $fields, ";", '"', '\\');
        }
        fclose($out);

        return ['csv' => ob_get_clean()];
    }

    public function checkAndModify($tableData, array $data)
    {
        $inVars = [];

        $modify = $data['modify'] ?? [];
        $remove = $data['remove'] ?? [];

        $duplicate = $data['duplicate'] ?? [];
        $reorder = $data['reorder'] ?? [];

        $refresh = $data['refresh'] ?? [];

        $this->checkTableUpdated($tableData);


        $inVars['modify'] = [];
        $inVars['add'] = $data['add'] ?? [];
        $inVars['channel'] = $data['channel'] ?? 'web';

        if (!empty($modify['setValuesToDefaults'])) {
            unset($modify['setValuesToDefaults']);
            $inVars['setValuesToDefaults'] = $modify;
        } else {
            $inVars['modify'] = $modify;
        }
        $inVars['remove'] = $remove;
        $inVars['duplicate'] = $duplicate;
        $inVars['reorder'] = $reorder;

        if (!empty($data['addAfter'])) {
            $inVars['addAfter'] = $data['addAfter'];
        }


        $inVars['calculate'] = aTable::CALC_INTERVAL_TYPES['changed'];
        if ($refresh) {
            $inVars['modify'] = $inVars['modify'] + array_flip($refresh);
        }
        foreach ($inVars['modify'] as $itemId => &$editData) {//Для  saveRow
            if ($itemId == 'params') {
                continue;
            }
            if (!is_array($editData)) {//Для  refresh
                $editData = [];
                continue;
            }

            foreach ($editData as $k => &$v) {
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

        $Log = $this->calcLog(["name" => 'RECALC', 'table' => $this, 'inVars' => $inVars]);
        $this->reCalculate($inVars);
        $this->calcLog($Log, 'result', $this->isTblUpdated(0) ? 'changed' : 'not changed');
    }

    abstract public function checkEditRow($data, $tableData = null);


    protected function prepareCsvImport(&$import, $csvString, $answers, $visibleFields = [], $type = 'full')
    {
        $import['modify'] = [];
        $import['add'] = [];
        $import['remove'] = [];

        $NotCorrectFormat = 'Неверный формат файла: ';
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

        if (substr($csvString, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf)) {
            $csvString = substr($csvString, 3);
        } else {
            if (!mb_check_encoding($csvString, 'utf-8') && mb_check_encoding($csvString, 'windows-1251')) {
                $csvString = mb_convert_encoding($csvString, 'utf-8', 'windows-1251');
            }
            if (!mb_check_encoding($csvString, 'utf-8')) {
                return ['error' => 'Неверная кодировка файла (должно быть utf-8 или windows-1251)'];
            }
        }


        $csvString = str_replace("\r\n", PHP_EOL, $csvString);
        $csvArray = explode(PHP_EOL, $csvString);

        foreach ($csvArray as &$row) {
            $row = str_getcsv(trim($row), ';', '"', '\\');
            foreach ($row as &$c) {
                $c = trim($c);
            }
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
                    $csvArray[$rowNumName][0] != $this->tableRow['title'],
                    'Файл таблицы [[' . $csvArray[$rowNumName][0] . ']] вы пытаетесь загрузить в таблицу [[' . $this->tableRow['title'] . ']]'
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
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumCodes + 1) . ' отсутствует код изменения таблицы'];
                } else {
                    $updated = json_decode($this->updated, true);
                    if ($checkQuestion(2, $matchCode[1] != $updated['code'], 'Таблица была изменена')) {
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
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumCodes + 1) . ' отсутствует код изменения структуры'];
                } else {
                    if ($checkQuestion(
                        3,
                        $matchCode[1] != $this->getStructureUpdatedJSON()['code'],
                        'Была изменена структура таблицы. Возможно несовпадение порядка полей.'
                    )) {
                        return $question;
                    }
                }

//Тот ли проект
                if (!isset($csvArray[$rowNumProject][0]) || !preg_match(
                    '/^(\d+|Вне циклов)$/',
                    $csvArray[$rowNumProject][0],
                    $matchCode
                )
                ) {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumProject + 1) . ' отсутствует указание на цикл'];
                } else {
                    if ($checkQuestion(
                        4,
                        (isset($this->Cycle) && $this->Cycle->getId() ? $this->Cycle->getId() : 'Вне циклов') != $matchCode[1],
                        'Таблица из другого цикла или вне циклов'
                    )) {
                        return $question;
                    }
                }


//Ручные значения
                if (($string = $csvArray[$rowNumSectionHandl][0] ?? '') != 'Ручные значения') {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHandl + 1) . ' отсутствует заголовок секции Ручные значения'];
                }
                if (!in_array(
                    ($string = strtolower($csvArray[$rowNumSectionHandl + 2][0] ?? '')),
                    [0, 1, 2]
                )
                ) {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHandl + 1) . ' отсутствует 0/1/2 переключатель редактирования'];
                }
                $import['codedFields'] = $string;

//Хэдер
                if (($string = $csvArray[$rowNumSectionHeader][0] ?? '') != 'Хедер') {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHeader + 1) . ' отсутствует заголовок секции Хедер'];
                }
                $headerFields = $csvArray[$rowNumSectionHeader + 2];

                foreach ($headerFields as $i => $fieldName) {
                    if (!$fieldName) {
                        continue;
                    }
                    if (($field = $this->fields[$fieldName]) && !in_array($field['type'], ['comments', 'button'])) {
                        if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) ; else {
                            $import['modify']['params'][$field['name']] = $getCsvVal(
                                $csvArray[$rowNumSectionHeader + 3][$i],
                                $field
                            );
                        }
                    }
                }

//Фильтр
                if (($string = $csvArray[$rowNumFilter][0] ?? '') != 'Фильтр') {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumFilter + 1) . ' отсутствует заголовок секции Фильтр'];
                }
                if (!empty($sortedVisibleFields["filter"])) {
                    if (empty($filterData = $csvArray[$rowNumFilter + 1][0])) {
                        return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumFilter + 2) . ' отсутствуют данные о фильтрах'];
                    }
                    //Вероятно не нужно. Блокирующие фильтры все равно не пропустят
                    //$this->setFilters($filterData, true);
                }


//Строчная часть
                if (($string = $csvArray[$rowNumSectionRows][0] ?? '') != 'Строчная часть') {
                    return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionRows + 1) . ' отсутствует заголовок секции Строчная часть'];
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
                                    if ($import['codedFields'] == 0) {
                                        continue;
                                    }
                                    if ($import['codedFields'] == 1 && empty($id)) {
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
                                    if ($field['category'] == 'footer') {
                                        if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) {
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
                    if (($string = $csvArray[$numRow][0] ?? '') != 'Футер') {
                        return ['error' => $NotCorrectFormat . 'в строке через одну после Строчной части отсутствует заголовок секции Футер' . var_export(
                            $csvArray[$numRow],
                            1
                        )];
                    }
                    $numRow += 2;

                    foreach ($csvArray[$numRow] ?? [] as $i => $fieldName) {
                        if (!$fieldName) {
                            continue;
                        }
                        if (($field = ($this->fields[$footerName] ?? null)) && !in_array(
                            $field['type'],
                            ['comments', 'button']
                        )) {
                            if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) {
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
    }

    protected function getCsvArray($visibleFields, $type)
    {
        $csv = [];

        $addTop = function () use (&$csv) {
            //Название таблицы
            $csv[] = [$this->tableRow['title']];
            //Апдейтед
            $updated = json_decode($this->updated, true);
            $csv[] = ['от ' . date_create($updated['dt'])->format('d.m H:i') . '', 'code:' . $updated['code'] . '', 'structureCode:' . $this->getStructureUpdatedJSON()['code']];

            //id Проекта    Название проекта
            if ($this->tableRow['type'] == 'calcs') {
                $csv[] = [$this->Cycle->getId(), $this->Cycle->getRowName()];
            } else {
                $csv[] = ['Вне циклов'];
            }

            $csv[] = ["", "", ""];

            $csv[] = ['Ручные значения'];
            $csv[] = ['[0: рассчитываемые поля не обрабатываем] [1: меняем значения рассчитываемых полей уже выставленных в ручное] [2: меняем рассчитываемые поля]'];
            $csv[] = [0];

            $csv[] = ["", "", ""];
        };
        $addRowsByCategory = function ($categoriFields, $categoryTitle) use (&$csv, $visibleFields) {
            $csv[] = [$categoryTitle];

            $paramNames = [];
            $paramValues = [];
            $paramTitles = [];

            foreach ($categoriFields as $field) {
                if (!in_array($field['name'], $visibleFields)) {
                    continue;
                }
                $valArray = $this->tbl['params'][$field['name']];

                Field::init($field, $this)->addViewValues('csv', $valArray, $this->tbl['params'], $this->tbl);
                $val = $valArray['v'];

                $paramTitles[] = '' . $field['title'] . '';
                $paramNames[] = '' . $field['name'] . '';
                $paramValues[] = '' . $val . '';
            }

            $csv[] = $paramTitles;
            $csv[] = $paramNames;
            $csv[] = $paramValues;
            $csv[] = ["", "", ""];
        };
        $addFilter = function ($categoriFields) use (&$csv) {
            $csv[] = ["Фильтр"];
            $_filters = [];
            foreach ($categoriFields as $field) {
                $_filters[$field['name']] = $this->tbl['params'][$field['name']]['v'] ?? null;
            }
            $csv[] = [empty($_filters) ? '' : Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE))];
            $csv[] = ["", "", ""];
        };
        $addFooter = function ($rowParams) use (&$csv, $addRowsByCategory, $visibleFields) {
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

                            if (!in_array($field['name'], $visibleFields)) {
                                continue;
                            }

                            $valArray = $this->tbl['params'][$field['name']];
                            Field::init($field, $this)->addViewValues(
                                'csv',
                                $valArray,
                                $this->tbl['params'],
                                $this->tbl
                            );
                            $val = $valArray['v'];

                            $iFooterCsvHead [] = $field['title'];
                            $iFooterCsvName [] = $field['name'];
                            $iFooterCsvVals [] = $val;
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

                $csv[] = ["", "", ""];
                $addRowsByCategory($withoutColumnsFooters, 'Футер');
            }
        };


        /*Определение полей*/
        $paramTitles = ['Удаление', 'id'];
        $paramNames = ['', ''];
        $rowParams = [];
        foreach ($this->getVisibleFields('web', true)['column'] as $k => $field) {
            if (!in_array($field['name'], $visibleFields)) {
                continue;
            }

            $paramTitles[] = $field['title'];
            $paramNames[] = $field['name'];
            $rowParams[] = $k;
        }

        switch ($type) {
            case 'full':

                /***Top****/
                $addTop();
                /******Хэдер******/
                $sortedVisibleFields = $this->getVisibleFields('web', true);

                $addRowsByCategory($sortedVisibleFields['param'], 'Хедер');
                /******Фильтр******/
                $addFilter($sortedVisibleFields['filter']);

                /******Строчная часть ******/
                $csv[] = ['Строчная часть'];
                $csv[] = $paramTitles;
                $csv[] = $paramNames;


                foreach ($this->tbl['rows'] as $row) {
                    $csvRow = ['', $row['id']];
                    foreach ($rowParams as $fName) {
                        $valArray = $row[$fName];
                        Field::init($this->fields[$fName], $this)->addViewValues('csv', $valArray, $row, $this->tbl);
                        $val = $valArray['v'];
                        $csvRow [] = $val;
                    }
                    $csv[] = $csvRow;
                }

                $addFooter($rowParams);

                break;
            case 'rows':
                foreach ($this->tbl['rows'] as $row) {
                    $csvRow = [];
                    foreach ($rowParams as $fName) {
                        $valArray = $row[$fName];
                        Field::init($this->fields[$fName], $this)->addViewValues('csv', $valArray, $row, $this->tbl);
                        $val = $valArray['v'];
                        $csvRow [] = $val;
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
