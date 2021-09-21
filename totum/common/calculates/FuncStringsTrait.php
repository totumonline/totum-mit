<?php

namespace totum\common\calculates;

use \JsonException;
use totum\common\errorException;
use totum\common\Lang\RU;

trait FuncStringsTrait
{

    protected function funcJsonCreate(string $params): string
    {
        if ($params = $this->getParamsArray($params, ['field', 'flag'], ['field'])) {

            if (key_exists('data', $params)) {
                if (key_exists('field', $params)) {
                    $this->__checkListParam($params['data'], 'data');
                }
                $data = $params['data'];
            } else {
                $data = [];
            }

            foreach ($params['field'] ?? [] as $f) {
                $f = $this->getExecParamVal($f, 'field');
                if (ctype_digit(strval(array_keys($f)[0]))) {
                    $data = $f + $data;
                } else {
                    $data = array_merge($data, $f);
                }
            }
            $flags = 0;
            if (key_exists('flag', $params)) {
                $escaped = false;
                foreach ($params['flag'] as $flag) {
                    switch ($flag) {
                        case 'ESCAPED_UNICODE':
                            $escaped = true;
                            break;
                        case 'PRETTY':
                            $flags = $flags | JSON_PRETTY_PRINT;
                            break;
                    }
                }
                if (!$escaped) {
                    $flags = $flags | JSON_UNESCAPED_UNICODE;
                }
            } else {
                $flags = JSON_UNESCAPED_UNICODE;
            }

            $r = json_encode($data, $flags);

            if ($r !== false) {
                return $r;
            }
            throw new errorException($this->translate('JSON generation error: [[%s]].', json_last_error_msg()));
        }
        return '';
    }

    protected function funcJsonExtract(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['text']);
        $this->__checkNotArrayParams($params, ['text']);

        try {
            return json_decode((string)$params['text'], true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new errorException($this->translate('JSON parsing error: [[%s]].', $e->getMessage()));
        }
    }

    protected function funcStrAdd(string $params): string
    {
        $vars = $this->getParamsArray($params, ['str']);
        ksort($vars);
        $str = '';

        foreach ($vars['str'] ?? [] as $v) {
            if (is_array($v)) {
                throw new errorException($this->translate('The parameter [[%s]] should be of type string.', 'str'));
            }
            $str .= $v;
        }

        return $str;

    }

    protected function funcStrBaseDecode(string $params): bool|string
    {
        $params = $this->getParamsArray($params);
        if (!key_exists('str', $params) || is_array($params['str'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required and should be a string.', 'str'));
        }
        return base64_decode($params['str']);
    }

    protected function funcStrBaseEncode(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str']);
        $this->__checkNotArrayParams($params, ['str']);

        return base64_encode($params['str']);
    }

    protected function funcStrGz(string $params): bool|string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str']);
        $this->__checkNotArrayParams($params, ['str']);
        return gzencode($params['str']);

    }

    protected function funcStrLength(string $params): bool|int
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str'], 'strLength');
        $this->__checkNotArrayParams($params, ['str']);

        return mb_strlen($params['str'], 'utf-8');

    }

    protected function funcStrMd5(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str'], 'strMd5');
        $this->__checkNotArrayParams($params, ['str']);

        return md5($params['str']);
    }

    protected function funcStrPart(string $params): string
    {
        $params = $this->getParamsArray($params);

        $this->__checkRequiredParams($params, ['str', 'offset'], 'strPart');

        if ($params['str']) {
            $this->__checkNotArrayParams($params, ['str']);
            $str = (string)$params['str'];
        } else {
            $str = '';
        }
        if ($params['offset']) {
            $this->__checkNotArrayParams($params, ['offset']);

            $offset = (int)$params['offset'];
        } else {
            $offset = 0;
        }
        $length = null;

        if (key_exists('length', $params)) {
            $this->__checkNotArrayParams($params, ['length']);
            if ($params['length']) {
                $length = (int)$params['length'];
            } else {
                $length = $params['length'];
            }
        }

        return mb_substr($str, $offset, $length, 'UTF-8');
    }

    protected function funcStrRandom(string $params): string
    {

        $numbers = '0123456789';
        $letters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $simbols = '!@#$%^&*()_+=-%,.;:';

        $params = $this->getParamsArray($params);
        $this->__checkNotArrayParams($params, ['length', 'numbers', 'letters', '']);
        $this->__checkRequiredParams($params, ['length']);

        $length = (int)$params['length'];
        if ($length < 1) {
            throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].', ['length', '> 0']));
        }
        $getCharacters = function ($key, string $defaultSimbols) use ($params): string {
            if (array_key_exists($key, $params)) {
                if ($params[$key] !== 'false' && $params[$key] !== false) {
                    return match ($params[$key]) {
                        'true' => $defaultSimbols,
                        default => strval($params[$key]),
                    };
                }
            } else {
                return $defaultSimbols;
            }
            return '';
        };
        $characters = $getCharacters('numbers', $numbers);
        $characters .= $getCharacters('letters', $letters);
        $characters .= $getCharacters('simbols', $simbols);

