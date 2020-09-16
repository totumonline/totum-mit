<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 04.12.17
 * Time: 15:28
 */

namespace totum\common;

/*
 * Object of changing value in field
 * */
class FieldModifyItem
{
    protected $sign, $val, $percent;

    public function __construct($sign, $val, $percent = false)
    {
        $this->val = $val;
        $this->sign = $sign;
        $this->percent = $percent;
    }
    function __get($name)
    {
        return $this->$name;
    }

}