<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.10.16
 * Time: 19:31
 */

namespace totum\models;

use totum\common\Auth;
use totum\common\errorException;
use totum\common\Model;
use totum\common\sql\Sql;
use totum\common\Totum;
use totum\models\traits\WithTotumTrait;
use totum\fieldTypes\Comments;
use totum\tableTypes\JsonTables;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;

class TablesFields extends Model
{
    use WithTotumTrait;

    /**
     * @var |null
     */
    private $afterField=null;

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
                        throw new errorException('Нельзя удалять системные поля');
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
        if (array_key_exists('data_src', $params)) {
            $this->checkParams(
                $params,
                $oldRow['table_id']['v'],
                $oldRow['data_src']['v'],
                $oldRow['category']['v']
            );
        }

        if (key_exists('name', $params)) {
            $name = json_decode($params['name'], true)['v'];
            if ($this->getPrepared(['table_name' => $oldRow['table_name']['v'], 'name' => $name])) {
                throw new errorException('Поле name [[' . $name . ']] уже есть в таблице');
            }
            $tableRow = $this->Totum->getTableRow($oldRow['table_name']['v']);

            if ($oldRow['category']['v'] === 'column' && Totum::isRealTable($tableRow)) {
                $this->Sql->exec("ALTER TABLE {$oldRow['table_name']['v']} RENAME {$oldRow['name']['v']} TO {$name}");
            }
        }

        $r = parent::update($params, $where);

        $table = $this->Totum->getTableRow($oldRow['table_id']['v']);
        if ($table && $table['type'] !== 'tmp' && $table['type'] !== 'calcs') {
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
        if (!array_key_exists('data_src', $vars) || !($newData = json_decode(
            $vars['data_src'],
            true
        )['v'])
        ) {
            throw new errorException('Необходимо заполнить параметры');
        }

        $decodedVars = array_map(
            function ($v) {
                return json_decode($v, true)['v'];
            },
            $vars
        );

        if (!$decodedVars['table_id']) {
            throw new errorException('Выберите таблицу');
        }
        if ($decodedVars['name'] === 'new_field') {
            throw new errorException('Name поля не может быть new_field');
        }
        if (in_array($decodedVars['name'], Model::serviceFields)) {
            throw new errorException('[[' . $decodedVars['name'] . ']] - название технического поля. Выберите другое имя');
        }
        if (in_array($decodedVars['name'], Model::RESERVED_WORDS)) {
            throw new errorException('[[' . $decodedVars['name'] . ']] - слово зарезервировано в sql. Выберите другое имя');
        }
        if (!is_array($vars['table_id'])) {
            $tableRowId = json_decode($vars['table_id'], true)['v'];
        } else {
            $tableRowId = $vars['table_id']['v'];
        }


        $name = $decodedVars['name'];
        $category = $decodedVars['category'];

        if ($this->fieldExits($decodedVars['table_id'], $decodedVars['name'], $decodedVars['version'])) {
            throw new errorException('Поле [[' . $name . ']] уже существует в этой таблице.');
        }

        /*$this->checkParams($vars, $tableRowId);*/

        $this->Sql->transactionStart();
        if ($id = parent::insertPrepared($vars, $returning, $ignore, $cacheIt)) {
            $tableRow = $this->Totum->getTableRow($tableRowId);

            if (!is_null($this->afterField)) {
                $this->update(
                    ['ord=jsonb_build_object($$v$$,  ((ord->>\'v\')::integer+10)::text)'],
                    [
                        'table_id' => $tableRowId
                        , 'category' => $category
                        , '!id=' => $id
                        , '>ord' => (int)$this->afterField
                    ]
                );
                $this->afterField=null;
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


        if ($category === 'filter') {
            if (!in_array($tableRow['type'], ['calcs', 'globcalcs'])) {

                //Для реальных таблиц проставить индексы через изменение таблицы "Список таблиц"

                $oldColumnName = $oldDataSrc['column']["Val"] ?? '';
                $newColumnName = $newData['column']["Val"] ?? '';

                if ($newColumnName !== $oldColumnName) {
                    $fields = $this->Totum->getTable($tableRowId)->getFields();
                    $newIndexes = $tableRow['indexes'];
                    if (!empty($newColumnName)) {
                        if (!empty($fields[$newColumnName]) && $fields[$newColumnName]['category'] === 'column') {
                            if (!in_array($newColumnName, $newIndexes)) {
                                $newIndexes[] = $newColumnName;
                            }
                        }
                    }

                    if (!empty($fields[$oldColumnName])) {
                        $countWithColumn = 0;
                        foreach ($fields as $k => $v) {
                            if ($v['category'] === 'filter' && ($v['column'] ?? '') === $oldColumnName) {
                                $countWithColumn++;
                            }
                        }
                        if ($countWithColumn < 2) {
                            foreach ($newIndexes as $k => $index) {
                                if ($index === $oldColumnName) {
                                    unset($newIndexes[$k]);
                                }
                            }
                            $newIndexes = array_values($newIndexes);
                        }
                    }
                    if ($newIndexes !== $tableRow['indexes']) {
                        $this->Totum->getTable('tables')->reCalculateFromOvers(
                            ['modify' => [$tableRow['id'] => ['indexes' => $newIndexes]]]
                        );
                    }
                }
            }
        }
        if ($newData['type']['Val'] === 'text') {
            $newData['viewTextMaxLength']['Val'] = (int)$newData['viewTextMaxLength']['Val'];
            if ($newData['viewTextMaxLength']['Val'] > 500) {
                throw new errorException('Ограничение размера видимости текста в веб - не больше 500 символов');
            }
        }

        if ($category === 'footer' && !is_subclass_of(Totum::getTableClass($tableRow), JsonTables::class)) {
            throw new errorException('Нельзя создать поле [[футера]] [[не для рассчетных]] таблиц');
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
