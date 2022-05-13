<?php

namespace totum\common\Lang;

trait SearchTrait
{
    public function getSearchFunction($q): callable
    {

        $qs = new \stdClass();
        if (preg_match('/^([!\^~= ]+):\s*/', $q, $controlMatches)) {
            $q = substr($q, strlen($controlMatches[0]));
        }
        $q = $this->searchPrepare($q);
        $qs->searchVar = $q;
        $qs->searchArray = explode(' ', $q);

        if ($q === '') {
            return function ($v) {
                return true;
            };
        }


        $every = function ($array, $callback) {
            foreach ($array as $a) {
                if (!$callback($a)) {
                    return false;
                }
            }
            return true;
        };

        return match ($controlMatches[1] ?? '') {
            '!==' => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                return $qs->searchVar !== $v;
            },
            '==' => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                return $qs->searchVar === $v;
            },
            '=' => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                return mb_strpos($v, $qs->searchVar) !== false;
            },
            '!=' => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                return mb_strpos($v, $qs->searchVar) === false;
            },
            '!', '!~' => function ($v) use ($every, $qs) {
                $v = $this->searchPrepare($v);
                return $every($qs->searchArray, function ($q) use ($v) {
                    return mb_strpos($v, $q) === false;
                });
            },
            '^' => function ($v) use ($every, $qs) {
                $v = $this->searchPrepare($v);
                $v = explode(' ', $v);
                return $every($qs->searchArray, function ($q) use ($v) {
                    foreach ($v as $_v) {
                        if (mb_strpos($_v, $q) === 0) {
                            return true;
                        }
                    }
                    return false;
                });
            },
            '^=' => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                return mb_strpos($v, $qs->searchVar) === 0;
            },

            default => function ($v) use ($qs) {
                $v = $this->searchPrepare($v);
                foreach ($qs->searchArray as $q) {
                    if ($q !== '' && mb_stripos($v, $q) === false) {
                        return false;
                    }
                }
                return true;

            }
        };
    }


    public
    function searchPrepare($string): string
    {
        return mb_strtolower(trim((string)$string));
    }

}