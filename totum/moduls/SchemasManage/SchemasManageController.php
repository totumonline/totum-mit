<?php

namespace totum\moduls\SchemasManage;


use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\errorException;
use totum\common\jsonController;
use totum\common\Sql;
use totum\config\Conf;
use totum\fieldTypes\File;

class SchemasManageController extends jsonController
{
    protected function __runAction($action)
    {


        if (!defined(Conf::class . '::schemasPass') || ($_POST['pass'] ?? null) != Conf::schemasPass)
            throw new errorException('Ошибка доступа. Неверен пароль к схемам');

        if (empty(Conf::getSchema())) {
            Conf::setSchema('.');
        }

        parent::__runAction($action);
    }

    protected function getSchemas($schemaHosts)
    {
        $schemas = [];
        $qResRows = Sql::getAll('SELECT schema_name,
  (sum(table_size)::bigint) as "disk_space"

FROM (
       SELECT pg_catalog.pg_namespace.nspname as schema_name,
              pg_relation_size(pg_catalog.pg_class.oid) as table_size
       FROM   pg_catalog.pg_class
         JOIN pg_catalog.pg_namespace
           ON relnamespace = pg_catalog.pg_namespace.oid
      where pg_catalog.pg_namespace.nspname not in (\'information_schema\', \'pg_toast\', \'pg_catalog\')
     ) t
GROUP BY schema_name
ORDER BY "disk_space"');

        foreach ($qResRows as $row) {
            $host = ($schemaHosts[$row['schema_name']] ?? null);
            if ($dir = $host ? File::getDir($host) : null) {
                list($dir, $eee) = explode("\th", exec("du -s $dir"));
            }

            $schemas[$row['schema_name']] = ["disk" => $row['disk_space'], "host" => $host, "dir" => (int)$dir];
        }

        return $schemas;
    }

    protected function action_getSchemas()
    {
        $this->__addAnswerVar('schemas', static::getSchemas(array_flip(Conf::getSchemas())));
    }

    protected function action_setSchemaHost()
    {
        if (empty($schema = $_POST['schema'])) throw new errorException('Не указано имя схемы');
        $host = ($_POST['host'] ?? "");

        $hosts = Conf::getSchemas();
        $schemas = static::getSchemas(array_flip(Conf::getSchemas()));

        if (empty($schemas[$schema])) throw new errorException('Схема [[' . $schema . ']] не найдена');
        if (!empty($hosts[$host])) throw new errorException('Хост [[' . $host . ']] занят под схему [[' . $hosts[$host] . ']]. Выставьте хост схемы в пусто и попробуйте еще раз.');

        if (empty($host)) {
            if ($schemas[$schema]['host'] != "") {
                unset($hosts[$schemas[$schema]['host']]);
            }
        } else {
            if ($schemas[$schema]['host'] != $host) {
                unset($hosts[$schemas[$schema]['host']]);
            }
            $hosts[$host] = $schema;
        }

        file_put_contents(dirname((new \ReflectionClass(Conf::class))->getFileName()) . '/ConfSchemas.php',
            '<?php return ' . var_export($hosts, 1) . ';?>');

        sleep(2);

        $this->__addAnswerVar('schemas', static::getSchemas(array_flip($hosts)));
    }
    protected function action_uploadSchema()
    {
        if(empty(Conf::getDb()['pg_dump']) || empty(Conf::getDb()['psql'])) {
            echo json_encode(['error'=>'Настройте параметры pg_dump и psql в секции db файла Conf']);
            die;
        }
        $pathPsql = Conf::getDb()['psql'];
        $dbFrom = 'postgresql://' . Conf::getDb()['username'] . ':' . Conf::getDb()['password'] . '@' . Conf::getDb()['host'] . '/' . Conf::getDb()['dbname'];
        $tmpFileName = tempnam(Conf::getTmpLoadedFilesDir(), 'schema_');

        file_put_contents($tmpFileName, CalculateAction::cURL($_POST['file']));
        echo `mv $tmpFileName $tmpFileName.gz`;

        echo `gunzip $tmpFileName.gz`;

        file_put_contents($tmpFileName, preg_replace('/^(REVOKE|GRANT) ALL .*?;[\n\r]*$/m', '', file_get_contents($tmpFileName)));

        $tmpErrors = tempnam(Conf::getTmpLoadedFilesDir(), 'schema_errors_');
        `$pathPsql --dbname="$dbFrom" -q -1 -v ON_ERROR_STOP=1 -f $tmpFileName 2>$tmpErrors`;
        $data=file_get_contents($tmpErrors);
        if (strlen($data)>0){
            echo json_encode(['error'=>$data]);
            die;
        }
        unlink($tmpFileName);
        $this->__addAnswerVar('schemas', static::getSchemas(array_flip(Conf::getSchemas())));

    }
    protected function action_schemaDuplicate()
    {
        if (empty($schemaFrom = $_POST['duplicate'])) throw new errorException('Не указано имя схемы для дублирования');
        $schemas = static::getSchemas(array_flip(Conf::getSchemas()));
        if (!array_key_exists($schemaFrom, $schemas)) throw new errorException('Схема [[' . $schemaFrom . ']] не найдена');
        if (empty($schemaTo = $_POST['new_name'])) throw new errorException('Не указано имя новой схемы');

        if (array_key_exists($schemaTo, $schemas)) throw new errorException('Схема [[' . $schemaTo . ']] существует');


        if(empty(Conf::getDb()['pg_dump']) || empty(Conf::getDb()['psql'])) {
            echo json_encode(['error'=>'Настройте параметры pg_dump и psql в секции db файла Conf']);
            die;
        }


        $dbFrom = 'postgresql://' . Conf::getDb()['username'] . ':' . Conf::getDb()['password'] . '@' . Conf::getDb()['host'] . '/' . Conf::getDb()['dbname'];

        $tmpFileNameOld = tempnam(Conf::getTmpLoadedFilesDir(), 'schema_old_');
        $tmpFileName = tempnam(Conf::getTmpLoadedFilesDir(), 'schema_');
        $withLogData = '--exclude-table-data=\'*._log\' ';
        $pathDump = Conf::getDb()['pg_dump'];


        try{

            `$pathDump --dbname="$dbFrom" -O --schema $schemaFrom --no-tablespaces $withLogData > $tmpFileNameOld`;

        }catch (\Exception $e){
            throw new errorException($e->getMessage());
        }

        if (filesize($tmpFileNameOld) < 20) {
            throw new errorException('Схема не найдена');
        } else {
            $handle=fopen($tmpFileNameOld, 'r');
        }


        if (!($handleTmp = @fopen($tmpFileName, "a"))) {
            throw new errorException('Временный файл ' . $tmpFileName . ' не получается поздать');
        }


        $is_schema_replaced = false;


        while (($buffer = fgets($handle)) !== false) {
            if (!$is_schema_replaced && preg_match('/^CREATE SCHEMA/', $buffer)) {
                $tmpold = $schemaFrom . '_tmpold';
                $buffer = 'ALTER SCHEMA "' . $schemaFrom . '" RENAME TO "' . $tmpold . '";' . $buffer;
                $is_schema_replaced = true;
            }
            fputs($handleTmp, $buffer);
        }
        $buffer = 'update "' . $schemaFrom . '".settings set status=jsonb_build_object(\'v\', false);';
        $buffer .= 'ALTER SCHEMA "' . $schemaFrom . '" RENAME TO "' . $schemaTo . '";';
        $buffer .= 'ALTER SCHEMA "' . $tmpold . '" RENAME TO "' .$schemaFrom . '";';
        fputs($handleTmp, $buffer);

        if (!feof($handle)) {
            throw new errorException("Error: unexpected fgets() fail");
        }

        fclose($handle);
        fclose($handleTmp);
        $pathPsql = Conf::getDb()['psql'];

        echo `$pathPsql --dbname="$dbFrom" -1 -v ON_ERROR_STOP=1 -f $tmpFileName | grep ERROR`;
        unlink($tmpFileName);
        unlink($tmpFileNameOld);
        $this->__addAnswerVar('schemas', static::getSchemas(array_flip(Conf::getSchemas())));
    }

    protected function action_schemaDump()
    {
        if (empty($schemaFrom = $_POST['schema'])) throw new errorException('Не указано имя схемы для выгрузки');
        $schemas = static::getSchemas(array_flip(Conf::getSchemas()));
        if (!array_key_exists($schemaFrom, $schemas)) throw new errorException('Схема [[' . $schemaFrom . ']] не найдена');

        if(empty(Conf::getDb()['pg_dump']) || empty(Conf::getDb()['psql'])) {
            echo json_encode(['error'=>'Настройте параметры pg_dump и psql в секции db файла Conf']);
            die;
        }


        $dbFrom = 'postgresql://' . Conf::getDb()['username'] . ':' . Conf::getDb()['password'] . '@' . Conf::getDb()['host'] . '/' . Conf::getDb()['dbname'];

        $withLogData = '--exclude-table-data=\'*._log\' --exclude-table-data=\'_tmp_tables\' --exclude-table-data=\'*._bfl\'';
        $pathDump = Conf::getDb()['pg_dump'];
        $tmpFileName = tempnam(Conf::getTmpLoadedFilesDir(), 'schema_');
        `$pathDump --dbname="$dbFrom" -O --schema $schemaFrom --no-tablespaces $withLogData | grep -v '^--' > $tmpFileName`;
        `gzip $tmpFileName`;
        header('Content-type: application/gzip');
        echo file_get_contents($tmpFileName.'.gz');
        /*echo file_get_contents($tmpFileName);
        unlink($tmpFileName);*/
        die;
    }

}