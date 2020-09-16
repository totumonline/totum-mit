<?php

if (is_null($isCreatorView ?? null)) {
    return;
} ?>
<nav class="totbar-default navbar-default">
    <div class="container-fluid">
        <div
                id="bs-example-navbar-collapse-1">
            <ul class="nav navbar-nav">
                <?php

                foreach ($topBranches ?? [] as $branch) {
                    ?>
                    <li class="<?= $branch['active'] ?? false ? 'active' : '' ?>">
                        <a href="<?= $branch['href'] ?>">
                            <?= htmlspecialchars($branch['title']) ?>
                        </a></li>
                    <?php
                }
                if ($isCreatorView) {
                    if ($Branch ?? false) { ?>
                        <li class="plus-top-branch"
                            onClick="(new EditPanel("
                            tree", BootstrapDialog.TYPE_DANGER, {id: <?= $Branch ?>})).then(function (json) { if (json) window.location.reload() })">
                        <a><i class="fa fa-edit"></i></a></li>
                        <?php
                    }
                    ?>
                    <li class="plus-top-branch"
                        onClick="(new EditPanel("
                        tree", BootstrapDialog.TYPE_DANGER, {})).then(function (json) { if (json) window.location.href=('/Table/'+json.chdata.rows[Object.keys(json.chdata.rows)[0]].id+'/');})">
                    <a><i class="fa fa-plus"></i></a></li>
                    <?php
                } ?>

            </ul>

            <ul class="nav navbar-nav navbar-right">
                <li class="navbar-text">
                    <span class="btn btn-sm btn-<?= $isCreatorView ? 'danger' : 'default' ?>" id="docs-link"
                          data-type="<?= $isCreatorView ? 'dev' : 'user' ?>"><i class="fa fa-question"></i> </span>
                    <span class="btn btn-default btn-sm" style="margin-top: -3px;" id="bell-notifications"
                          data-periodicity="<?= $notification_period ?? 0 ?>"><i
                                class="fa fa-bell"></i></span>
                </li>

                <li class="navbar-text"
                    id="UserFio"><?= htmlspecialchars($UserName) ?></li>
                <li><a href="/Auth/logout/">Выход</a></li>
            </ul>
            <?php
            if ($reUsers ?? null) {
                ?>
                <script>
                    (function () {
                        let reUsers = <?=json_encode($reUsers, JSON_UNESCAPED_UNICODE);?>;
                        App.reUserInterface(reUsers, <?=$isCreatorNotItself ? 'true' : 'false'?>);
                    }());
                </script>
                <?php
            } ?>
        </div><!-- /.navbar-collapse -->
    </div><!-- /.container-fluid -->
</nav>
<div id="nav-top-line"></div>