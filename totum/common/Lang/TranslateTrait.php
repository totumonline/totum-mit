<?php

namespace totum\common\Lang;

trait TranslateTrait
{
    protected array $templateReplaces = [];

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

        if (key_exists($str, $this->templateReplaces)) {
            $str = $this->templateReplaces[$str];
        }

        if (key_exists($str, static::TRANSLATES)) {
            if (empty($vars)) return static::TRANSLATES[$str];
            return sprintf(static::TRANSLATES[$str], ...$vars);
        }
        if (empty($vars)) return $str;
        return trim(sprintf($str, ...$vars));
    }

    public function replaceTempates(string|array $from, string|array $to): void
    {
        $from = (array)$from;
        $to = (array)$to;

        if (count($to) !== count($from)) {
            throw new \Exception('Lang template replaces error');
        }

        foreach ($from as $i => $_from) {
            if (!key_exists($i, $to)) {
                throw new \Exception('Lang template replaces error');
            }
            $this->templateReplaces[$_from] = $to[$i];
        }
    }
}