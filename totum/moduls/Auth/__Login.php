<?php

use totum\config\Conf;

?>


<div id="auth_form"
     class="center-block"
     style="width: 350px; margin-top: 10vh; display: none;">
    <style>
        #top_line, .Tree {
            display: none;
        }

        .page_content {
            margin-left: 0px;
        }

        input.error {
            border-color: red;
        }
    </style>

    <div style="text-align: center; font-size: 30px; padding-bottom: 2vh;padding-top: 2vh;"
         class="login-brand"><?= $schema_name ?> </div>
    <div class="center-block"
         style="width: 300px; ">
        <form method="post"
              id='form'>
            <div class="form-group"><label>Логин/Email:</label><input type="text"
                                                                      name="login"
                                                                      value=""
                                                                      class="form-control"
                /></div>
            <div class="form-group"><label>Пароль:</label><input type="password"
                                                                 name="pass"
                                                                 class="form-control"/></div>
            <div class="form-group"><input type="submit"
                                           value="Вход"
                                           style="width: 79px;margin-top:4px;"
                                           id="login"
                                           class="form-control"/>

            </div>
        </form>
        <?php
        if ($with_pass_recover) { ?>
            <button
                    style=";margin-top:4px;"
                    id="recover"
                    class="form-control btn btn-default">Отправить новый пароль на email
            </button>
        <?php
        } ?>
    </div>
</div>

<script>
    $(function () {
        try {

            let i = 1;

            try {
                LOGINJS();

            } catch (e) {

                $('body').html('<div style="width: 600px; margin: auto; padding-top: 50px; font-size: 16px; text-align: center;" id="comeinBlock">' +
                    '<img src="/imgs/start.png" alt="">' +
                    '<div style="padding-bottom: 10px;">Сервис оптимизирован под десктопные броузеры Chrome, Safari, Yandex последних версий. Похоже, ваша версия броузера не поддерживается. Ошибка - для разработчиков: ' + e.stack + '</div>' +
                    '</div>');
            }

        } catch (e) {
            $('body').html('<div style="width: 600px; margin: auto; padding-top: 50px; font-size: 16px; text-align: center;" id="comeinBlock">' +
                '<img src="/imgs/start.png" alt="">' +
                '<div style="padding-bottom: 10px;">Сервис оптимизирован под десктопные броузеры Chrome, Safari, Yandex последних версий. Похоже, ваша версия броузера не поддерживается</div>' +
                '</div>');
        }


    })
</script>