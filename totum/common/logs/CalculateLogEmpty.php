<?php


namespace totum\common\logs;


class CalculateLogEmpty extends CalculateLog
{
    public function getChildInstance($params)
    {
        return new static([], $this);
    }
    public function getParent()
    {
        return $this->parent;
    }
    public function addParam($key, $value)
    {
    }
}