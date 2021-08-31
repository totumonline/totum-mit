<?php

namespace totum\common\Lang;

interface LangInterface
{
    public function translate(string $str, array|string $vars=[]): string;
}