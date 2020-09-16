<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="stylesheet"
          type="text/css"
          href="/css/forms.css">
    <script src="/js/forms.js"></script>
    <link rel="shortcut icon" type="image/png" href="/fls/297_favicon.png"/>

    <title><?= !empty($title) ? $title . ' — ' : '' ?>Totum</title>
    <?php
    $host = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
    ?>
    <meta name="viewport" content="width=900, user-scalable=no">
    <meta property="og:image" content="<?= $host ?>imgs/hand.png"/>
    <meta property="og:url" content="<?= $host ?>"/>
    <meta property="og:title" content="TOTUM — платформа для любой автоматизации в малом бизнесе"/>
    <meta property="og:description"
          content="TOTUM — платформа для любой автоматизации в малом бизнесе. На ней можно собирать заточенные под клиента базы данных с готовым интерфейсом и настраиваемым доступом, узкоспециальные CRM, склады, расчеты и все, что придет в голову"/>
    <script>
        DATA = <?=empty($_REQUEST['sess_hash']) ? json_encode([
            'post' => $_POST,
            'get' => $_GET,
            'input' => file_get_contents('php://input')
        ],
            JSON_UNESCAPED_UNICODE) : '{}'?>
    </script>
    <style>
        <?=$css?>
    </style>

</head>
<body id="pk"
      class="lock">
<noscript>
    Для работы с системой необходимо включить JavaScript в настройках броузера
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<div class="page_content">
    <?php if (!empty($error)) {
        echo '<div class="panel panel-danger"><div class="panel-body">' . $error . '</div></div>';
    } else {
        echo '<div id="table"></div>';
    } ?>
</div>
</body>
</html>