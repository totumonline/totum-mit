<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/libs.css?v=ead40ab">
    <script src="/js/libs.js?v=665342a"></script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/main.css?v=b125e32">

    <?php
    if ($isCreatorView ?? null) { ?>
        <script src="/js/functions.js?v=2885b25"></script>
        <?php
        echo '<script>App.functions=App.functions.concat(' . $this->Config->getExtFunctionsTemplates() . ')</script>';
        ?>
        <?php
    } ?>

    <script src="/js/main.js?v=7a4a1c5"></script>
    <script src="/js/i18n/<?= $this->Config->getLang() ?>.js?5"></script>
    <script>App.lang = App.langs["<?= $this->Config->getLang() ?>"]</script>


    <link rel="shortcut icon" type="image/png" href="/fls/6_favicon.png"/>

    <?php
    include dirname(__FILE__) . DIRECTORY_SEPARATOR . '__titles_descriptions.php';

    ?>
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">

</head>
<body id="pk"
      class="lock">
<noscript>
    <?= $this->translate('To work with the system you need to enable JavaScript in your browser settings') ?>
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<script>
    $(function () {
        App.fullScreenProcesses.showCog();
    })
</script>
<div class="page_content">
    <?php
    include dirname(__FILE__) . '/__tree.php'; ?>
    <?php
    include dirname(__FILE__) . '/__header.php' ?>
    <div id="notifies"></div>
    <?php
    if (!empty($error)) {
        echo '<div class="panel panel-danger"><div class="panel-body">' . $error . '</div></div>';
    } ?>
    <?php
    include static::$contentTemplate; ?>
</div>
<div id="TOTUM_FOOTER">
    <?= $totumFooter ?? '' ?>

</div>


<script>
    $(function () {
        App.fullScreenProcesses.hideCog();
    })
</script>
</body>
</html>