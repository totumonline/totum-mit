<?php

namespace totum\common\Lang;

use DateTime;

interface LangInterface
{
    public function translate(string $str, array|string $vars = []): string;

    public function dateFormat(DateTime $date, $fStr): string;

    public function num2str($num): string;

    public function smallTranslit($s): string;
}