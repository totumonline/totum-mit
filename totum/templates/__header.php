<?

use \totum\common\Auth;
use totum\models\Table;
use \totum\models\Tree;
use totum\models\User;
use totum\tableTypes\tableTypes;

if (!Auth::isAuthorized()) {
    return;
} ?>
    <nav class="totbar-default navbar-default">
        <div class="container-fluid">
            <div
                    id="bs-example-navbar-collapse-1">
                <ul class="nav navbar-nav">
                    <?

                    if (is_null($topBranches ?? null) && Auth::isCreator()) {
                        $topBranches = Tree::init()->getBranchesForCreator(null);
                    }

                    $topBranches = $topBranches ?? Tree::init()->getBranchesByTables(null,
                            array_keys(Auth::$aUser->getTreeTables()),
                            Auth::$aUser->getRoles());

                    foreach ($topBranches as $branch) {
                        $href = '/Table/' . $branch['id'] . '/';
                        if (!empty($branch['default_table']) && Auth::$aUser->isTableInAccess($branch['default_table'])) $href .= $branch['default_table'] . '/';
                        ?>
                        <li class="<?= ($Module == 'Table' && !empty($Branch)) && $branch['id'] == $Branch ? 'active' : '' ?>">
                            <a href="<?= $href ?>">
                                <?= htmlspecialchars($branch['title']) ?>
                            </a></li>
                    <? }
                    if (Auth::isCreator()) {?>
                        <li class="plus-top-branch" onClick="(new EditPanel(window.TREE_TABLE_ID, BootstrapDialog.TYPE_DANGER, {})).then(function (json) { if (json) window.location.href=('/Table/'+json.chdata.rows[Object.keys(json.chdata.rows)[0]].id+'/');})"><a><i class="fa fa-plus"></i></a></li>
                    <?}?>

                </ul>

                <ul class="nav navbar-nav navbar-right">
                    <li class="navbar-text">
                    <span class="btn btn-sm btn-<?= Auth::isCreator() ? 'danger' : 'default' ?>" id="docs-link"
                          data-type="<?= Auth::isCreator() ? 'dev' : 'user' ?>"><i class="fa fa-question"></i> </span>
                        <span class="btn btn-default btn-sm" style="margin-top: -3px;" id="bell-notifications"
                              data-periodicity="<?= tableTypes::getTableByName('notifications')->getTbl()['params']['periodicity']['v'] ?? 0 ?>"><i
                                    class="fa fa-bell"></i></span>
                    </li>

                    <li class="navbar-text"
                        id="UserFio"><?= htmlspecialchars(Auth::getUserVar('fio')) ?></li>
                    <li><a href="/Auth/logout/">Выход</a></li>
                </ul>
                <?php
                if (Auth::isCreator() || Auth::isCreatorOnShadow()) {
                    ?>
                    <script>
                        (function () {
                            let reUsers = <?=json_encode(User::init()->getFieldIndexedById('fio',
                                ['is_del' => false, 'interface' => 'web', 'on_off' => 'true', 'login->>\'v\'!=\'service\'', 'login->>\'v\'!=\'cron\'']),
                                JSON_UNESCAPED_UNICODE);?>;
                            App.reUserInterface(reUsers, <?=Auth::isCreatorNotItself() ? 'true' : 'false'?>);
                        }());
                    </script>
                <? } ?>
            </div><!-- /.navbar-collapse -->
        </div><!-- /.container-fluid -->
    </nav>
    <div id="nav-top-line"></div>
<?php if (Auth::isCreator()) { ?>
    <script>window.TREE_TABLE_ID =  <?=Table::getTableRowByName('tree')['id'];?>;</script>
<?php } ?>