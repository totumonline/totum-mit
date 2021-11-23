<?php

namespace totum\common\calculates;

use Exception;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\fieldTypes\File;

trait FuncOperationsTrait
{

    protected function cURL($url, string $ref = '', $header = 0, $cookie = '', $post = null, $timeout = null, $headers = null, $method = null): bool|string|null
    {
        if ($headers) {
            $headers = (array)$headers;
        } else {
            $headers = [];
        }
        if ($cookie) {
            $headers[] = 'Cookie: ' . $cookie;
        }

        if ($timeout === 'parallel') {
            $data = '';
            if (empty($method)) {
                $method = null;
            }
            $localeOld = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'en_US.UTF-8');
            if (!is_null($post)) {
                $method = $method ?? 'POST';
                if (!empty($post)) {
                    $post = is_array($post) ? http_build_query($post) : $post;
                    $data = '--data ' . escapeshellarg($post);
                }
            } else {
                $method = $method ?? 'GET';
            }

            if ($ref) {
                $ref = '--referer ' . escapeshellarg($ref);
            }

            $hhs = [];
            foreach ($headers ?? [] as $h) {
                $hhs[] = '-H ' . escapeshellarg($h);
            }

            setlocale(LC_CTYPE, $localeOld);

            $hhs = implode(' ', $hhs);
            `curl --insecure --request $method $ref $hhs $url $data  > /dev/null 2>&1 &`;

            return null;
        }


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, $header);

        if ($timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        if (!empty($method)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!is_null($post)) {
            if (empty($method)) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
        }


        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$headers);
        }

        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            curl_close($ch);
            throw new errorException($error);
        }
        curl_close($ch);
        return $result;
    }

    protected function funcErrorExeption(string $params)
    {
        $params = $this->getParamsArray($params);
        if (!empty($params['text'])) {
            $this->__checkNotArrayParams($params, ['text']);
            throw new errorException((string)$params['text']);
        }
    }

    protected function funcErrorException(string $params)
    {
        $this->funcErrorExeption($params);
    }

    protected function funcExec(string $params): mixed
    {
        $params = $this->getParamsArray($params, ['var'], ['var']);

        $code = $params['code'] ?? $params['kod'] ?? '';
        if (!empty($code)) {
            if (preg_match('/^[a-z_0-9]{3,}$/', $code) && key_exists($code, $this->Table->getFields())) {
                $code = $this->Table->getFields()[$code]['code'] ?? '';
            }

            $CA = new Calculate($code);
            try {
                $Vars = [];
                foreach ($params['var'] ?? [] as $v) {
                    $Vars = array_merge($Vars, $this->getExecParamVal($v, 'var'));
                }
                $r = $CA->exec(
                    $this->varData,
                    $this->newVal,
                    $this->oldRow,
                    $this->row,
                    $this->oldTbl,
                    $this->tbl,
                    $this->Table,
                    $Vars
                );

                $this->newLogParent['children'][] = $CA->getLogVar();
                return $r;
            } catch (errorException $e) {
                $this->newLogParent['children'][] = $CA->getLogVar();
                throw $e;
            }
        }
        return null;
    }

    protected function funcExecSSH(string $params): bool|string|null
    {
        if (!$this->Table->getTotum()->getConfig()->isExecSSHOn()) {
            throw new criticalErrorException($this->translate('The ExecSSH function is disabled. Enable it in Conf.php.'));
        }
        $params = $this->getParamsArray($params);
        if (empty($params['ssh'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'ssh'));
        }
        $string = $params['ssh'];
        if ($params['vars'] ?? null) {
            $localeOld = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, 'en_US.UTF-8');

            if (!is_array($params['vars'])) {
                throw new errorException($this->translate('The parameter [[%s]] should be of type row/list.', 'vars'));
            }
            if (key_exists('0', $params['vars'])) {
                foreach ($params['vars'] as $v) {
                    $string .= ' ' . escapeshellarg($v) . '';
                }
            } else {
                foreach ($params['vars'] as $k => $v) {
                    $string .= ' ' . escapeshellcmd($k) . '=' . escapeshellarg($v) . '';
                }
            }
            setlocale(LC_CTYPE, $localeOld);
        }
        return shell_exec($string);
    }

    protected function funcFileGetContent(string $params): bool|string|null
    {
        $params = $this->getParamsArray($params);
        if (empty($params['file'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'file'));
        }

        return File::getContent($params['file'], $this->Table->getTotum()->getConfig());
    }

    protected function funcGetFromScript(string $params): bool|string|null
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match(
                '`https?://`',
                $params['uri']
            )) {
            throw new errorException($this->translate('The %s parameter is required and must start with %s.',
                ['uri', 'http/https']));
        }

        $link = $params['uri'];
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'GetFromScript');
        } elseif (!empty($params['posts'])) {
            $post = $params['posts'];
        } else {
            $post = null;
        }


        if (!empty($params['gets'])) {
            $link .= !str_contains($link, '?') ? '?' : '&';
            $link .= http_build_query($params['gets']);
        }

        $toBfl = $params['bfl'] ?? in_array(
                'script',
                $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
            );

        try {
            $r = $this->cURL(
                $link,
                'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                $params['header'] ?? 0,
                $params['cookie'] ?? '',
                $post,
                (($params['ssh'] ?? false) ? 'parallel' : $params['timeout'] ?? null),
                ($params['headers'] ?? ''),
                ($params['method'] ?? ''),
            );
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    'getFromScript',
                    [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            return $r;
        } catch (Exception $e) {
            if ($toBfl) {
                $r = $r ?? '';
                $this->Table->getTotum()->getOutersLogger()->error(
                    'getFromScript:',
                    ['error' => $e->getMessage()] + [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            throw new errorException($e->getMessage());
        }
    }

    /**
     * @deprecated
     */
    protected function funcGetVar(string $params)
    {
        $params = $this->getParamsArray($params, [], ['default']);
        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        if (!array_key_exists(
            $params['name'],
            $this->vars
        )) {
            if (array_key_exists('default', $params)) {
                $this->vars[$params['name']] = $this->execSubCode($params['default'], 'default');
            } else {
                throw new errorException($this->translate('The [[%s]] parameter has not been set in this code.',
                    $params['name']));
            }
        }
        return $this->vars[$params['name']];
    }

    protected function funcGlobVar(string $params)
    {
        $params = $this->getParamsArray($params, [], []);

        $this->__checkNotEmptyParams($params, 'name');

        $_params = [];
        if (key_exists('value', $params)) {
            $_params['value'] = $params['value'];
        } elseif (key_exists('default', $params)) {
            $_params['default'] = $params['default'];
        } elseif (key_exists('block', $params)) {
            $_params['block'] = $params['block'];
        }
        if ($params['date'] ?? false) {
            $_params['date'] = true;
        }

        return $this->Table->getTotum()->getConfig()->globVar($params['name'], $_params);
    }

    protected function funcIf(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['condition']);

        if ($this->getConditionsResult($params)) {
            if (array_key_exists('then', $params)) {
                return $this->execSubCode($params['then'], 'then');
            } else {
                return null;
            }
        } elseif (array_key_exists('else', $params)) {
            return $this->execSubCode($params['else'], 'else');
        } else {
            return null;
        }

    }

    protected function funcProcVar(string $params)
    {
        $params = $this->getParamsArray($params, [], []);
        $this->__checkNotEmptyParams($params, 'name');

        $_params = [];
        if (key_exists('value', $params)) {
            $_params['value'] = $params['value'];
        } elseif (key_exists('default', $params)) {
            $_params['default'] = $params['default'];
        }

        return $this->Table->getTotum()->getConfig()->procVar($params['name'], $_params);
    }

    /**
     * @deprecated
     */
    protected function funcSetVar(string $params)
    {
        $params = $this->getParamsArray($params);

        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        $this->__checkRequiredParams($params, ['value']);

        return $this->vars[$params['name']] = $params['value'];
    }

    protected function funcUserInRoles(string $params): bool
    {
        if ($params = $this->getParamsArray($params, ['role'])) {
            $roles = $this->Table->getTotum()->getUser()->getRoles();
            foreach ($params['role'] ?? [] as $role) {
                if (in_array($role, $roles)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function funcVar(string $params)
    {
        $params = $this->getParamsArray($params, [], ['default']);
        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        if (array_key_exists('value', $params)) {
            $this->vars[$params['name']] = $params['value'];
        } elseif (!array_key_exists($params['name'], $this->vars)) {
            if (array_key_exists('default', $params)) {
                $this->vars[$params['name']] = $this->execSubCode($params['default'], 'default');
            } else {
                throw new errorException($this->translate('The [[%s]] parameter has not been set in this code.',
                    $params['name']));
            }
        }
        return $this->vars[$params['name']];
    }

    protected function funcWhile(string $params)
    {
        $vars = $this->getParamsArray(
            $params,
            ['action', 'preaction', 'postaction'],
            ['action', 'preaction', 'postaction', 'limit']
        );

        $iteratorName = $vars['iterator'] ?? '';

        $return = null;

        if (!empty($vars['preaction'])) {
            foreach ($vars['preaction'] as $i => $action) {
                $return = $this->execSubCode($action, 'preaction' . (++$i));
            }
        }

        if (!empty($vars['action'])) {
            $limit = key_exists('limit', $vars) ? (int)$this->execSubCode($vars['limit'], 'limit') : 1;
            $whileIterator = 0;
            $isPostaction = false;

            while ($limit-- > 0) {
                if ($iteratorName) {
                    $this->whileIterators[$iteratorName] = $whileIterator;
                }

                if ($this->getConditionsResult($vars)) {
                    foreach ($vars['action'] as $i => $action) {
                        $return = $this->execSubCode($action, 'action' . (++$i));
                    }
                    $isPostaction = true;
                } else {
                    break;
                }


                $whileIterator++;
            }

            if ($isPostaction && !empty($vars['postaction'])) {
                foreach ($vars['postaction'] as $i => $action) {
                    $return = $this->execSubCode($action, 'postaction' . (++$i));
                }
            }
        }

        return $return;
    }
}