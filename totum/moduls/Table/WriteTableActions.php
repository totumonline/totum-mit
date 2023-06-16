<?php


namespace totum\moduls\Table;

use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Lang\RU;
use totum\fieldTypes\File;
use totum\models\TmpTables;
use totum\tableTypes\tmpTable;

class WriteTableActions extends ReadTableActions
{
    public function checkUnic()
    {
        if ($this->Table->isField('visible',
                'web',
                $fieldName = ($this->post['fieldName'] ?? '')) && $this->Table->getFields()[$fieldName]['type'] === 'unic') {
            return $this->Table->checkUnic($fieldName, $this->post['fieldVal'] ?? '');
        }
        throw new errorException($fieldName . ' is not field of type unique');
    }

    public function add()
    {
        if ($this->User->isOneCycleTable($this->Table->getTableRow())) {
            return $this->translate('You are not allowed to add to this table');
        }
        if (!$this->Table->isUserCanAction('insert')) {
            throw new errorException($this->translate('You are not allowed to add to this table'));
        }

        $this->Table->setWebIdInterval(json_decode($this->post['ids'], true));

        if ($this->Table->getTableRow()['name'] === 'tables_fields' && key_exists(
                'afterField',
                $this->post['tableData']
            )) {
            $this->Totum->getModel('tables_fields')->setAfterField($this->post['tableData']['afterField']);
        }

        $data = null;
        if (!empty($this->post['data'])) {
            $data = json_decode($this->post['data'], true);
        }

        return $this->modify(['add' => $data ?? $this->post['hash'] ?? 'new cycle', 'addAfter' => $this->post['insertAfter'] ?? null],
            json_decode($this->post['fields'] ?? '[]', true));
    }

    public function tmpFileUpload()
    {
        return File::fileUpload($this->User->getId(), $this->Totum->getConfig());
    }

    public function saveOrder()
    {
        if (!$this->Table->isUserCanAction('reorder')) {
            throw new errorException($this->translate('You are not allowed to sort in this table'));
        }

        if (!empty($this->post['ids']) && ($orderedIds = json_decode(
                $this->post['orderedIds'],
                true
            ))) {
            return $this->modify(['reorder' => $orderedIds ?? []]);
        } else {
            throw new errorException($this->translate('Table is empty'));
        }
    }

    public function checkInsertRow()
    {
        if (empty($this->post['hash'])) {
            $hash = TmpTables::init($this->Totum->getConfig())->getNewHash(
                TmpTables::SERVICE_TABLES['insert_row'],
                $this->User,
                [],
                'i'
            );
        } else {
            $hash = $this->post['hash'];
        }
        $onlyFields = json_decode($this->post['fields'] ?? '[]', true);
        if (empty($onlyFields)) {
            $onlyFields = null;
        }
        $data = ['rows' => [$this->getInsertRow($hash,
            json_decode($this->post['data'], true),
            $this->post['tableData'] ?? [],
            $this->post['clearField'] ?? null)]];

        $data = $this->Table->getValuesAndFormatsForClient($data, 'edit', [], fieldNames: $onlyFields);
        $res = ['row' => $data['rows'][0], 'hash' => $hash];
        $this->addLoadedSelects($res);
        return $res;
    }

    public function checkEditRow()
    {

        if (empty($this->post['hash'])) {
            if (!empty($this->post['loadSelects'])) {
                $hash = TmpTables::init($this->Totum->getConfig())->getNewHash(
                    TmpTables::SERVICE_TABLES['edit_row'],
                    $this->User,
                    [],
                    'e'
                );
            }
        } else {
            $hash = $this->post['hash'];
        }

        if ($hash ?? null) {
            $row = $this->getEditRow($hash,
                json_decode($this->post['data'], true),
                $this->post['tableData'] ?? []);
            $res['hash'] = $hash;
            $res['f'] = $this->getTableFormat([]);
        } else {
            $editData = json_decode($this->post['data'], true) ?? [];
            $row = $this->Table->checkEditRow($editData, $this->post['tableData'] ?? []);
        }
        $onlyFields = json_decode($this->post['fields'] ?? '[]', true);
        if (empty($onlyFields)) {
            $onlyFields = null;
        }
        $res['row'] = $this->Table->getValuesAndFormatsForClient(['rows' => [$row]],
            'edit',
            [],
            fieldNames: $onlyFields)['rows'][0];
        $this->addLoadedSelects($res);

        return $res;
    }

    public function saveEditRow()
    {
        $data = json_decode($this->post['data'], true) ?? [];
        return $this->modify(['modify' => [$data['id'] => $data ?? []]],
            json_decode($this->post['fields'] ?? '[]', true));
    }

    public function csvImport()
    {
        if ($this->Table->isUserCanAction('csv_edit')) {
            $r = $this->Table->csvImport(
                $this->post['tableData'] ?? [],
                $this->post['csv'] ?? '',
                $this->post['answers'] ?? [],
                json_decode($this->post['visibleFields'] ?? '[]', true),
                $this->post['type']
            );

            if (is_array($r) && ($r['ok'] ?? false)) {
                return ['ok' => 1];
            }
            return $r;
        } else {
            throw new errorException($this->translate('You do not have access to csv-import in this table'));
        }
    }

