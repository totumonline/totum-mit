<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/libs.css?v=880594f">
    <script src="/js/libs.js?v=40c5077"></script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/main.css?v=2b66cb1">

    <?php
    if (\totum\common\Auth::isCreator()) { ?>
        <script src="/js/functions.json?v=9d17f34"></script>
        <?php
        if (!empty($GLOBALS['CalculateExtentions']) && is_object($GLOBALS['CalculateExtentions']) && property_exists($GLOBALS['CalculateExtentions'],
                'jsTemplates')) {
            echo '<script>App.functions=App.functions.concat(' . $GLOBALS['CalculateExtentions']->jsTemplates . ')</script>';
        } ?>
    <?php
    } ?>

    <script src="/js/main.js?v=fcc00c3"></script>


    <link rel="shortcut icon" type="image/png" href="/fls/6_favicon.png"/>

    <?php
    include dirname(__FILE__) . DIRECTORY_SEPARATOR . '__titles_descriptions.php';

    ?>
    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">

</head>
<body id="pk"
      class="lock">
<noscript>
    Для работы с системой необходимо включить JavaScript в настройках броузера
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<script>
    $(function () {
        App.fullScreenProcesses.showCog();
    })
</script>
<div class="page_content">
    <?php include dirname(__FILE__) . '/__tree.php'; ?>
    <?php include dirname(__FILE__) . '/__header.php' ?>
    <div id="notifies"></div>
    <?php
    if (!empty($error)) {
        echo '<div class="panel panel-danger"><div class="panel-body">' . $error . '</div></div>';
    } ?>
    <?php include static::$contentTemplate; ?>
</div>
<?php include dirname(__FILE__) . '/__footer.php' ?>

<script>
    $(function () {
        App.fullScreenProcesses.hideCog();
    })
</script>
</body>
</html>