<?php
use totum\common\Auth;
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
            <?php
        }
    }
    return;
} ?>
<div id="table" style=""></div>
<script>
    if (window.parent == window) {
        $('#table').css('padding-top', 50);
    }else{
        $('head').append('<style>.pcTable-beforeSpace{padding-bottom: 15px; margin-left: 56px;}</style>');
    }
    var TableModel = App.models.table(window.location.href, {'updated': <?=($table['updated'])?><?=($this->Table->getTableRow()['sess_hash'] ?? null) ? ', sess_hash: "' . $this->Table->getTableRow()['sess_hash'] . '"' : ''?>})
</script>
<?php
$forJsonObj = [
    'type' => $table['type']
    , 'control' => [
        'adding' => (!($table['__blocked'] ?? null) && $table['adding'])
        , 'deleting' => (!($table['__blocked'] ?? null) && $table['deleting'])
        , 'duplicating' => (!($table['__blocked'] ?? null) && $table['duplicating'])
        , 'editing' => (!($table['__blocked'] ?? null) && !$onlyRead)
    ]
    , 'tableRow' => $this->getTableRowForClient($this->Table->getTableRow())
    , 'f' => $table['f']
    , 'withCsvButtons' => $table['withCsvButtons']
    , 'withCsvEditButtons' => $table['withCsvEditButtons']
    , 'isAnonim' => true
    , 'filterDataCrypted' => $table['filtersString']
    , 'fields' => $table['fields'] ?? []
    , 'data' => $table['data'] ?? []
    , 'data_params' => $table['params'] ?? []
    , 'checkIsUpdated' => 0
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
