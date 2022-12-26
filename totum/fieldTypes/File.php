<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.02.18
 * Time: 13:51
 */

namespace totum\fieldTypes;

use totum\common\Auth;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\sql\Sql;
use totum\config\Conf;

class File extends Field
{
    protected static $transactionCommits = [];

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        switch ($viewType) {
            case 'web':
                if (!empty($valArray['e'])) {
                    $valArray['v'] = [];
                }
                break;
            case 'csv':
                throw new errorException($this->translate('Export via csv is not available for [[%s]] field.', 'file'));
            case 'print':
                $func = function ($array) use (&$func) {
                    if (!$array) {
                        return '';
                    }
                    $v = $array[0];
                    return '<div><span>' . htmlspecialchars($v['name']) . '</span><span>' . number_format(
                            $v['size'] / 1024,
                            0,
                            ',',
                            ' '
                        ) . 'Kb</span></div>' . $func(array_slice($array, 1));
                };
                $valArray['v'] = $func($valArray['v']);
                break;
        }
    }

    public function getValueFromCsv($val)
    {
        throw new errorException($this->translate('Import from csv is not available for [[%s]] field.', 'file'));

        //return $val = json_decode(base64_decode($val), true);
    }


    public static function deleteFilesOnCommit($deleteFiles, Conf $Config)
    {
        if ($deleteFiles) {
            $Config->getSql()->addOnCommit(function () use ($deleteFiles, $Config) {
                foreach ($deleteFiles as $file) {
                    if ($file = ($file['file'] ?? null)) {
                        static::deleteFile(static::getFilePath($file, $Config));
                    }
                }
            });
        }
    }

    protected static function deleteFile($fullFileName)
    {
        if (is_file($fullFileName)) {
            unlink($fullFileName);
        }
        if (is_file($preview = $fullFileName . '_thumb.jpg')) {
            unlink($preview);
        }
    }

    public static function getFilePath($file_name, Conf $Config): string
    {
        return $Config->getFilesDir() . $file_name;
    }

    public function addXmlExport(\SimpleXMLElement $simpleXMLElement, $fVar)
    {
        $paramInXml = $simpleXMLElement->addChild($this->data['name']);
        foreach ($fVar['v'] ?? [] as $file) {
            $value = $paramInXml->addChild('value', $file['file']);
            $value->addAttribute('title', $file['name']);
            $value->addAttribute('size', $file['size']);
            $value->addAttribute('ext', $file['ext']);
        }
    }

    public static function isImage($name): bool|string
    {
        if (in_array(
            $ext = preg_replace('/^.*\.([a-z0-9]{2,5})$/', '$1', strtolower($name)),
            ['jpg', 'jpeg', 'png']
        )) {
            return $ext;
        }
        return false;
    }

    protected static function getThumb($tmpFileName, $ext): \GdImage|bool
    {
        if ($ext === 'png') {
            $source = imagecreatefrompng($tmpFileName);
        } else {
            $source = imagecreatefromjpeg($tmpFileName);
        }
        // получение нового размера
        list($width, $height) = getimagesize($tmpFileName);

        $newwidth = 290;
        $newheight = $height * $newwidth / $width;


        $thumb = imagecreatetruecolor($newwidth, $newheight);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));

        if ($newwidth > $width && $newheight > $height) {
            if ($height < 100) {
                $newheight = 100;
            } else {
                $newheight = $height + 10;
            }

            $thumb = imagecreatetruecolor($newwidth, $newheight);
            imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));

            imagecopyresampled(
                $thumb,
                $source,
                round(($newwidth - $width) / 2),
                round(($newheight - $height) / 2),
                0,
                0,
                $width,
                $height,
                $width,
                $height
            );
        } else {
            imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
        }
        return $thumb;
    }

    protected static function checkAndCreateThumb($tmpFileName, $name)
    {
        if ($ext = static::isImage($name)) {
            $thumbName = static::getTmpThumbName($tmpFileName);
            $thumb = static::getThumb($tmpFileName, $ext);
            imagejpeg($thumb, $thumbName, 100);
        }
    }

    public static function getTmpThumbName($tmpFileName)
    {
        return $tmpFileName . '_thumb.jpg';
    }

    /*TODO переделать на использование входящего контекста*/
    public static function fileUpload($userId, Conf $Config)
    {
        $tmpFileName = tempnam($Config->getTmpDir(), $Config->getSchema() . '.' . $userId . '.');
        if ($_FILES['file']) {
            if (filesize($_FILES['file']['tmp_name']) > Conf::$MaxFileSizeMb * 1024 * 1024) {
                return ['error' => $Config->getLangObj()->translate('File > ') . Conf::$MaxFileSizeMb . ' Mb'];
            }

            if (copy($_FILES['file']['tmp_name'], $tmpFileName)) {
                static::checkAndCreateThumb($tmpFileName, $_FILES['file']['name']);
                return ['fname' => preg_replace('`^.*/([^/]+)$`', '$1', $tmpFileName)];
            }
        }
        return ['error' => $Config->getLangObj()->translate('File not received. May be too big.')];
    }

    public function getLogValue($val, $row, $tbl = [])
    {
        $files = '';
        foreach ($val ?? [] as $file) {
            if ($files) {
                $files .= ', ';
            }
            $fsize = number_format($file['size'] / 1024, 0, ',', ' ');
            $files .= $file['name'] . " ($fsize Kb)";
        }
        return $files;
    }

    protected function _getFprefix($rowId = null): string
    {
        return $this->table->getTableRow()['id'] . '_' //Таблица
            . ($this->table->getTableRow()['type'] === 'calcs' ? $this->table->getCycle()->getId() . '_' : '') //цикл
            . ($rowId ? $rowId . '_' : '') //Строка
            . ($this->data['name']) //Поле
            . ($this->table->getTableRow()['type'] === 'tmp' ? '!tmp!' : '');
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (is_object($modifyVal) && empty($this->data['multiple'])) {
            throw new errorException($this->translate('Operation [[%s]] over not mupliple select is not supported.',
                $modifyVal->sign));
        }


        if (!$isCheck) {
            $deletedFiles = [];
            if (is_object($modifyVal)) {
                if (empty($oldVal) || !is_array($oldVal)) {
                    $oldVal = array();
                }
                $modifyVal = match ($modifyVal->sign) {
                    '+' => array_merge($oldVal, [$modifyVal->val]),
                    default => throw new errorException($this->translate('Operation [[%s]] over files is not supported.',
                        $modifyVal->sign)),
                };
            } elseif (!empty($oldVal) && is_array($oldVal)) {
                foreach ($oldVal as $fOld) {
                    foreach ($modifyVal as $file) {
                        if (is_array($fOld) && $fOld['file'] === ($file['file'] ?? null)) {
                            continue 2;
                        }
                    }
                    if (is_array($fOld) && str_starts_with($fOld['file'] ?? '',
                            $this->_getFprefix($row['id'] ?? null))) {
                        $deletedFiles[] = $fOld;
                    }
                }
            }


            if ($deletedFiles) {
                static::deleteFilesOnCommit($deletedFiles, $this->table->getTotum()->getConfig());
            }
        }
        return $modifyVal;
    }


    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (is_null($val) || $val === '' || $val === []) {
            return [];
        }

        if (!is_array($val)) {
            throw new criticalErrorException($this->translate('The data format is not correct for the File field.'));
        }


        /*Добавление через filestring и filestringbase64 */
        foreach ($val as &$file) {
            if (!empty($file['filestring'])) {
                $ftmpname = tempnam(
                    $this->table->getTotum()->getConfig()->getTmpDir(),
                    $this->table->getTotum()->getConfig()->getSchema() . '.' . $this->table->getUser()->getId() . '.'
                );
                file_put_contents($ftmpname, $file['filestring']);

                if (!empty($file['gz'])) {
                    `gzip $ftmpname`;
                    $ftmpname .= '.gz';
                    unset($file['gz']);
                    $file['name'] .= '.gz';
                }
                $file['size'] = filesize($ftmpname);

                unset($file['filestring']);
                static::checkAndCreateThumb($ftmpname, $file['name']);
                $file['tmpfile'] = preg_replace('`^.*/([^/]+)$`', '$1', $ftmpname);
            } elseif (!empty($file['filestringbase64'])) {
                $ftmpname = tempnam(
                    $this->table->getTotum()->getConfig()->getTmpDir(),
                    $this->table->getTotum()->getConfig()->getSchema() . '.' . $this->table->getUser()->getId() . '.'
                );

                file_put_contents($ftmpname, base64_decode($file['filestringbase64']));

                if (!empty($file['gz'])) {
                    `gzip $ftmpname`;
                    $ftmpname .= '.gz';
                    unset($file['gz']);
                    $file['name'] .= '.gz';
                }
                $file['size'] = filesize($ftmpname);

                static::checkAndCreateThumb($ftmpname, $file['name']);
                unset($file['filestringbase64']);
                $file['tmpfile'] = preg_replace('`^.*/([^/]+)$`', '$1', $ftmpname);
            }
        }
        unset($file);
        /*----------------*/

        if (!$isCheck && ($this->data['category'] !== 'column' || $row['id'] ?? null)) {
            $fPrefix = $this->_getFprefix($row['id'] ?? null);

            $funcGetFname = function ($ext) use ($fPrefix) {
                $fnum = 0;

                do {
                    $unlinked = false;

                    $fname = static::getFilePath(
                        $fPrefix
                        . ($fnum ? '_' . $fnum : '') //Номер
                        . (!empty($this->data['nameWithHash']) ? '_' . md5(microtime(1) . $this->data['name']) : '') //хэш
                        . '.' . $ext,
                        $this->table->getTotum()->getConfig()
                    );

                    if (!$this->data['multiple'] && $this->table->getTableRow()['type'] !== 'tmp') {
                        break;
                    }

                    ++$fnum;

                    if (is_file($fname) &&
                        (
                            (filesize($fname) === 0 && filemtime($fname) < time() - 10 * 60)
                            || ($this->table->getTableRow()['type'] === 'tmp' && filemtime($fname) < time() - 24 * 60 * 60)
                        )

                    ) {
                        if (unlink($fname)) {
                            $fnum--;
                        }
                        $unlinked = true;
                    }
                } while ($unlinked || (!@fopen($fname, 'x') && $fnum < 1030));


                if ($fnum === 1030) {
                    die($this->translate('File name search error.'));
                }
                return $fname;
            };

            $vals = [];
            foreach ($val as $file) {
                $fl = [];
                if (!is_array($file) || !array_key_exists('name', $file)) {
                    throw new criticalErrorException($this->translate('The data format is not correct for the File field.'));
                }

                $file['ext'] = preg_replace('/^.*\.([a-z0-9]{2,4})$/', '$1', strtolower($file['name']));

                if (empty($file['ext'])) {
                    throw new criticalErrorException($this->translate('The file must have an extension.'));
                }
                if (in_array(
                    $file['ext'],
                    ['php', 'phtml']
                )) {
                    throw new criticalErrorException($this->translate('Restricted to add executable files to the server.'));
                }

                if ($file['ext'] === 'jpeg') {
                    $file['ext'] = 'jpg';
                }


                if (!empty($file['tmpfile'])) {
                    if (!is_file($ftmpname = $this->table->getTotum()->getConfig()->getTmpDir() . $file['tmpfile'])) {
                        die('{"error":"Временный файл не найден"}');
                    }
                    $fname = $funcGetFname($file['ext']);

                    static::$transactionCommits[$fname] = $ftmpname;

                    $this->table->getTotum()->getConfig()->getSql()->addOnCommit(function () use ($ftmpname, $fname) {
                        if (!copy($ftmpname, $fname)) {
                            die(json_encode(['error' => $this->translate('Failed to copy a temporary file.')]));
                        }
                        if (is_file($ftmpname . '_thumb.jpg')) {
                            if (!copy($ftmpname . '_thumb.jpg', $fname . '_thumb.jpg')) {
                                die(json_encode(['error' => $this->translate('Failed to copy preview.')],
                                    JSON_UNESCAPED_UNICODE));
                            }
                        }
                        unset(static::$transactionCommits[$fname]);
                    });

                    $fl['size'] = filesize($ftmpname);
                    $fl['ext'] = $file['ext'];
                    $fl['file'] = preg_replace('/^.*\/([^\/]+)$/', '$1', $fname);
                } elseif (!empty($file['file'])) {
                    $filepath = static::getFilePath($file['file'], $this->table->getTotum()->getConfig());
                    $fl['file'] = $file['file'];

                    if (key_exists($filepath, static::$transactionCommits)) ; elseif (!is_file($filepath)) {
                        if ($isCheck) {
                            throw new errorException($this->translate('Field [[%s]] is not found.', $file['name']));
                        }
                        $file['size'] = 0;
                        $fl['e'] = 'Файл не найден';
                    } else {
                        if (!str_starts_with($file['file'], $fPrefix) && !empty($this->data['fileDuplicateOnCopy'])) {
                            $fname = $funcGetFname($file['ext']);

                            $otherfname = static::getFilePath($file['file'], $this->table->getTotum()->getConfig());

                            static::$transactionCommits[$fname] = $otherfname;

                            $this->table->getTotum()->getConfig()->getSql()->addOnCommit(function () use ($otherfname, $fname) {
                                if (!copy($otherfname, $fname)) {
                                    die(json_encode(['error' => $this->translate('Error copying a file to the storage folder.')],
                                        JSON_UNESCAPED_UNICODE));
                                }
                                if (is_file($otherfname . '_thumb.jpg')) {
                                    copy($otherfname . '_thumb.jpg', $fname . '_thumb.jpg');
                                }
                                unset(static::$transactionCommits[$fname]);
                            });
                            $fl['file'] = preg_replace('/^.*\/([^\/]+)$/', '$1', $fname);
                        }
                        if (!$file['size']) {
                            $file['size'] = filesize($filepath);
                        }
                    }

                    $fl['size'] = $file['size'];
                    $fl['ext'] = $file['ext'];
                }

                $fl['name'] = $file['name'];
                $vals[] = $fl;
            }
            $val = $vals;
        }
    }

    public static function replaceImageSrcsWithEmbedded(Conf $Config, string $html): string
    {
        return preg_replace_callback(
            '~src\s*=\s*([\'"]?)(?:http(?:s?)://' . $Config->getFullHostName() . ')?/fls/(.*?)\1~',
            function ($matches) use ($Config, &$attachments) {
                if (!empty($matches[2])) {
                    if ($file = File::getContent($matches[2],
                        $Config)) {
                        return 'src="data:image/' . preg_replace('/^.*?\.([^.]+)$/',
                                '$1',
                                $matches[2]) . ';base64,' . base64_encode($file) . '"';
                    }
                }
                return null;
            },
            $html
        );
    }

    public static function getContent($fname, Conf $Config): bool|string|null
    {
        $filepath = static::getFilePath($fname, $Config);
        if (key_exists($filepath, static::$transactionCommits)) {
            $filepath = static::$transactionCommits[$filepath];
        }
        if (!is_file($filepath)) {
            return null;
        }
        return file_get_contents($filepath);
    }
}
