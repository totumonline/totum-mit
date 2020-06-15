<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.10.16
 * Time: 11:26
 */

namespace totum\models;


use totum\common\Model;
use totum\common\Sql;
use totum\tableTypes\tableTypes;

class Tree extends Model
{

    function insert($vars, $returning = 'idFieldName', $ignore = false)
    {
        $id = parent::insert($vars, $returning, $ignore);
        if ($id) {
            $this->update(['title' => $vars['title']], ['id' => $id]);
        }
        return $id;
    }

    function update($params, $where, $ignore = 0, $oldRow = null): int
    {
        $r = $params ? parent::update($params, $where, $ignore, $oldRow) : 0;
        if (array_key_exists('parent_id', $params) || empty($oldRow['top']['v'])) {
            foreach (TreeV::init()->getField('id', [], null, null) as $id) {
                $params = [];
                $where = ['id' => $id];
                $treeVRow = TreeV::init()->get($where, 'top');
                $params['top'] = json_encode(['v' => $treeVRow['top']], JSON_UNESCAPED_UNICODE);
                parent::update($params, $where, $ignore, null);
            }
            $Tables = tableTypes::getTable(Table::getTableRowById(Table::$TableId));
            $ids = $Tables->loadRowsByParams([['field' => 'type', 'operator' => '!=', 'value' => "calcs"]]);
            $Tables->reCalculateFromOvers(['modify' => array_map(function () {
                return [];
            },
                array_flip($ids))]);
        }
        return $r;
    }

    function getBranchesByTables($branchId = null, array $tables = null, array $roles = null)
    {
        if (empty($tables)) $tables = [0];
        $rolesSql = '';

        $quotedTables = implode(',',
            Sql::quote($tables));

        if (!empty($roles)) {
            foreach ($roles as &$role) $role = Sql::quote(strval($role));
            unset($role);
            $roles = implode(',', $roles);
            $rolesSql = <<<SQL
 UNION 
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE type='link' AND (ARRAY(SELECT * FROM   jsonb_array_elements_text(roles::jsonb) elem ) && ARRAY[{$roles}] OR roles='[]')
SQL;
        }

        $anchorsSql = <<<SQL
 UNION 
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE type='anchor' AND default_table IN (
       select id from tables where (ARRAY(SELECT * FROM   jsonb_array_elements_text(edit_roles->'v') ) && ARRAY[{$roles}]) OR (ARRAY(SELECT * FROM   jsonb_array_elements_text(read_roles->'v') ) && ARRAY[{$roles}])
    ) 
SQL;

        $r= Sql::getAll($q='WITH RECURSIVE r AS (
    SELECT parent_id, id, title, ord, top, default_table, type, icon, link
    FROM tree__v
    WHERE id IN (select (tree_node_id->>\'v\')::integer from tables where type->>\'v\'!=\'calcs\' AND id in (' . $quotedTables . '))
   ' . $rolesSql . '
   ' . $anchorsSql . '
    UNION 
    SELECT tree__v.parent_id, tree__v.id, tree__v.title, tree__v.ord, tree__v.top, tree__v.default_table, tree__v.type, tree__v.icon, tree__v.link
    FROM tree__v JOIN r ON tree__v.id = r.parent_id
    )
    select * FROM r where top!=0 AND (parent_id is null ' . ($branchId ? ' OR top=' . $branchId : '') . ') order by ord');
        return $r;
    }

    function getBranchesForCreator($branchId = null)
    {
        return Sql::getAll('WITH RECURSIVE r AS (
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