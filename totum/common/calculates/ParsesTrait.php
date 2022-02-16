<?php

namespace totum\common\calculates;

use \JsonException;
use totum\common\errorException;
use totum\common\Json\TotumJson;

trait ParsesTrait
{

    public static function parseTotumCode($code, $table_name = null): array
    {
        $c = [];
        $fixes = [];
        $catches = [];
        $strings = [];
        $lineParams = [];

        $tableParams = [];


        $usedHashParams = function (string $clearedLine) use ($table_name, &$tableParams) {
            if ($table_name) {
                if (preg_match_all('/(.?)#(?:[a-z]{1,3}\.)?([a-z_0-9]+)/', $clearedLine, $matches)) {
                    foreach ($matches[2] as $i => $m) {
                        if ($matches[1][$i] !== '$') {
                            $tableParams[$table_name][$m] = 1;
                        }
                    }
                }
                if (preg_match_all('/@([a-z_0-9]{2,})\.([a-z_0-9]+)/', $clearedLine, $matches)) {
                    foreach ($matches[2] as $i => $m) {
                        $tableParams[$matches[1][$i]][$m] = 1;
                    }
                }
            }
        };
        $replace_line_params = function ($line) use ($usedHashParams, &$lineParams) {
            return preg_replace_callback(
                '/{([^}]+)}/',
                function ($matches) use ($usedHashParams, &$lineParams) {
                    if ($matches[1] === '') {
                        return '{}';
                    }
                    $Num = count($lineParams);
                    $lineParams[] = $matches[1];
                    $usedHashParams($matches[1]);
                    return '{' . $Num . '}';
                },
                $line
            );
        };
        $replace_strings = function ($line) use ($usedHashParams, &$strings, &$replace_line_params, &$replace_strings) {
            return preg_replace_callback(
                '/(?|(math|json|str|cond)`([^`]*)`|(")([^"]*)"|(\')([^\']*)\')/',
                function ($matches) use ($usedHashParams, &$strings, &$replace_line_params, &$replace_strings) {
                    if ($matches[1] === '') {
                        return '""';
                    }
                    $type = $matches[1];
                    switch ($matches[1]) {
                        case 'json':
                            if (!json_decode($matches[2]) && json_last_error()) {
                                $matches[2] = $replace_strings($matches[2]);
                                $usedHashParams($matches[2]);
                                $type = 'jsot';
                            }
                            break;
                        case 'math':
                        case 'str':
                        case 'cond':
                            $matches[2] = $replace_strings($matches[2]);
                            $matches[2] = $replace_line_params($matches[2]);
                            $usedHashParams($matches[2]);
                            break;
                    }
                    $stringNum = count($strings);
                    $strings[] = $type . $matches[2];
                    return '"' . $stringNum . '"';
                },
                $line
            );
        };


        $isInCodeNamed = null;
        $codeType = null;
        $codeContent = '';
        foreach (preg_split('/[\r\n]+/', trim($code)) as $row) {

            if ($isInCodeNamed) {
                /*codeBlockEnd*/
                if (trim($row) === '```') {
                    $isInCodeNamed = $codeType = null;
                    $lineName = trim($matches['1']);
                    if (str_starts_with($lineName, '~')) {
                        $lineName = substr($lineName, 1);
                        $fixes[] = $lineName;
                    }

                    /*Сразу сохраняем в строки*/

                    $stringNum = count($strings);
                    $strings[] = '"' . $codeContent;
                    $c[$lineName] = '"' . $stringNum . '"';

                    $codeContent = '';
                } else {
                    if ($codeType === 'totum') {
                        $row = trim($row);
                        /*Для кода Тотум убираем комментарии и пустые строки*/
                        if (empty($row) || str_starts_with($row, '//')) {
                            continue;
                        }
                    }
                    if ($codeContent) {
                        $codeContent .= "\n";
                    }
                    $codeContent .= $row;
                }
                continue;
            }
            $row = trim($row);
            //checkCodeBlock
            if (preg_match('/^```(?<codeName>~?[a-zA-Z0-9_]+):(?<codeType>[a-z]+)$/', $row, $matches)) {
                $isInCodeNamed = $matches['codeName'];
                $codeType = $matches['codeType'];
                continue;
            }


            /*Убрать комментарии*/
            if (str_starts_with($row, '//')) {
                continue;
            }
            /*Разбираем код построчно*/
            if (preg_match('/^([a-z0-9]*=\s*|~?[a-zA-Z0-9_]+)\s*(?<catch>[a-zA-Z0-9_]*)\s*:(.*)$/', $row, $matches)) {
                $lineName = trim($matches['1']);
                if (str_starts_with($lineName, '~')) {
                    $lineName = substr($lineName, 1);
                    $fixes[] = $lineName;
                }

                /*TryCatch*/
                if (substr($lineName, -1, 1) === '=' && $matches['catch']) {
                    $catch = $matches['catch'];
                    $catches [$lineName] = $catch;
                }

                $line = trim($matches[3]);
                /*Используемые параметры в функциях*/
                if ($table_name) {
                    if (preg_match_all('/\(.*?table:\s*\'([a-z0-9_]+)\'.*?\)/', $line, $matches)) {
                        foreach ($matches[1] as $i => $t_name) {
                            if (preg_match_all(
                                '/(field|where|order|sfield|bfield|tfield|preview|parent|section|table|filter):\s*\'([a-z0-9_]+)\'/',
                                $matches[0][$i],
                                $mches
                            )) {
                                foreach ($mches[2] as $field) {
                                    $tableParams[$t_name][$field] = 1;
                                }
                            }
                        }
                    }
                    if (preg_match_all('/\(.*?table:\s*\$#ntn.*?\)/', $line, $matches)) {
                        foreach ($matches[0] as $i => $t_name) {
                            if (preg_match_all('/(field|where|order):\s*\'([a-z0-9_]+)\'/', $matches[0][$i], $mches)) {
                                foreach ($mches[2] as $field) {
                                    $tableParams[$table_name][$field] = 1;
                                }
                            }
                        }
                    }
                }


                $line = $replace_strings($line);
                $line = str_replace(' ', '', $line);

                $line = $replace_line_params($line);
                $usedHashParams($line);
                $c[$lineName] = $line;
            }
        }


        if ($fixes) {
            $c['==fixes=='] = $fixes;
        }
        if ($strings) {
            $c['==strings=='] = $strings;
        }
        if ($lineParams) {
            $c['==lineParams=='] = $lineParams;
        }
        if ($tableParams) {
            $c['==usedFields=='] = $tableParams;
        }
        if ($catches) {
            $c['==catches=='] = $catches;
        }

        return $c;
    }

