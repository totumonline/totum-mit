<?php

namespace totum\common;

abstract class jsonController extends Controller
{
    public function doIt($action)
    {
        try {
            $this->__runAction($action);
        } catch (errorException $e) {
            $this->__addAnswerVar('error', $e->getMessage());
        }
        echo json_encode($this->answerVars, JSON_UNESCAPED_UNICODE);
    }

    protected function __runAction($action)
    {
        $this->{'action_' . $action}();
    }

}