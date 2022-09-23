<?php

namespace totum\models;

use totum\common\errorException;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\Totum;
use totum\models\traits\WithTotumTrait;
use totum\fieldTypes\Comments;
use totum\tableTypes\JsonTables;

class TablesFields extends Model
{
    use WithTotumTrait;

    /**
     * @var |null
     */
    private $afterField = null;

    public function delete($where, $ignore = 0)
    {
        if ($rows = $this->getAll($where)) {
            $this->Sql->transactionStart();
            if (parent::delete($where, $ignore)) {
                foreach ($rows as $fieldRow) {
                    $fieldRow['name'] = json_decode($fieldRow['name'], true)['v'];
                    $fieldRow['table_id'] = json_decode($fieldRow['table_id'], true)['v'];
                    $fieldRow['category'] = json_decode($fieldRow['category'], true)['v'];

                    if (in_array($fieldRow['name'], Model::serviceFields)) {
                        throw new errorException($this->translate('You cannot delete system fields.'));
                    }
                    $tableRow = $this->Totum->getTableRow($fieldRow['table_id']);
                    if ($tableRow['type'] !== 'calcs') {
                        $this->Totum->getTable($tableRow, null)->deleteField($fieldRow);
                    }

                    $fieldData = json_decode($fieldRow['data'], true)['v'];
                    if ($fieldData['type'] === 'comments') {
                        Comments::removeViewedForField($tableRow['id'], $fieldRow['name'], $this->Totum->getConfig());
                    }
                }
            }
            $this->Sql->transactionCommit();
        }
    }

    public function update($params, $where, $oldRow = null): int
    {
        if (key_exists('name', $params)) {
            $name = json_decode($params['name'], true)['v'];
            if ($this->getPrepared(['table_name' => $oldRow['table_name']['v'], 'name' => $name])) {
                throw new errorException($this->translate('The [[%s]] field is already present in the [[%s]] table.', [$name, $oldRow['table_name']['v']]));
            }
            $tableRow = $this->Totum->getTableRow($oldRow['table_name']['v']);

            if ($oldRow['category']['v'] === 'column' && Totum::isRealTable($tableRow)) {
                $this->Sql->exec("ALTER TABLE {$oldRow['table_name']['v']} RENAME {$oldRow['name']['v']} TO {$name}");
            }
        }

        $r = parent::update($params, $where);

        if ($oldRow && ($table = $this->Totum->getTableRow($oldRow['table_id']['v'])) && $table['type'] !== 'tmp' && $table['type'] !== 'calcs') {
            $Table = $this->Totum->getTable($table);
            if (!empty($params['category'])) {
                $newCategory = json_decode($params['category'], true)['v'];
                if (!empty($params['category']) && $newCategory !== $oldRow['category']['v']) {
                    if ($oldRow['category']['v'] === 'column') {
                        $clearOldValue = array_map(
                            function ($v) {
                                if (is_array($v)) {
                                    return $v['v'];
                                } else {
                                    return $v;
                                }
                            },
                            $oldRow
                        );
                        $Table->deleteField($clearOldValue);
                    } elseif ($newCategory === 'column') {
                        $Table->addField($oldRow['id']);
                    }
                }
            }
            $Table->initFields(true);
        }

        return $r;
    }

