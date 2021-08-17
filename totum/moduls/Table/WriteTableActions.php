<?php


namespace totum\moduls\Table;

use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\fieldTypes\File;
use totum\models\TmpTables;
use totum\tableTypes\tmpTable;

class WriteTableActions extends ReadTableActions
{
    public function checkUnic()
    {
        return $this->Table->checkUnic($this->post['fieldName'] ?? '', $this->post['fieldVal'] ?? '');
    }

    public function add()
    {
        if ($this->User->isOneCycleTable($this->Table->getTableRow())) {
            return 'Добавление запрещено';
        }
        if (!$this->Table->isUserCanAction('insert')) {
            throw new errorException('Добавление в эту таблицу вам запрещено');
        }
        $this->Table->setWebIdInterval(json_decode($this->post['ids'], true));

        if ($this->Table->getTableRow()['name'] === 'tables_fields' && key_exists(
            'afterField',
            $this->post['tableData']
        )) {
            $this->Totum->getModel('tables_fields')->setAfterField($this->post['tableData']['afterField']);
        }

        $data=null;
        if(!empty($this->post['data'])){
            $data=json_decode($this->post['data'], true);
        }

        return $this->modify(['add' => $data ?? $this->post['hash'] ?? "new cycle", 'addAfter' => $this->post['insertAfter'] ?? null]);
    }

    public function tmpFileUpload()
    {
        return File::fileUpload($this->User->getId(), $this->Totum->getConfig());
    }

    public function saveOrder()
    {
        if (!$this->Table->isUserCanAction('reorder')) {
            throw new errorException('Сортировка в этой таблице вам запрещена');
        }

        if (!empty($this->post['ids']) && ($orderedIds = json_decode(
            $this->post['orderedIds'],
            true
        ))) {
            return $this->modify(['reorder' => $orderedIds ?? []]);
        } else {
            throw new errorException('Таблица пуста');
        }
    }

    public function checkInsertRow()
    {
        $this->Table->reCalculateFilters(
            'web',
            false,
            false,
            ["params" => $this->getPermittedFilters($this->Request->getParsedBody()['filters'] ?? '')]
        );

        $visibleFields = $this->Table->getVisibleFields('web', true);
        if (empty($this->post['hash'])) {
            $addData = json_decode($this->post['data'], true);
            do {
                $hash = 'i-' . md5(microtime(true) . rand());
            } while (!TmpTables::init($this->Totum->getConfig())->saveByHash(
                TmpTables::serviceTables['insert_row'],
                $this->User,
                $hash,
                [],
                true
            ));
        } else {
            $addData = json_decode($this->post['data'], true);
            $hash = $this->post['hash'];
        }

        $columnFilter = [];
        foreach ($this->Table->getSortedFields()['filter'] as $k => $f) {
            if (($f['showInWeb'] ?? false) && $f['column'] ?? false) {
                $columnFilter[$f['column']] = $k;
            }
        }
        foreach ($visibleFields['column'] as $v) {
            $filtered = null;
            if (key_exists($v['name'], $columnFilter)) {
                $val = $this->Table->getTbl()['params'][$columnFilter[$v['name']]]['v'];

                if (isset($columnFilter[$v['name']])
                    && $val !== '*ALL*'
                    && $val !== ['*ALL*']
                    && $val !== '*NONE*'
                    && $val !== ['*NONE*']
                ) {
                    $filtered = $val ?? null;
                }
                if (is_null($addData[$v['name']] ?? null) && !empty($filtered)) {
                    $addData[$v['name']] = $filtered;
                }
            }
        }
        $data = ['rows' => [$this->Table->checkInsertRow(
            $this->post['tableData'] ?? [],
            $addData,
            $hash,
            [],
            $this->post['clearField'] ?? null
        )]];

        $data = $this->Table->getValuesAndFormatsForClient($data, 'edit');
        return ['row' => $data['rows'][0], 'hash' => $hash];
    }

    public function checkEditRow()
    {
        $editData = json_decode($this->post['data'], true) ?? [];
        $data = ['id' => $editData['id'] ?? 0];
        $dataSetToDefault = [];

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

        $row = $this->Table->checkEditRow($data, $dataSetToDefault, $this->post['tableData'] ?? []);
        $res['row'] = $this->Table->getValuesAndFormatsForClient(['rows' => [$row]], 'edit')['rows'][0];
        $res['f'] = $this->getTableFormat([]);
        return $res;
    }

    public function saveEditRow()
    {
        $data = json_decode($this->post['data'], true) ?? [];
        return $this->modify(['modify' => [$data['id'] => $data ?? []]]);
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
                $this->Totum->addToInterfaceLink($this->Request->getServerParams()['REQUEST_URI'], 'self', 'reload');
            }
            return $r;
        } else {
            throw new errorException('У вас нет доступа для csv-изменений');
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
            throw new errorException('Дублирование в этой таблице вам запрещено');
        }
        $ids = !empty($this->post['duplicate_ids']) ? json_decode($this->post['duplicate_ids'], true) : [];
        if ($ids) {
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
                    $e->addPath('Таблица [[' . $this->Table->getTableRow()['name'] . ']]; КОД ПРИ ДУБЛИРОВАНИИ');
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
            throw new errorException('Удаление из этой таблицы вам запрещено');
        }
        $ids = (array)(!empty($this->post['delete_ids']) ? json_decode($this->post['delete_ids'], true) : []);
        return $this->modify(['remove' => $ids]);
    }

    public function restore()
    {
        if (!$this->Table->isUserCanAction('restore')) {
            throw new errorException('Восстановление в этой таблице вам запрещено');
        }
        $ids = (array)(!empty($this->post['restore_ids']) ? json_decode($this->post['restore_ids'], true) : []);

        return $this->modify(['restore' => $ids]);
    }

    public function selectSourceTableAction()
    {
        return $this->Table->selectSourceTableAction(
            $this->post['field_name'],
            json_decode($this->post['data'], true) ?? []
        );
    }
}
