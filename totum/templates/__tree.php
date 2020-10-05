<?php
if (is_null($isCreatorView ?? null)) {
    return;
}
?>
<div class="Tree pull-left" id="LeftTree">
    <div id="TreeMaximizer"><span class="fa fa-bars"></span></div>
    <div class="TreeContainer">
        <a class="totum-brand" href="/"><span><?= $schema_name ?></span></a> <span class="fa fa-times"
                                                                                   id="TreeMinimizer"></span>
        <?php
        if (!empty($Branch)) { ?>
            <div id="branch-title"><?= $BranchTitle ?? null ?></div>
        <?php
        } ?>
        <div id="leftTree"
             style=""></div>
    </div>
</div>
<script>
    addTree('<?=$ModulePath?><?=!empty($Branch) ? $Branch . '/' : ''?>', <?php echo json_encode(
    ($treeData ?? []),
    JSON_UNESCAPED_UNICODE
)?>, <?=json_encode($isCreatorView ?? false)?>);
</script>