<?php
namespace totum\common\Lang;

trait TranslateTrait{

    public function translate(string $str, array|string $vars = []): string
    {
        if (!is_array($vars)) {
            $vars = [$vars];
        }

        if (key_exists($str, static::TRANSLATES)) {
            if (empty($vars)) return static::TRANSLATES[$str];
            return sprintf(static::TRANSLATES[$str], ...$vars);
        }
        if (empty($vars)) return $str;
        return trim(sprintf($str, ...$vars));
    }
}