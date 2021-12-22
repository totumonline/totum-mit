<?php

namespace totum\common\Lang;

trait TranslateTrait
{

    public function translate(string $str, mixed $vars = []): string
    {
        if (!is_array($vars)) {
            $vars = [$vars];
        }

        foreach ($vars as &$var) {
            if (is_array($var)) {
                $var = json_encode($var, JSON_UNESCAPED_UNICODE);
            } elseif ($var === null) {
                $var = 'null';
            } elseif ($var === '') {
                $var = '""';
            }
        }
        unset($var);

        if (key_exists($str, static::TRANSLATES)) {
            if (empty($vars)) return static::TRANSLATES[$str];
            return sprintf(static::TRANSLATES[$str], ...$vars);
        }
        if (empty($vars)) return $str;
        return trim(sprintf($str, ...$vars));
    }
}