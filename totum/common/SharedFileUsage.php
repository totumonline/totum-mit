<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 13.02.18
 * Time: 9:15
 */

namespace totum\common;


class SharedFileUsage
{
    private $fOpen;

    public function __construct($file)
    {
        $this->fOpen = fopen($file, 'c+');
    }

    public function __destruct()
    {
        fclose($this->fOpen);
    }

    public function read()
    {
        flock($this->fOpen, LOCK_SH);
        $data = stream_get_contents($this->fOpen);
        rewind($this->fOpen);
        flock($this->fOpen, LOCK_UN);
        return $this->unshifr($data);
    }

    function update($update, $deleteFunc = null)
    {
        flock($this->fOpen, LOCK_EX);

        if ($data = stream_get_contents($this->fOpen)) {
            $oldData = $this->unshifr($data);
            if ($oldData === false) return false;
        } else {
            $oldData = array();
        }
        rewind($this->fOpen);
        $update = $update + $oldData;
        if ($deleteFunc) {
            array_map(function ($k, $v) use ($deleteFunc, &$update) {
                if ($deleteFunc($k, $v)) {
                    unset($update[$k]);
                }
            },
                array_keys($update),
                array_values($update));
        }

        if ($update != $oldData) {

            ftruncate($this->fOpen, 0);
            $len = fwrite($this->fOpen, $data = $this->shifr($update));
            rewind($this->fOpen);
            flock($this->fOpen, LOCK_UN);
            return $len === strlen($data);

        } else return true;
    }

    private function unshifr($data)
    {
        if (substr($data, -1, 1) !== '.') return false;
        $data = substr($data, 0, -1);
        $array = [];

        foreach (explode("\n", $data) as $row) {
            @list($k, $v) = explode(':', $row, 2);
            $array[$k] = $v;
        }
        return $array;
    }

    private function shifr($data)
    {
        $output = "";
        foreach ($data as $k => $v) {
            if ($output != "") $output .= "\n";
            $output .= $k . ':' . $v;
        }
        return $output . '.';
    }

}