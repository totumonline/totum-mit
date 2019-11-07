<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 04.07.2018
 * Time: 11:37
 */

namespace totum\common;


abstract class ConfParent
{
    protected static $tmpLoadedFilesDirPath = 'totumTmpfiles/tmpLoadedFiles/';
    protected static $tmpTableChangesDirPath = 'totumTmpfiles/tmpTableChangesDirPath/';

    static $MaxFileSizeMb = 10;
    protected static $schemas;

    static function getLogPaths()
    {
        $dir= getcwd().'/myLogs/';
        if(!is_dir($dir)) mkdir($dir);
        return [
            'sql' => $dir.'sql_' . static::getDb()['schema'] . '.log',
            'error' => $dir.'error.log',
            'calcs' => $dir.'calcs.log'
        ];
    }



    static function getTmpLoadedFilesDir()
    {
        if (!is_dir(static::$tmpLoadedFilesDirPath)) {
            mkdir(static::$tmpLoadedFilesDirPath, 0777, true);
        }
        return static::$tmpLoadedFilesDirPath;
    }

    static function getTmpTableChangesDir()
    {
        if (!is_dir(static::$tmpTableChangesDirPath)) {
            mkdir(static::$tmpTableChangesDirPath, 0777, true);
        }
        return static::$tmpTableChangesDirPath;
    }

    protected static $settedSchema;
    
    static function setSchema($schemaName)
    {
        static::$settedSchema = $schemaName;
    }

    static function getSchema()
    {
        return static::$settedSchema ?? static::$schemas[$_SERVER['HTTP_HOST'] ?? ''] ?? '';
    }

    static function getSchemas()
    {
        return static::$schemas;
    }

    static function getDb()
    {
        $db = static::db;
        $db['schema'] = static::getSchema();
        return $db;
    }

    public static function getFullHostName()
    {
        return ($_SERVER['HTTP_HOST'] ?? array_flip(static::$schemas)[static::getSchema()]);
    }

    /*
     * Возвращает часть имени хоста для образования имени папки с файлами
     * Если изменять - нужно изменить htaccess в папке http/fls
    */
    public static function getHostForDir($host)
    {

        return preg_replace('`^(www.)?(.+)$`',
            '$2',
            $host);
    }

    public static function mail($to, $title, $body, $attachments = [], $from = null, $system = false)
    {
        throw new errorException('Настройки для отправки почты не заданы');
    }
}