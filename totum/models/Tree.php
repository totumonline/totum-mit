<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.10.16
 * Time: 11:26
 */

namespace totum\models;

use totum\common\Model;
use totum\config\Conf;

class Tree extends Model
{

    /**
     * @var mixed|string|Model
     */
    private $treeVModel;

    public function insertPrepared($vars, $returning = 'idFieldName', $ignore = false, $cacheIt = true)
    {
        $id = parent::insertPrepared($vars, $returning, $ignore, $cacheIt);
        if ($id) {
            $this->update(['title' => $vars['title']], ['id' => $id]);
        }
        return $id;
    }


    public function update($params, $where, $oldRow = null): int
    {
        $r = $params ? parent::update($params, $where, $oldRow) : 0;
        if (array_key_exists('parent_id', $params) || empty($oldRow['top']['v'])) {
            $treeVModel = $this->treeVModel ?? $this->treeVModel = (new Model(
                    $this->Sql,
                    Conf::getTableNameByModel(TreeV::class),
                    $this->Lang,
                    null,
                    true
                ));

            foreach ($this->getColumn('id') as $id) {
                $params = [];
                $where = ['id' => $id];
                $treeVRow = $treeVModel->executePrepared(true, $where, 'top')->fetch();
                $params['top'] = json_encode(['v' => $treeVRow['top'] ? (string)$treeVRow['top'] : null],
                    JSON_UNESCAPED_UNICODE);
                parent::update($params, $where, null);
            }
        }
        return $r;
    }

    public function getBranchesByTables($branchId = null, array $tables = null, array $roles = null)
    {
        if (empty($tables)) {
            $tables = [0];
        }
        $rolesSql = '';

        $quotedTables = implode(
            ',',
            $this->Sql->quote($tables)
        );

        if (!empty($roles)) {
            foreach ($roles as &$role) {
                $role = $this->Sql->quote(strval($role));
            }
            unset($role);
            $roles = implode(',', $roles);
            $rolesSql = <<<SQL
 UNION 
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE is_del = false AND type='link' AND (ARRAY(SELECT * FROM   jsonb_array_elements_text(roles::jsonb) elem ) && ARRAY[{$roles}] OR roles='[]')
SQL;
        } else {
            $roles = '';
        }

        $anchorsSql = <<<SQL
 UNION 
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE is_del = false AND type='anchor' AND (ARRAY(SELECT * FROM   jsonb_array_elements_text(roles::jsonb) elem ) && ARRAY[{$roles}] OR roles='[]') AND default_table IN (
       select id from tables where (ARRAY(SELECT * FROM   jsonb_array_elements_text(edit_roles->'v') ) && ARRAY[{$roles}]::text[]) OR (ARRAY(SELECT * FROM   jsonb_array_elements_text(read_roles->'v') ) && ARRAY[{$roles}]::text[])
    ) 
SQL;

        $r = $this->Sql->getAll($q = 'WITH RECURSIVE r AS (
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE is_del = false AND id IN (select (tree_node_id->>\'v\')::integer from tables where type->>\'v\'!=\'calcs\' AND id in (' . $quotedTables . '))
   ' . $rolesSql . '
   ' . $anchorsSql . '
    UNION 
    SELECT tree__v.parent_id, tree__v.id, tree__v.title, tree__v.ord, tree__v.top, tree__v.default_table, tree__v.type, tree__v.icon, tree__v.link
    FROM tree__v JOIN r ON tree__v.id = r.parent_id
    )
    select * FROM r where top!=0 AND (parent_id is null ' . ($branchId ? ' OR top=' . $branchId : '') . ') order by ord');
        return $r;
    }

    public function getBranchesForCreator($branchId = null)
    {
        return $this->Sql->getAll('WITH RECURSIVE r AS (
    SELECT parent_id, id, title, ord, top,default_table, type, icon,link
    FROM tree__v
    UNION 
    SELECT parent_id, id, title, ord, top, default_table, type, icon,link
    FROM tree__v
    WHERE type=\'link\'
    UNION 
    SELECT tree__v.parent_id, tree__v.id, tree__v.title, tree__v.ord, tree__v.top,tree__v.default_table, tree__v.type, tree__v.icon, tree__v.link
    FROM tree__v JOIN r ON tree__v.id = r.parent_id 
    
    )
    select * FROM r where top!=0 AND (parent_id is null ' . ($branchId ? ' OR top=' . $branchId : '') . ') order by ord');
    }
}