    public function excelImport()
    {
        if ($this->isTableServiceOn('xlsx_import')) {
            $calc = new CalculateAction(<<<CODE
= : linkToFileUpload(title: $#title; code: \$code; limit: 1; type: ".xlsx"; var: "title" = $#title; var: 'table'=$#table; refresh: true)
```code:totum
=: linkToDataTable(table: 'ttm__prepared_data_import'; title: $#title;  params: \$params;  target: "iframe"; width: "90wv"; refresh: true; bottombuttons: 'force')
params: rowCreate(field: "h_import_data" = \$fileData; field: "h_table" = $#table; field: "h_iscolumnsinfirstrow"=true)
~fileData: serviceXlsxParser(filestring: $#input[0][filestring]; withformats: false; withcolumns: true)
```
CODE
            );
            $calc->execAction('CODE', [], [], [], [], $this->Table, 'exec', [
                'title' => 'Excel import to '.$this->post['title'],
                'table' => $this->Table->getTableRow()['name']
            ]);

        } else {
            throw new errorException($this->translate('There is no access to excel-import in this table'));
        }
    }

    public function refresh_rows()
    {
        $ids = !empty($this->post['refreash_ids']) ? json_decode($this->post['refreash_ids'], true) : [];
        return $this->modify(['refresh' => $ids]);
    }

    public function duplicate()
    {
        if (!$this->Table->isUserCanAction('duplicate')) {
            throw new errorException($this->translate('You are not allowed to duplicate in this table'));
        }
        $ids = !empty($this->post['duplicate_ids']) ? json_decode($this->post['duplicate_ids'], true) : [];
        if ($ids) {
            $this->Table->checkIsUserCanViewIds('web', $ids);

            if (preg_match('/^\s*(a\d+)?=\s*:\s*[^\s]/', $this->Table->getTableRow()['on_duplicate'])) {
                try {
                    $Calc = new CalculateAction($this->Table->getTableRow()['on_duplicate']);
                    $Calc->execAction(
                        '__ON_ROW_DUPLICATE',
                        [],
                        [],
                        $this->Table->getTbl(),
                        $this->Table->getTbl(),
                        $this->Table,
                        'exec',
                        ['ids' => $ids]
                    );
                } catch (errorException $e) {
                    $e->addPath($this->translate('Table %s. DUPLICATION CODE', $this->Table->getTableRow()['name']));
                    throw $e;
                }
            } else {
                $this->modify(['channel' => 'inner', 'duplicate' => ['ids' => $ids, 'replaces' => json_decode(
                        $this->post['data'],
                        true
                    ) ?? []], 'addAfter' => ($this->post['insertAfter'] ?? null)]);
            }

            return $this->getTableClientChangedData([]);/*$this->getTableClientData($this->post['offset'] ?? null,
                $this->post['onPage'] ?? null);*/
        }
    }

    public function delete()
    {
        if (!$this->Table->isUserCanAction('delete')) {
            throw new errorException($this->translate('You are not allowed to delete from this table'));
        }
        $ids = (array)(!empty($this->post['delete_ids']) ? json_decode($this->post['delete_ids'], true) : []);
        return $this->modify(['remove' => $ids]);
    }

    public function restore()
    {
        if (!$this->Table->isUserCanAction('restore')) {
            throw new errorException($this->translate('You are not allowed to restore in this table'));
        }
        $ids = (array)(!empty($this->post['restore_ids']) ? json_decode($this->post['restore_ids'], true) : []);

        return $this->modify(['restore' => $ids]);
    }

    public function selectSourceTableAction()
    {


        if (str_starts_with($this->post['data'],
            'e-')) {
            $itemData = $this->getEditRow($this->post['data'], [], []);
        } elseif (str_starts_with($this->post['data'],
            'i-')) {
            $itemData = $this->getInsertRow($this->post['data']);
        } else {
            $itemData = json_decode($this->post['data'], true) ?? [];
        }
        $this->Table->selectSourceTableAction(
            $this->post['field_name'],
            $itemData,
            ($this->post['isPlus'] ?? false) === 'true'
        );
        return ['ok' => true];
    }

    protected function addLoadedSelects(array &$res)
    {
        if (!empty($this->post['loadSelects'])) {
            $selects = [];
            foreach ($this->Table->getSortedFields()['column'] as $field) {
                if ($field['type'] === 'select' && $this->Table->isField('editable', 'web', $field)) {
                    if (($res['row'][$field['name']]['f']['block'] ?? false) != true) {
                        if ($this->post['loadSelects'] === 'all' || ($field['codeSelectIndividual'] ?? false)) {
                            $item = $res['row'];
                            $item = array_map(fn($x) => is_array($x) && key_exists('v', $x) ? $x['v'] : $x, $item);
                            $selects[$field['name']] = $this->getEditSelect(['field' => $field['name'], 'item' => $item],
                                null,
                                null,
                                true);
                        }
                    }
                }
            }
            $res['selects'] = $selects;
        }
    }

    protected function getEditRow(string $hash, array $editData, array $tableData)
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ['params' => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        $loadData = TmpTables::init($this->Totum->getConfig())->getByHash(
            TmpTables::SERVICE_TABLES['edit_row'],
            $this->User,
            $hash
        ) ?? [];

        $summData = array_merge($loadData, $editData);
        TmpTables::init($this->Totum->getConfig())->saveByHash(
            TmpTables::SERVICE_TABLES['edit_row'],
            $this->User,
            $hash,
            $summData
        );
        return $this->Table->checkEditRow($summData, $tableData);
    }

}