        if (!$characters) {
            throw new errorException($this->translate('No characters selected for generation.'));
        }

        $charactersLength = mb_strlen($characters, 'utf-8');
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= mb_substr($characters, mt_rand(0, $charactersLength - 1), 1, 'utf-8');
        }
        return $randomString;
    }

    protected function funcStrRegAllMatches(string $params): bool
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['template', 'str']);
        $this->__checkNotArrayParams($params, ['template', 'matches', 'str', 'flags']);

        if ($r = preg_match_all(
            '/' . str_replace('/', '\/', $params['template']) . '/'
            . ($params['flags'] ?? 'u'),
            (string)$params['str'],
            $matches
        )) {
            if (!empty($params['matches'])) {
                $this->vars[$params['matches']] = $matches;
            }
        }
        if ($r === false) {
            throw new errorException($this->translate('Regular expression error: [[%s]]', $params['template']));
        }
        return !!$r;
    }

    protected function funcStrRegMatches(string $params): bool
    {
        $params = $this->getParamsArray($params);

        $this->__checkRequiredParams($params, ['template', 'str']);
        $this->__checkNotArrayParams($params, ['template', 'matches', 'str', 'flags']);

        if ($r = preg_match(
            '/' . str_replace('/', '\/', (string)$params['template']) . '/'
            . ($params['flags'] ?? 'u'),
            (string)$params['str'],
            $matches
        )) {
            if (!empty($params['matches'])) {
                $this->vars[$params['matches']] = $matches;
            }
        }
        if ($r === false) {
            throw new errorException($this->translate('Regular expression error: [[%s]]', $params['template']));
        }
        return !!$r;
    }

    protected function funcStrRepeat(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str', 'num'], 'strRepeat');
        $this->__checkNotArrayParams($params, ['str', 'num']);
        return str_repeat($params['str'], (int)$params['num']);
    }

    protected function funcStrReplace(string $params): array|string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str', 'from', 'to'], 'strRepeat');

        return str_replace($params['from'], $params['to'], $params['str']);
    }

    protected function funcStrSplit(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str'], 'strSplit');
        $this->__checkNotArrayParams($params, ['str', 'separator']);

        if (!key_exists('separator', $params)) {
            $list = [$params['str']];
        } elseif ($params['separator'] === '' || is_null($params['separator'])) {
            $list = str_split($params['str']);
        } else {
            $list = explode($params['separator'], $params['str']);
        }
        if (key_exists('limit', $params)) {
            $this->__checkNotArrayParams($params, ['limit']);

            if (!ctype_digit(strval($params['limit']))) {
                throw new errorException($this->translate('The %s parameter must be a number.', 'limit'));
            }
            if ($params['limit'] < count($list)) {
                $list = array_slice($list, 0, (int)$params['limit']);
            }
        }

        return $list;
    }

    protected function funcStrTransform(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str', 'to'], 'strTransform');
        $this->__checkNotArrayParams($params, ['str', 'to']);

        return match ($params['to'] ?? '') {
            'upper' => mb_convert_case($params['str'], MB_CASE_UPPER, 'UTF-8'),
            'lower' => mb_convert_case($params['str'], MB_CASE_LOWER, 'UTF-8'),
            'capitalize' => mb_convert_case($params['str'], MB_CASE_TITLE, 'UTF-8'),
            default => throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'to')),
        };

    }

    protected function funcStrUnGz(string $params): bool|string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['str']);
        $this->__checkNotArrayParams($params, ['str']);

        return gzdecode($params['str']);
    }

    protected function funcSysTranslit(string $params): string
    {
        $params = $this->getParamsArray($params);

        $this->__checkRequiredParams($params, ['str']);
        $this->__checkNotArrayParams($params, ['str']);

        return $this->getLangObj()->translit((string)$params['str']);
    }

    protected function funcTextByTemplate(string $params): string
    {
        $params = $this->getParamsArray($params);

        $getTemplate = function ($name) {
            return $this->Table->getTotum()->getModel('print_templates')->getPrepared(
                ['name' => $name],
                'styles, html, name'
            );
        };

        $this->__checkNotArrayParams($params, ['template', 'text']);

        if ($params['template'] ?? null) {
            if ($main = $getTemplate($params['template'])) {
                $mainTemplate = $main['html'];
                $style = $main['styles'];
            } else {
                throw new errorException($this->translate('Template not found.'));
            }
        } elseif ($params['text'] ?? null) {
            $mainTemplate = $params['text'];
            $style = null;
        } else {
            throw new errorException($this->translate('Template not found.'));
        }


        $usedStyles = [];

        $funcReplaceTemplates = function ($html, $data) use (&$funcReplaceTemplates, $getTemplate, &$style, &$usedStyles) {
            return preg_replace_callback(
                '/{(([a-z_0-9]+)(\["[a-z_0-9]+"\])?(?:,([a-z]+(?::[^}]+)?))?)}/',
                function ($matches) use ($data, $getTemplate, &$funcReplaceTemplates, &$style, &$usedStyles) {
                    if (!key_exists($matches[2], $data)) {
                        return '';
                    }
                    if (is_array($data[$matches[2]])) {
                        if (!empty($matches[3])) {
                            $matches[3] = substr($matches[3], 2, -2);
                            if (!array_key_exists(
                                $matches[3],
                                $data[$matches[2]]
                            )) {
                                throw new errorException($this->translate('There is no [[%s]] key in the [[%s]] list.',
                                    [$matches[3], $matches[2]]));
                            }
                            $value = $data[$matches[2]][$matches[3]];
                        } else {
                            if (!empty($data[$matches[2]]['template'])) {
                                $template = $getTemplate($data[$matches[2]]['template']);
                                if (!$template) {
                                    throw new errorException($this->translate('Not found template [[%s]] for parameter [[%s]].',
                                        [$data[$matches[2]]['template'], $matches[2]]));
                                }

                                if (!in_array($template['name'], $usedStyles)) {
                                    $style .= $template['styles'];
                                    $usedStyles[] = $template['name'];
                                }
                            } elseif (key_exists('text', $data[$matches[2]])) {
                                $template = ['html' => $data[$matches[2]]['text']];
                            } else {
                                throw new errorException($this->translate('No template is specified for [[%s]].',
                                    $matches[2]));
                            }

                            $html = '';


                            if (array_key_exists(0, $data[$matches[2]]['data'])) {
                                foreach ($data[$matches[2]]['data'] ?? [] as $_data) {
                                    $html .= $funcReplaceTemplates($template['html'], $_data);
                                }
                            } else {
                                $html .= $funcReplaceTemplates(
                                    $template['html'],
                                    (array)$data[$matches[2]]['data']
                                );
                            }

                            return $html;
                        }
                    } else {
                        /*$data[$matches[2]] - не массив*/
                        $value = $data[$matches[2]];
                    }

                    if (!empty($matches[4])) {
                        $value = $this->getFormattedValue($matches[4], (string)$value);
                    }

                    return $value;

                },
                $html
            );
        };


        if ($style) {
            return '<style>' . $style . '</style><body>' . $funcReplaceTemplates(
                    $mainTemplate,
                    $params['data'] ?? []
                ) . '</body>';
        } else {
            return $funcReplaceTemplates($mainTemplate, $params['data'] ?? []) ?? '';
        }
    }

    protected function funcXmlExtract(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['xml'], 'xmlExtract');

        $params['attrpref'] = $params['attrpref'] ?? '';
        $params['textname'] = $params['textname'] ?? 'TEXT';

        if ($xml = @simplexml_load_string($params['xml'])) {
            $getData = function (\SimpleXMLElement $xml) use (&$getData, $params) {
                $children = [];
                foreach ($xml->attributes() as $k => $attr) {
                    $children[$params['attrpref'] . $k] = (string)$attr;
                }
                foreach ($xml->getNamespaces() as $pref => $namespace) {
                    foreach ($xml->children($namespace) as $k => $child) {
                        $children[$pref . ':' . $k][] = $getData($child);
                    }
                }
                foreach ($xml->children() as $k => $child) {
                    $children[$k][] = $getData($child);
                }
                if ((string)$xml) {
                    $children[$params['textname']] = trim((string)$xml);
                }
                return $children;
            };

            return [$xml->getName() => $getData($xml)];
        }

        throw new errorException($this->translate('XML Format Error.'));
    }


    private function getFormattedValue(string $formatString, mixed $value): mixed
    {
        if ($formatData = explode(':', $formatString, 2)) {
            switch ($formatData[0]) {
                case 'money':
                    if (is_numeric($value)) {
                        $value = $this->getLangObj()->num2str($value);
                    }
                    break;
                case 'number':
                    if (count($formatData) === 2) {
                        if (is_numeric($value)) {
                            if ($numberVals = explode('|', $formatData[1])) {
                                $value = number_format(
                                        $value,
                                        (int)$numberVals[0],
                                        $numberVals[1] ?? '.',
                                        $numberVals[2] ?? ''
                                    )
                                    . ($numberVals[3] ?? '');
                            }
                        }
                    }
                    break;
                case 'date':
                    if (count($formatData) === 2) {
                        if ($date = date_create($value)) {
                            $value = $this->getLangObj()->dateFormat($date, $formatData[1]);
                        }
                    }
                    break;
                case 'checkbox':
                    if (is_bool($value)) {
                        $sings = [];
                        if (count($formatData) === 2) {
                            $sings = explode('|', $formatData[1] ?? '');
                        }

                        $value = match ($value) {
                            true => $sings[0] ?? '✓',
                            false => $sings[1] ?? '-',
                        };
                    }
                    break;
            }
        }
        return $value;
    }
}