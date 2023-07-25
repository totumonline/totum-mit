<?php

namespace totum\common\calculates;

use totum\common\errorException;
use totum\common\Services\ServicesConnector;
use totum\fieldTypes\File;

trait FuncServicesTrait
{

    protected function __checkAnswertype($params): string
    {
        $answertype = $params['answertype'] ?? 'filestring';
        $answertype = match ($answertype) {
            'filestringlist', 'filestring', 'filerow', 'filerowlist' => $answertype,
            default => throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'answertype'))
        };
        return $answertype;
    }

    protected function serviceRequests(\totum\config\Conf $Config, $serviceName, array $datas, $comment = null): array
    {
        if (count($datas) > 10) {
            throw new errorException($this->translate('Service does not accept more than 10 files'));
        }


        $hashes = [];
        $connector = ServicesConnector::init($Config);

        foreach ($datas as $data) {
            $hash = $Config->getServicesVarObject()->getNewVarnameHash(3600);
            if (!empty($comment)) {
                $comment = mb_substr((string)$comment, 0, 30);
                $data['comment'] = $comment;
            }
            $connector->sendRequest($serviceName, $hash, $data);
            $hashes[] = $hash;
        }
        $executes = $Config->getServicesVarObject()->waitVarValues($hashes, true);

        $answer = [];
        foreach ($hashes as $hash) {
            $answer[] = $executes[$hash];
        }
        return $answer;
    }

    protected function funcServiceXlsxParser($params)
    {
        $params = $this->getParamsArray($params);
        $Config = $this->Table->getTotum()->getConfig();


        $this->__checkNotArrayParams($params, ['comment', 'withformats', 'filestring', 'file']);
        $withFormats = !!$params['withformats'];
        $withColumns = !!$params['withcolumns'];

        $data = [];
        if ($params['filestring'] ?? null) {
            foreach ((array)$params['filestring'] as $_file) {
                $data[] = ['file' => base64_encode($_file), 'f' => $withFormats, 'c' => $withColumns];
            }
        } elseif ($params['file'] ?? null) {
            foreach ((array)$params['file'] as $_file) {
                $data[] = ['file' => base64_encode(File::getContent($_file, $Config)), 'f' => $withFormats, 'c' => $withColumns];
            }
        }

        if (empty($data)) {
            return [];
        }
        $answers = $this->serviceRequests($Config, 'xlsx', $data, $params['comment'] ?? null);

        return json_decode($answers[0], true);
    }

    protected function funcServiceAskOpenaiList($params)
    {
        $params = $this->getParamsArray($params);
        $Config = $this->Table->getTotum()->getConfig();

        $data = [];
        if (!empty($params['system']) && is_array($params['system'])) {
            foreach ($params['system'] as $i => $_data) {
                $row['s'] = $_data;
                if (key_exists('user', $params)) {
                    if (is_array($params['user'])) {
                        if (!key_exists($i, $params['user'])) {
                            throw new errorException($this->translate('The number of the [[%s]] must be equal to the number of [[%s]].', ['system', 'user']));
                        }
                        $row['u'] = $params['user'][$i];
                    } else {
                        $row['u'] = $params['user'];
                    }
                }
                $data[] = $row;
            }
        } elseif (!empty($params['user']) && is_array($params['user'])) {
            foreach ($params['user'] as $i => $_data) {
                $row['u'] = $_data;
                if (key_exists('system', $params)) {
                    if (is_array($params['system'])) {
                        if (!key_exists($i, $params['system'])) {
                            throw new errorException($this->translate('The number of the [[%s]] must be equal to the number of [[%s]].', ['system', 'user']));
                        }
                        $row['s'] = $params['system'][$i];
                    } else {
                        $row['s'] = $params['system'];
                    }
                }
                $data[] = $row;
            }
        } else {
            $row = [];
            if (!empty($params['user'])) {
                $row['u'] = $params['user'];
            }
            if (!empty($params['system'])) {
                $row['s'] = $params['system'];
            }
            if (empty($row)) {
                $this->__checkNotEmptyParams($params, ['user']);
            }
            $data[] = $row;
        }
        if (!empty($params['maxtokens'])) {
            $this->__checkNumericParam($params['maxtokens'], 'maxtokens');
            $data['max'] = $params['maxtokens'];
        }

        $answers = $this->serviceRequests($Config, 'openai', [$data], $params['comment'] ?? null);

        $results = [];

        foreach ($answers as &$answer) {
            $answer = json_decode($answer, true);
            $results[] = !empty($answer['error']) ? ['error' => $answer['error']] : match ($params['answer'] ?? 'full') {
                'content' => $answer[0]['message']['content'],
                'json' => json_decode($answer[0]['message']['content'], true),
                default => $answer,
            };
        }

        return $results;
    }

    protected function funcServiceAskOpenai($params)
    {
        $params = $this->getParamsArray($params, ['system', 'user'], ['system', 'user']);
        $this->__checkNotArrayParams($params, ['comment']);

        $datas['system'] = [];
        $datas['user'] = [];
        foreach (['system', 'user'] as $type) {
            foreach ($params[$type] ?? [] as $_s) {
                $fieldparam = $this->getCodes($_s);
                switch (count($fieldparam)) {
                    case 1:
                        $datas[$type][] = $this->__getValue($fieldparam[0]);
                        break;
                    case 3:
                        $val = '';
                        foreach ([$this->__getValue($fieldparam[0]), ' ', $this->__getValue($fieldparam[1])] as $_d) {
                            $val .= (is_array($_d) ? json_encode($_d, JSON_UNESCAPED_UNICODE) : $_d);
                        }
                        $datas[$type][] = $val;
                        break;
                    default:
                        throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                            $_s));
                }
            }
        }

        if (!empty($datas['system'])) {
            $data['s'] = implode("\n\n", $datas['system']);
        }
        if (!empty($datas['user'])) {
            $data['u'] = implode("\n\n", $datas['user']);
        }

        if (!empty($params['maxtokens'])) {
            $this->__checkNumericParam($params['maxtokens'], 'maxtokens');
            $data['max'] = $params['maxtokens'];
        }
        $Config = $this->Table->getTotum()->getConfig();
        $answers = $this->serviceRequests($Config, 'openai', [[$data]], $params['comment'] ?? null);

        if (!($answer = @json_decode($answers[0], true))) {
            throw new errorException($this->translate('Error: %s', 'Service not answered'));
        }
        if (!empty($answer['error'])) {
            throw new errorException($this->translate('Error: %s', $answer['error']));
        }
        return match ($params['answer'] ?? 'full') {
            'content' => $answer[0]['message']['content'],
            'json' => json_decode($answer[0]['message']['content'], true),
            default => $answer,
        };
    }

    protected function funcServiceXlsxGenerator($params)
    {
        $params = $this->getParamsArray($params);
        return $this->generateByTemplate($params, 'xlsx', 'xlsx');
    }

    protected function generateByTemplate($params, $serviceName, $extention)
    {

        $this->__checkNotEmptyParams($params, ['template']);
        $this->__checkNotArrayParams($params, ['comment']);
        $this->__checkListParam($params, ['data']);

        if (is_array($params['template']) && key_exists(0, $params['data'])) {
            if (count($params['template']) != count($params['data'])) {
                throw new errorException('Number of elements %s and %s do not match', ['template', 'data']);
            } else {
                $templates = $params['template'];
                $datas = $params['data'];
            }
        } elseif (key_exists(0, $params['data']) && $params['template'] !== '*NEW*') {
            $templates = array_fill(0, count($params['data']), $params['template']);
            $datas = $params['data'];
        } elseif (is_array($params['template'])) {
            $datas = array_fill(0, count($params['template']), $params['data']);
            $templates = $params['template'];
        } else {
            $templates = (array)$params['template'];
            $datas = [$params['data']];
        }

        $Config = $this->Table->getTotum()->getConfig();

        $preparedData = [];
        foreach ($templates as $i => $template) {
            $pdf = $params['pdf'] ?? false;
            $_pdf = is_array($pdf) && key_exists(0, $pdf) ? ($pdf[$i] ?? false) : $pdf;
            $_pdf = match ($_pdf) {
                'true', true => true,
                default => is_array($_pdf) ? $_pdf : false
            };
            $preparedData[] = [
                'template' => $template === '*NEW*' ? '*NEW*' : base64_encode(File::getContent($template, $Config)),
                'data' => $datas[$i],
                'pdf' => $_pdf,
            ];
        }
        unset($template);


        $answertype = $this->__checkAnswertype($params);
        if (in_array($answertype, ['filestring', 'filerow'])) {
            $preparedData = [$preparedData[0]];
        }

        $answers = $this->serviceRequests($Config, $serviceName, $preparedData, $params['comment'] ?? null);

        $exts = [];
        foreach ($preparedData as $p) {
            $exts[] = $p['pdf'] ? 'pdf' : $extention;
        }
        return $this->getNamedAnswers($answertype, (array)($params['name'] ?? []), $answers, $exts);
    }

    protected function funcServiceDocxGenerator($params)
    {
        $params = $this->getParamsArray($params);
        return $this->generateByTemplate($params, 'docx', 'docx');
    }

    protected function funcServicePDFGenerator($params)
    {
        $params = $this->getParamsArray($params);
        $answertype = $this->__checkAnswertype($params);


        $this->__checkNotEmptyParams($params, 'type');
        $this->__checkNotArrayParams($params, 'comment');
        $pdf = $params['pdf'] ?? null;
        $types = (array)$params['type'];

        foreach ($types as &$_) {
            $_ = match ($_) {
                'html', 'xlsx', 'docx' => $_,
                default => throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'type'))
            };
        }
        unset($_);


        if (!empty($params['filestring'])) {
            $files = (array)$params['filestring'];
        } elseif (!empty($params['file'])) {
            $files = [];
            foreach ((array)$params['file'] as $file) {
                $files[] = File::getContent($file, $this->Table->getTotum()->getConfig());
            }
        } else {
            throw new errorException($this->translate('Fill in the parameter [[%s]].',
                ['file/filestring']));
        }

        if (count($types) < count($files)) {
            if (!is_array($params['type'])) {
                $types = array_fill(0, count($files), $types[0]);
            } else {
                throw new errorException('Number of elements %s and %s do not match', ['file/filestring', 'type']);
            }
        }

        if (in_array($answertype, ['filestring', 'filerow'])) {
            $files = [$files[0]];
        }

        $datas = [];
        foreach ($files as $i => &$file) {
            if ($types[$i] === 'html') {
                $file = File::replaceImageSrcsWithEmbedded($this->Table->getTotum()->getConfig(), $file);
            }
            $datas[] = [
                'file' => base64_encode($file),
                'type' => $types[$i],
            ];
            if (!empty($pdf)) {
                if (is_array($pdf) && key_exists(0, $pdf)) {
                    if (!empty($pdf[$i])) {
                        $datas[array_key_last($datas)]['pdf'] = $pdf[$i];
                    }
                } else {
                    $datas[array_key_last($datas)]['pdf'] = $pdf;
                }
            }
        }
        unset($file);

        $Config = $this->Table->getTotum()->getConfig();
        $answers = $this->serviceRequests($Config, 'pdf', $datas, $params['comment'] ?? null);
        return $this->getNamedAnswers($answertype, (array)($params['name'] ?? []), $answers, 'pdf');

    }

    protected function getNamedAnswers($answertype, $names, $answers, $exts)
    {
        $getName = $this->getNamesFunction($names, 'pdf');

        switch ($answertype) {
            case 'filestring':
                return $answers[0];
            case 'filestringlist':
                return $answers;
            case 'filerow':
                return ['name' => $getName(is_array($exts) ? $exts[0] : $exts), 'filestring' => $answers[0]];
            case 'filerowlist':
                $rowList = [];
                foreach ($answers as $i => $a) {
                    $rowList[] = ['name' => $getName(is_array($exts) ? $exts[$i] : $exts), 'filestring' => $a];
                }
                return $rowList;

        }
    }

    protected function getNamesFunction($names): \Closure
    {
        $namesIterator = 0;
        $namesPostfixIterator = 0;
        $getName = function ($extention) use (&$namesPostfixIterator, $names, &$namesIterator) {
            $extention = '.' . $extention;
            $name = $names[$namesIterator] ?? false;
            if (!$name) {
                $name = '__' . ($namesPostfixIterator > 0 ? ($namesPostfixIterator + 1) : '');
                $namesPostfixIterator++;
            }
            if (!str_ends_with($name, $extention)) {
                $name .= $extention;
            }
            $namesIterator++;
            return $name;
        };
        return $getName;
    }
}