<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/libs.css?v=005a92c">
    <script src="/js/libs.js?v=f52b4ad"></script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/main.css?v=bfb9482">
    <script src="/js/main.js?v=2ccee68"></script>

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
    App.fullScreenProcesses.showCog();
</script>
<div class="page_content tree-minifyed">
    <div id="notifies"></div>
    <?php
    if (!empty($error)) {
        echo '<div class="panel panel-danger"><div class="panel-body">' . $error . '</div></div>';
    } ?>
    <?php include static::$contentTemplate; ?>
</div>
<script>
    $(function(){
        App.fullScreenProcesses.hideCog();
    })
</script>
</body>
</html>