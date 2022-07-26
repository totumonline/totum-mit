<!DOCTYPE html>
<head lang="ru">
    <script>App = {}</script>
    <link rel="shortcut icon" type="image/png" href="/fls/6_favicon.png"/>
    <title><?= !empty($title) ? $title . ' — ' : '' ?>Totum</title>
    <?php
    $host = 'http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/';
    ?>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible"
          content="ie=edge">
    <meta property="og:image" content="<?= $host ?>imgs/hand.png"/>
    <meta property="og:url" content="<?= $host ?>"/>
    <meta property="og:title" content=""/>
    <meta property="og:description" content=""/>

    <style>
        body{
            background: url(/imgs/mailttm.png?1) no-repeat center center fixed;
            background-size: cover;
        }
        #form.ttm-form {
            width: 100%;
            min-height: 200px;
            max-width: 620px;
            margin: auto;
            padding-top: 20px;
            padding-bottom: 20px;
            border-radius: 10px;
            margin-top: 20px;
            box-shadow: 0 5px 10px rgb(0, 0, 0 , 20%);
            backdrop-filter: blur(10px);
            background: rgb(255 255 255 / 90%);
        }
    </style>

    <meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1">

</head>
<body id="pk"
      class="lock">
<noscript>
    <?=$this->translate('To work with the system you need to enable JavaScript in your browser settings')?>
</noscript>
<div id="big_loading" style="display: none;"><i class="fa fa-cog fa-spin fa-3x"></i></div>
<div id="form">
    <script>
        window.MAIN_HOST_FORM = true;
        let num = "85";
        (function (src, cssSrc, address, post, get, input) {
            let div = document.currentScript.parentNode;
            let path = "<?=$path?>";

            let newScript = document.createElement("script");
            newScript.charset = "utf-8";

            newScript.onerror = (event) => {
                console.log(event)
                div.innerHTML = '<div><?=$this->translate('It did not load :(')?>></div>';
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
        })("/js/forms.js?" + num, "/css/forms.css?" + num, "test-form", [], Object.fromEntries(new URLSearchParams(window.location.search)), "input_text");
    </script>
</div>
</body>
</html>