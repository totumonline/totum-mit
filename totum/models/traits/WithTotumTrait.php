<?php


namespace totum\models\traits;


use totum\common\Totum;

trait WithTotumTrait
{
    /**
     * @var Totum
     */
    protected $Totum;

    function addTotum(Totum $Totum){
        $this->Totum=$Totum;
    }
}