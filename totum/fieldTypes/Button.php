<?php

namespace totum\fieldTypes;

use totum\common\Field;

class Button extends Field
{

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        $val = null;
    }

}