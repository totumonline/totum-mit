<?php

use totum\config\Conf;


require_once '../totum/AutoloadAndErrorHandlers.php';
require_once 'Conf.php';
$ConfDb = Conf::db;

$dir = '../totumTmpfiles/backups/';
if (!is_dir($dir)) mkdir($dir, 0777, true);
`rm $dir*.*`;

$schemas = array_unique(Conf::getSchemas());
if ($schemas) {
    $month = date('y-m');

    /*
     * Создание папки WEBDAV
     * exec("curl --user " . Conf::backup_loginparol . " '" . Conf::backup_server . $month . "' -sw '%{http_code}'",
        $data);
    if ($data[0] != 415) {
        exec("curl --user " . Conf::backup_loginparol . " -X MKCOL " . Conf::backup_server . $month, $data);
    }*/
    foreach ($schemas as $schema) {

        $fname = date('Y-m-d H') . '_' . $schema . '.sql';
        $filename = $dir . $fname;
        if (is_file($filename)) unlink($filename);
        exec("pg_dump " .
            "--dbname=postgresql://{$ConfDb['username']}:{$ConfDb['password']}@{$ConfDb['host']}/{$ConfDb['username']}" .
            " -O --schema {$schema} --exclude-table-data='*._bfl' --exclude-table-data='_tmp_tables' | grep -v '^--' > \"{$filename}\"",
            $data);

        $data = [];
        if (is_file($filename)) {
            if (filesize($filename) > 0) {
                exec("gzip '$filename'");
                $filename .= '.gz';


                /*
                 * ПАПКА WEBDAV
                 * exec("curl --user " . Conf::backup_loginparol . " '" . Conf::backup_server . $month . '/' . $schema . "' -sw '%{http_code}'",
                    $data);
                if ($data[0] != 415) {
                    exec("curl --user " . Conf::backup_loginparol . " -X MKCOL " . Conf::backup_server . $month . '/' . $schema,
                        $data);
                }*/
                $data = [];
                exec("curl -u " . escapeshellarg(Conf::backup_loginparol) . " --ftp-create-dirs -T \"{$filename}\" " . escapeshellarg(Conf::backup_server . $month . '/' . $schema . '/'),
                    $data);
            }

            unlink($filename);
        }

        /*Файлы бекапим только в полночь*/
        if (date('H') == '00') {
            foreach (Conf::getSchemas() as $host => $sch) {
                if ($sch == $schema) {
                    $host = preg_replace('/^([^\.]+)\..*$/', '$1', $host);
                    if (is_dir($_dir = 'http/fls/' . $host) && count(scandir($_dir)) > 2) {
                        $dirfilename = $dir . date('Y-m-d_H') . '_' . $host . '.tar.gz';
                        `tar --exclude='*!tmp!*' -cvzf $dirfilename $_dir`;
                        /*
                         * Добавление файла WEBDAV
                         * exec("curl --user " . escapeshellarg(Conf::backup_loginparol) . " -T \"{$dirfilename}\" " . escapeshellarg(Conf::backup_server . $month . '/' . $schema . '/'),
                            $data);*/

                        exec("curl -u " . escapeshellarg(Conf::backup_loginparol) . " --ftp-create-dirs -T \"{$dirfilename}\" " . escapeshellarg(Conf::backup_server . $month . '/' . $schema . '/'));

                        unlink($dirfilename);

                    }
                }
            }
        }
    }
}
?>