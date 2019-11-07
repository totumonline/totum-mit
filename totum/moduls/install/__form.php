<style>
    h3{
        padding-top: 20px;
    }
    small{
        font-size: 12px;
    }
</style>
<form method="post" action="/" style="width: 500px; margin: auto">
    <div style="padding-bottom: 40px; padding-top: 10px; font-size: 55px;"><img src="/fls/hand.png" style="width: 80px; vertical-align: bottom"> TOTUM</div>
    <div style="padding-bottom: 20px; font-size: 20px;"><a href="https://docs.totum.online/obnovleniya#part-2" target="_blank">Документация по установке</a></div>
    <h3 class="fieldset">Пользователь базы данных PostgreSQL</h3>
    <div class="form-group"><label>Логин</label><input type="text" name="db_user_login" class="form-control" value="<?=$_POST['db_user_login']??""?>" required></div>
    <div class="form-group"><label>Пароль</label><input type="text" name="db_user_password" class="form-control"  value="<?=$_POST['db_user_password']??""?>" required></div>
    <h3>База данных PostgreSQL</h3>
    <div class="form-group"><label>host базы</label><input type="text" name="db_host" class="form-control"  value="<?=$_POST['db_host']??""?>" required></div>
    <div class="form-group"><label>Имя базы</label><input type="text" name="db_name" class="form-control"  value="<?=$_POST['db_name']??""?>" required></div>
    <div class="form-group"><label>Схема</label><input type="text" name="db_schema" class="form-control"  value="<?=$_POST['db_schema']??""?>" required></div>
    <h3>Путь к утилитам Postgres на сервере</h3>
    <div class="form-group"><label>pg_dump</label><input type="text" name="pg_dump" class="form-control"  value="<?=$_POST['pg_dump']??"pg_dump"?>" required>
    </div>

    <div class="form-group"><label>psql</label><input type="text" name="psql" class="form-control"  value="<?=$_POST['psql']??"psql"?>" required>
    </div>


    <h3>Создать пользователя с полным доступом</h3>
    <div class="form-group"><label>Логин</label><input type="text" name="user_login" class="form-control"  value="<?=$_POST['user_login']??"admin"?>" required></div>
    <div class="form-group"><label>Пароль</label><input type="text" name="user_pass" class="form-control"  value="<?=$_POST['user_pass']??""?>" required></div>


    <div><input type="submit" value="Создать конфиг и залить схему" style="color: #fff; margin-top: 20px;"  class="btn btn-danger"/></div>
</form>
<script>
    $('.page_content:first').addClass('tree-minifyed')
</script>