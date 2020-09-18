<div class="page_content"><style>
    h3{
        padding-top: 20px;
    }
    small{
        font-size: 12px;
    }
    .panel-body{
        white-space: pre-line;
    }
    #form[data-consol="1"] #consol-scripts{
        display: none;
    }
</style>
<form method="post" id="form" action="/" style="width: 500px; margin: auto; padding-bottom: 100px" data-consol="0">
    <div style="padding-bottom: 40px; padding-top: 10px; font-size: 55px;"><img src="/imgs/365_100_file.png"></div>

    <div class="form-group">
        <select id="multy" name="multy" class="form-control"><option value="0" <?=($_POST['multy']??'0')==='0'?'selected':''?>>Одинарная установка</option><option value="1" <?=($_POST['multy']??'0')==='1'?'selected':''?>>Множественная установка</option></select>
    </div>
    <div class="form-group">
        <label>Схема</label><input type="text" name="db_schema" class="form-control"  value="<?=$_POST['db_schema']??"public"?>" required>
        <br>
        <select name="schema_exists" class="form-control"><option value="0" <?=($_POST['schema_exists']??'1')==='0'?'selected':''?>>Разворачивать только в новой</option><option value="1" <?=($_POST['schema_exists']??'1')==='1'?'selected':''?>>Использовать существующую</option></select>
    </div>

    <h3>База данных PostgreSQL</h3>
    <div class="form-group"><label>Строка установки</label>
        <input type="text" class="form-control" id="dbstring">
        <div class="error" id="dbstring_error"></div>
    </div>


    <div class="form-group"><label>host базы</label><input type="text" name="db_host" class="form-control"  value="<?=$_POST['db_host']??"localhost"?>" required></div>
    <div class="form-group"><label>Имя базы</label><input type="text" name="db_name" class="form-control"  value="<?=$_POST['db_name']??""?>" required></div>


    <div class="form-group"><label>Логин</label><input type="text" name="db_user_login" class="form-control" value="<?=$_POST['db_user_login']??""?>" required></div>
    <div class="form-group"><label>Пароль</label><input type="text" name="db_user_password" class="form-control"  value="<?=$_POST['db_user_password']??""?>" required></div>

    <div class="form-group">
        <label>Консольные утилиты PostgreSql</label>
        <select id="consol" name="consol" class="form-control"><option value="0" <?=($_POST['consol']??'0')==='0'?'selected':''?>>С консольными утилитами</option><option value="1" <?=($_POST['consol']??'0')==='1'?'selected':''?>>Без консольных утилит</option></select>
    </div>
    <div id="consol-scripts">
    <div class="form-group"><label>pg_dump</label><input type="text" name="pg_dump" class="form-control"  value="<?=$_POST['pg_dump']??"pg_dump"?>" >
    </div>

    <div class="form-group"><label>psql</label><input type="text" name="psql" class="form-control"  value="<?=$_POST['psql']??"psql"?>" >
    </div>
    </div>


    <h3>Создать пользователя с полным доступом</h3>
    <div class="form-group"><label>Логин</label><input type="text" name="user_login" class="form-control"  value="<?=$_POST['user_login']??"admin"?>" required></div>
    <div class="form-group"><label>Пароль</label><input type="text" name="user_pass" class="form-control"  value="<?=$_POST['user_pass']??""?>" required></div>
    <div class="form-group"><label>Email для нотификаций крона</label><input type="text" name="admin_email" class="form-control"  value="<?=$_POST['admin_email']??""?>"></div>

    <div><input type="submit" value="Создать конфиг и залить схему" style="color: #fff; margin-top: 20px;"  class="btn btn-danger" id="submit" /></div>
</form>
<script>
    $('.page_content:first').addClass('tree-minifyed')

    $('#multy').on('change', function (){
        if($(this).val()==='0'){
            $('input[name="db_schema"]').val("public");
            $('select[name="schema_exists"]').val("1");
        }else{
            $('input[name="db_schema"]').val("new_totum");
            $('select[name="schema_exists"]').val("0");
        }
    })
    $('#dbstring').on('change', function (){
       let val=$(this).val();
        $('#dbstring_error').text('');
        let matches
       if(matches=val.match(/^postgresql:\/\/(?<USER>[^:]+):(?<PASS>[^@]+)@(?<HOST>[^\/]+)\/(?<DBNAME>.+)$/)){

           $('input[name="db_host').val(matches.groups.HOST);
           $('input[name="db_name').val(matches.groups.DBNAME);
           $('input[name="db_user_login').val(matches.groups.USER);
           $('input[name="db_user_password').val(matches.groups.PASS);
           $(this).val('');
       }else{
           $('#dbstring_error').text('Некорректная строка');
       }
       
    });
    $('#consol').on('change', function (){
        let val=$(this).val();
        $('#form').attr('data-consol', val)
        if (val==='1'){
            $('input[name="pg_dump').val('');
            $('input[name="psql').val('');
        }
    })
</script></div>