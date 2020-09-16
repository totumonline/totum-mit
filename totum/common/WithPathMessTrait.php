<?php


namespace totum\common;
/*
 * for Exceptions
 * */
trait WithPathMessTrait
{
    protected $pathMess;

    function addPath($path)
    {
        if ($this->pathMess == '') $this->pathMess = $path;
        else {
            $this->pathMess = $this->getPathMess() . '; ' . $path;
        }
    }

    /**
     * @return mixed
     */
    public function getPathMess()
    {
        return $this->pathMess;
    }
}