    public function insertPrepared($vars, $returning = 'idFieldName', $ignore = false, $cacheIt = true): int
    {
        $decodedVars = array_map(
            function ($v) {
                return json_decode($v, true)['v'];
            },
            $vars
        );

        if (empty($decodedVars['data_src'])) {
            throw new errorException($this->translate('Fill in the field parameters.'));
        }

        if (!$decodedVars['table_id']) {
            throw new errorException($this->translate('[[%s]] is reqired.', 'table'));
        }
        if ($decodedVars['name'] === 'new_field') {
            throw new errorException($this->translate('The name of the field cannot be new_field'));
        }
        if (in_array($decodedVars['name'], Model::serviceFields) || in_array($decodedVars['name'],
                Model::RESERVED_WORDS)) {
            throw new errorException($this->translate('[[%s]] cannot be a field name. Choose another name.',
                $decodedVars['name']));
        }
        if (!is_array($vars['table_id'])) {
            $tableRowId = json_decode($vars['table_id'], true)['v'];
        } else {
            $tableRowId = $vars['table_id']['v'];
        }


        $name = $decodedVars['name'];
        $category = $decodedVars['category'];

        if ($this->fieldExits($decodedVars['table_id'], $decodedVars['name'], $decodedVars['version'])) {
            throw new errorException($this->translate('The [[%s]] field is already present in the [[%s]] table.', [$name, $decodedVars['table_name']]));
        }

        /*$this->checkParams($vars, $tableRowId);*/

        $this->Sql->transactionStart();
        if ($id = parent::insertPrepared($vars, $returning, $ignore, $cacheIt)) {
            $tableRow = $this->Totum->getTableRow($tableRowId);

            if (!is_null($this->afterField)) {
                if ($fields = $this->executePrepared(
                    false,
                    (object)['params' => [$tableRowId, (int)$this->afterField]
                        , 'whereStr' => "table_id->>'v'=? AND (data_src->'v'->'showInWebOtherPlace'->>'isOn'='true') AND (data_src->'v'->'showInWebOtherOrd'->>'isOn')='true' AND (data_src->'v'->'showInWebOtherOrd'->>'Val')::numeric > ?"],
                    'id, data, data_src, category'
                )->fetchAll()) {
                    $update = [];
                    foreach ($fields as $field) {
                        $dataSrc = json_decode($field['data_src'], true);
                        $data = json_decode($field['data'], true);

                        if (($decodedVars['category'] === $field['category'] && ($data['showInWebOtherPlacement'] ?? false) === false)
                            || $decodedVars['category'] === ($data['showInWebOtherPlacement'] ?? false)
                        ) {
                            $dataSrc['showInWebOtherOrd']['Val'] += 10;
                            $update[$field['id']] = ['data_src' => $dataSrc];
                        }
                    }
                    if ($update) {
                        $this->Totum->getTable('tables_fields')->reCalculateFromOvers(['modify' => $update]);
                    }
                }

                $this->update(
                    ['ord=jsonb_build_object($$v$$,  ((ord->>\'v\')::integer+10)::text)'],
                    [
                        'table_id' => $tableRowId
                        , 'category' => $category
                        , '!id=' => $id
                        , '>Nord' => (int)$this->afterField
                    ]
                );
                $this->afterField = null;
            }

            if ($tableRow['type'] !== 'calcs') {
                $table = $this->Totum->getTable($tableRow, null);
                $table->addField($id);
                $table->initFields(true);
            }
        }
        $this->Sql->transactionCommit();
        return $id;
    }

    /**
     * @param mixed $afterField
     */
    public function setAfterField($afterField): void
    {
        $this->afterField = $afterField;
    }


    protected function checkParams(&$params, $tableRowId, $oldDataSrc = null, $category = null)
    {
        if (empty($params['data_src'])) {
            return;
        }
        if (is_null($category)) {
            $category = json_decode($params['category'], true)['v'];
        }

        $newData = json_decode($params['data_src'], true)['v'];

        $tableRow = $this->Totum->getTableRow($tableRowId);

        if ($newData['type']['Val'] === 'text') {
            $newData['viewTextMaxLength']['Val'] = (int)$newData['viewTextMaxLength']['Val'];
        }

        if ($category === 'footer' && !is_subclass_of(Totum::getTableClass($tableRow), JsonTables::class)) {
            throw new errorException($this->translate('You cannot create a [[footer]] field for [[non-calculated]] tables.'));
        }
    }

    private function fieldExits($table_id, $name, $version)
    {
        return !!$this->executePrepared(
            true,
            ['table_id' => $table_id, 'name' => $name, 'version' => $version],
            'id',
            null,
            '0,1'
        )->fetch();
    }
}
