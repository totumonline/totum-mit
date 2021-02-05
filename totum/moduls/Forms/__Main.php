<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
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
</head>
<body id="pk"
      class="lock">
<noscript>
    Для работы с системой необходимо включить JavaScript в настройках броузера
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<div class="page_content">
    <script>
        let num = "78";
        (function (src, cssSrc, address, post, get, input) {
            let div = document.currentScript.parentNode;
            let path = "<?=$path?>";

            let newScript = document.createElement("script");
            newScript.charset = "utf-8";

            newScript.onerror = (event) => {
                console.log(event)
                div.innerHTML = '<div>Не загрузилось :(</div>';
            };
            newScript.onload = (event) => {
                address = src.match(/^(.*?\/)[^\/]*$/)[1] + address;
                address = '/Forms/' + path;

                ttmForm(div, address, true, post, get, input);
            };
            div.insertBefore(newScript, document.currentScript);

            newScript.src = src;
            if (!window.ttmCss) {
                window.ttmCss = true;
                let styles = document.createElement("link");
                styles.rel = "stylesheet";
                styles.type = "text/css";
                styles.href = cssSrc;
                document.head.append(styles);
            }
        })("/js/forms.js?" + num, "/css/forms.css?" + num, "test-form", [], window.location.search, "input_text");
    </script>
</div>
</body>
</html>