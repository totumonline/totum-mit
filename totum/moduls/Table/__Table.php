<? use totum\common\Auth;
use totum\common\Crypt;
use totum\models\Table;

if (empty($table)) {
    if (empty($error)) {
        if (!empty($html)) echo '<div style="padding:40px; font-family: \'Open Sans\', sans-serif" id="text_main_page">' . $html . '</div>';
        else {
            ?>

            <div class="panel panel-default">
                <div class="panel-body">
                    Выберите таблицу
                </div>
            </div>

            <?
        }
        echo '<div id="page-tree"></div>';
    }
    return;
} ?>
<div id="table"></div>
<script>
    var TableModel = App.models.table(window.location.href, {'updated': <?=($table['updated'])?><?=($this->Table->getTableRow()['sess_hash'] ?? null) ? ', sess_hash: "' . $this->Table->getTableRow()['sess_hash'] . '"' : ''?>})
</script>
<?
$forJsonObj = [
    'type' => $table['type']
    , 'control' => [
        'adding' => (!($table['__blocked'] ?? null) && $table['adding'])
        , 'deleting' => (!($table['__blocked'] ?? null) && $table['deleting'])
        , 'duplicating' => (!($table['__blocked'] ?? null) && $table['duplicating'])
        , 'editing' => (!($table['__blocked'] ?? null) && !$onlyRead)
    ]
    , 'tableRow' => ($this->Table->getTableRow()['type'] == 'calcs' ? ['fields_sets' => $this->changeFieldsSets()] :
            ['__is_in_favorites' =>
                !key_exists($this->Table->getTableRow()['id'],
                    Auth::$aUser->getTreeTables()) ? null : in_array($this->Table->getTableRow()['id'],
                    Auth::$aUser->getFavoriteTables())]
        ) + $this->getTableRowForClient($this->Table->getTableRow()) + (is_a($this->Table,
            \totum\tableTypes\calcsTable::class) ? ['cycle_id' => $this->Table->getCycle()->getId()] : [])
    , 'f' => $table['f']
    , 'withCsvButtons' => $table['withCsvButtons']
    , 'withCsvEditButtons' => $table['withCsvEditButtons']
    , 'isCreatorView' => $isCreatorView
    , 'filterDataCrypted' => $table['filtersString']
//    , 'notCorrectOrder' => ($table['notCorrectOrder']??false)
    , 'fields' => $table['fields'] ?? []
    , 'data' => $table['data'] ?? []
    , 'data_params' => $table['params'] ?? []
    , 'checkIsUpdated' => ($table['type'] == 'tmp' || Auth::$aUser->isOuter() || in_array($this->Table->getTableRow()['actual'],
            ['none', 'disable'])) ? 0 : 1
    , 'ROLESLIST' => ($isCreatorView) ? \totum\common\Model::init('roles')->getFieldIndexedById('title',
        ['is_del' => false]) : []
    , 'isMain' => true
];

if ($isCreatorView) {
    $forJsonObj['TableFields'] = ['branchId' => 1, 'id' => \totum\models\TablesFields::TableId];
    $forJsonObj['Tables'] = ['branchId' => 1, 'id' => \totum\models\Table::$TableId];

    $forJsonObj['hidden_fields'] = $table['hidden_fields'] ?? [];
    if ($this->Table->getTableRow()['type'] === 'calcs') {
        $forJsonObj['TablesCyclesVersions'] = ['branchId' => 1
            , 'id' => \totum\common\Model::init('tables')->getField('id', ['name' => 'calcstable_cycle_version'])
            , 'version_filters' => Crypt::getCrypted(json_encode([
                'fl_table' => $this->Table->getTableRow()['tree_node_id'],
                'fl_cycle' => $this->Table->getCycle()->getId()
            ],
                JSON_UNESCAPED_UNICODE))
        ];
        $forJsonObj['TablesVersions'] = ['branchId' => 1
            , 'id' => \totum\common\Model::init('tables')->getField('id', ['name' => 'calcstable_versions'])
            , 'version_filters' => Crypt::getCrypted(json_encode([
                'fl_table' => $this->Table->getTableRow()['tree_node_id'],
            ],
                JSON_UNESCAPED_UNICODE))
        ];
        $forJsonObj['cycle'] = $this->Table->getCycle()->getId();
    }

}
if (!empty($_GET['a'])) {
    $forJsonObj['addVars'] = $_GET['a'];
}
?>

<script>
    let TableConfig = <?=json_encode($forJsonObj, JSON_UNESCAPED_UNICODE);?>;
    TableConfig.model = TableModel;
    <? if ($LOGS ?? null) {

        $jsLog = json_encode($LOGS, JSON_UNESCAPED_UNICODE | JSON_OBJECT_AS_ARRAY);
        echo 'TableConfig.LOGS=' . ($jsLog !== false ? $jsLog : '[{"text":"jsonError: ' . json_last_error_msg() . '","type":"error"}];') . ';';
        if ($jsLog === false) {
            $FullLOGS = [["text" => 'jsonError: ' . json_last_error_msg(), "type" => "error"]];
        }
    }
    ?>
    <? if ($FullLOGS ?? null) {
        $jsLog = json_encode($FullLOGS, JSON_UNESCAPED_UNICODE | JSON_OBJECT_AS_ARRAY);
        echo 'TableConfig.FullLOGS=' . ($jsLog !== false ? $jsLog : '[{"text":"jsonError: ' . json_last_error_msg() . '"}];') . ';';
    }?>

    <? if ($FieldLOGS ?? null) {
        $jsLog = json_encode($FieldLOGS, JSON_UNESCAPED_UNICODE | JSON_OBJECT_AS_ARRAY);
        echo 'TableConfig.FieldLOGS=' . ($jsLog !== false ? $jsLog : '[{"text":"jsonError: ' . json_last_error_msg() . '"}];') . ';';
    }?>
    $(function () {
        new App.pcTableMain($('#table'), TableConfig);
    })

</script>