    protected function parseTotumCond($string): ?bool
    {
        $string = preg_replace('/\s+/', '', $string);

        $actions = preg_split(
            '`(
                        \(|\)|
                        [&]{2}|
                        [|]{2}|
                        !==|
                        ==|
                        !=|
                        >=|
                        <=|
                        [><=]
                        )`x',
            $string,
            null,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pack_Sc = function ($actions) use (&$pack_Sc, &$calcIt) {
            $sc = 0;
            $interval_start = null;
            $i = 0;
            while ($i < count($actions)) {
                if ($actions[$i] === '') {
                    array_splice($actions, $i, 1);
                    continue;
                }
                switch ((string)$actions[$i]) {
                    case '(':
                        if ($sc++ === 0) {
                            $interval_start = $i;
                        }
                        break;
                    case ')':
                        if ($sc < 1) {
                            throw new errorException($this->translate('The unpaired closing parenthesis.'));
                        }
                        if (--$sc === 0) {
                            array_splice(
                                $actions,
                                $interval_start,
                                $i + 1 - $interval_start,
                                $calcIt($pack_Sc(array_slice(
                                    $actions,
                                    $interval_start + 1,
                                    $i - $interval_start - 1
                                )))
                            );
                            $i = $interval_start - 1;
                        }
                        break;
                }
                $i++;
            }
            return $actions;
        };

        $checkValue = function ($varIn, $onlyBool = true) {
            if ($varIn === 'false' || $varIn === false) {
                return false;
            }
            if ($varIn === 'true' || $varIn === true) {
                return true;
            }

            $var = $this->execSubCode($varIn, 'CondCode ' . $varIn);

            if ($onlyBool) {
                if ($var === 'false' || $var === false) {
                    return false;
                }
                if ($var === 'true' || $var === true) {
                    return true;
                }
                throw new errorException($this->translate('The parameter [[%s]] should be of type true/false.',
                    'cond:' . $varIn));
            }
            return $var;
        };

        $getValue = function ($var) use ($checkValue) {
            if ($var && !is_numeric($var)) {
                $var = $checkValue($var, false);
            }
            return $var;
        };

        $calcIt = function ($action) use ($checkValue, $getValue, $string) {
            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '<':
                    case '>':
                    case '=':
                    case '==':
                    case '!==':
                    case '!=':
                    case '<=':
                    case '>=':

                        $left = $getValue($action[$i - 1]);
                        $right = $getValue($action[$i + 1]);

                        $val = Calculate::compare($action[$i], $left, $right, $this->getLangObj());
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }


            $i = 0;

            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '&&':
                        $left = $i === 0 ? true : $checkValue($action[$i - 1]);

                        if (!$left) {
                            $val = false;
                        } elseif (key_exists($i + 1, $action)) {
                            $val = $checkValue($action[$i + 1]);
                        } else {
                            break 2;
                        }

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }

                        $i--;
                        break;
                    case '||':
                        $left = $i !== 0 && $checkValue($action[$i - 1]);

                        if ($left) {
                            $val = true;
                        } elseif (key_exists($i + 1, $action)) {
                            $val = $checkValue($action[$i + 1]);
                        } else {
                            break 2;
                        }

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }

                        $i--;
                }
                $i++;
            }
            if (count($action) !== 1) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', 'cond:' . $string));
            }
            return $checkValue($action[0]);
        };
        return $calcIt($pack_Sc($actions));
    }

    protected function parseTotumJson(string $str, $isSureTotum = false)
    {
        $processJson = function ($str) {
            $TJ = new TotumJson($str);
            $TJ->setTotumCalculate(function ($param) {
                return $this->execSubCode($param, 'paramFromJson');
            });
            $TJ->setStringCalculate(function ($str) {
                if (key_exists($str, $this->CodeStrings)) {
                    return substr($this->CodeStrings[$str], 1);
                } else {
                    return $str;
                }
            });
            $TJ->parse();
            return $TJ->getJson();
        };
        if ($isSureTotum) {
            return $processJson($str);
        }

        try {
            return json_decode($str, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $processJson($str);
        }
    }

    protected function parseTotumMath($string)
    {
        $string = preg_replace('/\s+/', '', $string);

        $actions = preg_split(
            '`((?<=[^(+\-^*/])[()+\-^*/]|[(])`',
            $string,
            null,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pack_Sc = function ($actions) use (&$pack_Sc, &$calcIt) {
            $sc = 0;
            $interval_start = null;
            $i = 0;
            while ($i < count($actions)) {
                if ($actions[$i] === '') {
                    array_splice($actions, $i, 1);
                    continue;
                }
                switch ((string)$actions[$i]) {
                    case '(':
                        if ($sc++ === 0) {
                            $interval_start = $i;
                        }
                        break;
                    case ')':
                        if ($sc < 1) {
                            throw new errorException($this->translate('The unpaired closing parenthesis.'));
                        }
                        if (--$sc === 0) {
                            array_splice(
                                $actions,
                                $interval_start,
                                $i + 1 - $interval_start,
                                $calcIt($pack_Sc(array_slice(
                                    $actions,
                                    $interval_start + 1,
                                    $i - $interval_start - 1
                                )))
                            );
                            $i = $interval_start - 1;
                        }
                        break;
                }
                $i++;
            }
            return $actions;
        };

        $checkValue = function ($var) {
            if ($var && !is_numeric($var)) {
                $var = $this->execSubCode($var, 'MathCode');
            }
            return $var;
        };

        $calcIt = function ($action) use ($checkValue, $string) {
            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '^':
                        $left = $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);
                        $val = $this->operatorExec($action[$i], $left, $right);
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }

            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '/':
                    case '*':
                        $left = $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);
                        $val = $this->operatorExec($action[$i], $left, $right);
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }

            $i = 0;

            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '+':
                    case '-':
                        $left = $i === 0 ? 0 : $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);

                        $val = $this->operatorExec($action[$i], $left, $right);

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }
                        $i--;
                }
                $i++;
            }
            if (count($action) !== 1 || !is_numeric((string)$action[0])) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', 'math:' . $string));
            }
            return $action[0];
        };
        return $calcIt($pack_Sc($actions));
    }

    protected function parseTotumStr($string): string
    {
        $string = preg_replace('/\s+/', '', $string);
        $result = '';

        foreach (explode('+', $string) as $i => $part) {
            if ($part === '') {
                $result .= ' ';
            } else {
                $res = $this->execSubCode($part, 'part' . $i);
                if (is_array($res)) {
                    $res = json_encode($res, JSON_UNESCAPED_UNICODE);
                }
                $result .= $res;
            }
        }
        return $result;
    }
}