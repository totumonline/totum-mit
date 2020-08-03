<?

use totum\common\Settings;

if (!\totum\common\Auth::isAuthorized()) return;
?>
<div class="Tree pull-left" id="LeftTree">
    <div id="TreeMaximizer"><span class="fa fa-bars"></span></div>
    <div class="TreeContainer">
        <a class="totum-brand" href="/"><span><?=Settings::init()->getParam('totum_name')??'TOTUM'?></span></a> <span class="fa fa-times" id="TreeMinimizer"></span>
        <?php if(!empty($Branch)){?>
            <div id="branch-title"><?=$BranchTitle ?></div>
        <?php }?>
        <div id="leftTree"
             style=""></div>
    </div>
</div>
<script>
    addTree('/<?=$Module?><?=!empty($Branch) ? '/' . $Branch : ''?>/', <?echo json_encode(($treeData??[]), JSON_UNESCAPED_UNICODE)?>, <?=json_encode($isCreatorView??false)?>);
</script>