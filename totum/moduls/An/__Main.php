<? use totum\common\Auth;
use totum\common\Crypt;
use totum\models\Table;

if (empty($table)) {
    if (empty($error)) {
        if (!empty($html)) echo '<div style="padding:40px; font-family: \'Open Sans\', sans-serif" id="text_main_page">'.$html.'</div>';
        else {
            ?>
            <div class="panel panel-default">
                <div class="panel-body">
                    Выберите таблицу
                </div>
            </div>
            <?
        }
    }
    return;
} ?>
<div id="table" style="padding-top: 50px"></div>
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
        ) + $this->Table->getTableRow() + (is_a($this->Table,
            \totum\tableTypes\calcsTable::class) ? ['cycle_id' => $this->Table->getCycle()->getId()] : [])
    , 'f' => $table['f']
    , 'withCsvButtons' => $table['withCsvButtons']
    , 'withCsvEditButtons' => $table['withCsvEditButtons']
    , 'isAnonim' => true
    , 'filterDataCrypted' => $table['filtersString']
    , 'fields' => $table['fields'] ?? []
    , 'data' => $table['data'] ?? []
    , 'data_params' => $table['params'] ?? []
    , 'checkIsUpdated' => ($table['type'] == 'tmp' || Auth::$aUser->isOuter() || in_array($this->Table->getTableRow()['actual'],
            ['none', 'disable'])) ? 0 : 1
    , 'checkForNotifications' => (($row = Table::getTableRowByName('notifications')) && ($tableNotifications = \totum\tableTypes\tableTypes::getTable($row))) ? $tableNotifications->getTbl()['params']['periodicity']['v'] : 0
    , 'ROLESLIST' => []
    , 'isMain' => true
];
if (!empty($_GET['a'])) {
    $forJsonObj['addVars'] = $_GET['a'];
}
?>
<div style="padding-top: 20px">&nbsp;</div>
<script>
    let TableConfig = <?=json_encode($forJsonObj, JSON_UNESCAPED_UNICODE);?>;
    TableConfig.model = TableModel;
    $(function () {
        new App.pcTableMain($('#table'), TableConfig);
    })
</script>
