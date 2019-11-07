<?php
chdir('../totumTmpfiles');

foreach (['./tmpLoadedFiles', './backups'] as $dir) {
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (is_file($fName = $dir . '/' . $file) && fileatime($fName) < time() - 360 * 20) {
                    unlink($fName);
                }
            }
            closedir($dh);
        }
    }
}