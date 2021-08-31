<?php

namespace totum\common\Lang;

class ENG implements LangInterface
{

    public function translate(string $str, array|string $vars = []): string
    {
        if (!is_array($vars)) {
            $vars = [$vars];
        }
        if (empty($vars)) return $str;
        return sprintf($str, ...$vars);
    }
}