<?php if (!\totum\common\Auth::isAuthorized()) {
    return;
} ?>
<div id="TOTUM_FOOTER">
            Время обработки страницы: <?=round(microtime(true)-$GLOBALS['mktimeStart'], 4)?> сек.<br/>
            Оперативная память: <?php $Mb = memory_get_peak_usage()/1024/1024; if ($Mb<1) echo '< 1 '; else echo round ($Mb, 2);?> M.  из <?=ini_get('memory_limit')?>.<br/>
            Sql схема: <?=\totum\config\Conf::getDb()['schema'] ?>, V <?=\totum\common\Totum::Version?><br/>
        </div>

