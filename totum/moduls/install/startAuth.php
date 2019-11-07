<?php


namespace totum\moduls\install;


use totum\common\Auth;

class startAuth extends Auth
{
    protected $roleIds=["1"];

    protected function __construct($rowData)
    {
        $this->rowData=$rowData;

    }
    static function startUser($rowData){
        static::$aUser=new startAuth($rowData);
    }
    static function isCreator()
    {
      return true;
    }
}