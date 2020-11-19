<?php


namespace totum\models\traits;

use totum\common\Totum;

trait WithTotumTrait
{
    /**
     * @var Totum
     */
    protected $Totum;

    public function addTotum(Totum $Totum)
    {
        $this->Totum=$Totum;
    }
}
