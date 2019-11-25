<?

use \totum\common\Auth;
use \totum\models\Tree;

if (!\totum\common\Auth::isAuthorized()) {
    return;
} ?>
<nav class="totbar-default navbar-default">
    <div class="container-fluid">
        <div
                id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <?

                if (is_null($topBranches??null) && Auth::isCreator()) {
                    $topBranches = Tree::init()->getBranchesForCreator(null);
                }

                $topBranches = $topBranches ?? Tree::init()->getBranchesByTables(null,
                        array_keys(Auth::$aUser->getTreeTables()), Auth::$aUser->getRoles());

                foreach ($topBranches as $branch) {
                    $href = '/Table/' . $branch['id'] . '/';
                    if (!empty($branch['default_table']) && Auth::$aUser->isTableInAccess($branch['default_table'])) $href .= $branch['default_table'] . '/';
                    ?>
                    <li class="<?= ($Module == 'Table' && !empty($Branch)) && $branch['id'] == $Branch ? 'active' : '' ?>">
                        <a href="<?= $href ?>">
                            <?= htmlspecialchars($branch['title']) ?>
                        </a></li>
                <? } ?>
            </ul>

            <ul class="nav navbar-nav navbar-right"><li class="navbar-text"><span class="btn btn-sm btn-<?=Auth::isCreator()?'danger':'default'?>" id="docs-link" data-type="<?=Auth::isCreator()?'dev':'user'?>"><i class="fa fa-question"></i> </span></li>

                <li class="navbar-text"
                    id="UserFio"><?= htmlspecialchars(Auth::getUserVar('fio')) ?></li>
                <li><a href="/Auth/logout/">Выход</a></li>
            </ul>
            <?php
            if (Auth::isCreator() || Auth::isCreatorOnShadow()) {
                ?>
                <script>
                    (function () {
                        let reUsers =<?=json_encode(\totum\models\User::init()->getFieldIndexedById('fio',
                            ['is_del' => false, 'interface' => 'web', 'interface' => 'web', 'login->>\'v\'!=\'service\'', 'login->>\'v\'!=\'cron\'']),
                            JSON_UNESCAPED_UNICODE);?>;
                        App.reUserInterface(reUsers, <?=Auth::isCreatorNotItself() ? 'true' : 'false'?>);
                    }());
                </script>
            <? } ?>
        </div><!-- /.navbar-collapse -->
    </div><!-- /.container-fluid -->
</nav>
<div id="nav-top-line"></div>
