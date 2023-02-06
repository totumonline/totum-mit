<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.08.17
 * Time: 16:37
 */

namespace totum\common\calculates;

use totum\common\controllers\Controller;
use totum\common\errorException;
use totum\common\Lang\RU;
use totum\common\sql\SqlException;
use totum\moduls\Forms\FormsController;
use totum\tableTypes\aTable;

class CalculcateFormat extends Calculate
{
    protected const formats = ['block', 'color', 'bold', 'background', 'italic', 'decoration', 'progress', 'progresscolor', 'icon', 'text', 'comment', 'hideinpanel', 'tab', 'align', 'editbutton', 'hide', 'placeholder', 'showhand', 'expand', 'textasvalue'];
    protected const tableformats = ['buttons', 'topbuttons', 'extbuttons', 'blockadd', 'blockdelete', 'block', 'blockorder', 'background', 'blockduplicate', 'tabletitle', 'rowstitle', 'fieldtitle', 'fieldhide', 'tabletext', 'tablehtml', 'tablecomment', 'browsertitle', 'interlace', 'printbuttons', 'hideadd'];
    protected const rowformats = ['block', 'blockdelete', 'blockorder', 'blockduplicate', 'color', 'bold', 'background', 'italic', 'decoration', 'rowcomment'];
    protected const floatFormat = ['fill', 'glue', 'maxheight', 'maxwidth', 'nextline', 'blocknum', 'height', 'breakwidth'];

    protected array $startSections = [];
    protected array $startPanelSections = [];
    protected array $formatArray = [];

    public function __construct($code)
    {
        parent::__construct($code);

        foreach ($this->code as $k => $v) {
            if (preg_match('/^f[\d]+=$/', $k)) {
                $this->startSections[$k] = $v;
                unset($this->code[$k]);
            } elseif (preg_match('/^p[\d]+=$/', $k)) {
                $this->startPanelSections[$k] = $v;
                unset($this->code[$k]);
            }
        }
        ksort($this->startSections);
        ksort($this->startPanelSections);
    }

    protected function formStartSections()
    {
        foreach ($this->code as $k => $v) {
            if (preg_match('/^(p|f)[\d]+=$/', $k, $matches)) {
                $this->startSections[$matches[1]][$k] = $v;
                unset($this->code[$k]);
            }
        }
        foreach ($this->startSections as &$v) {
            uksort(
                $v,
                function ($a, $b) {
                    $a = str_replace('=', '', $a);
                    $b = str_replace('=', '', $b);
                    return $a <=> $b;
                }
            );
        }
    }

    protected function funcSetRowsOrder($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], ['condition', 'ids'])) {
            if ($this->getConditionsResult($params)) {
                if (key_exists('ids', $params) && is_array($params['ids'] = $this->execSubCode($params['ids'],
                        'ids'))) {
                    foreach ($params['ids'] as &$id) {
                        $id = (int)$id;
                    }
                    unset($id);
                    $this->formatArray['order'] = $params['ids'];
                }
            }
        }
    }

    protected function funcSetRowsHide($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], ['condition', 'ids'])) {
            if ($this->getConditionsResult($params)) {
                if (key_exists('ids', $params) && is_array($params['ids'] = $this->execSubCode($params['ids'],
                        'ids'))) {
                    $this->formatArray['hideRows'] = $params['ids'];
                }
            }
        }
    }

    protected function funcSetRowsShow($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], ['condition', 'ids'])) {
            if ($this->getConditionsResult($params)) {
                if (key_exists('ids', $params) && is_array($params['ids'] = $this->execSubCode($params['ids'],
                        'ids'))) {
                    $this->formatArray['showRows'] = $params['ids'];
                }
            }
        }
    }

    public function funcSetFloatFormat($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], static::floatFormat))) {
            if ($this->getConditionsResult($params)) {
                foreach (static::floatFormat as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                    }
                }
            }
        }
    }

    public function getFormat($fieldName, $row, $tbl, aTable $table, $Vars = []): array
    {
        return $this->__getFormat($fieldName, $row, $tbl, $table, $Vars, 'f');
    }

    protected function funcPanelHtml($paramsIn): ?array
    {
        if ($params = $this->getParamsArray($paramsIn, ['button'], ['html', 'condition'])) {
            if ($this->getConditionsResult($params)) {
                $params = $this->getParamsArray($paramsIn, [], ['condition']);
                return ['type' => 'html', 'value' => $params['html']];
            }
        }
        return null;
    }

    protected function funcPanelImg(string $paramsIn): ?array
    {
        if ($params = $this->getParamsArray($paramsIn, ['button'], ['img', 'condition'])) {
            if ($this->getConditionsResult($params)) {
                $params = $this->getParamsArray($paramsIn, [], ['condition']);
                return ['type' => 'img', 'value' => $params['img']];
            }
        }
        return null;
    }

    protected function funcPanelButtons(string $paramsIn): ?array
    {
        if ($params = $this->getParamsArray($paramsIn, ['button'], ['buttons', 'button', 'refresh', 'condition'])) {
            if ($this->getConditionsResult($params)) {
                $params = $this->getParamsArray($paramsIn, ['button'], ['condition']);

                $values = array_merge($params['button'] ?? [], $params['buttons'] ?? []);
                if (key_exists('refresh', $params)) {
                    foreach ($values as &$btn) {
                        $btn['refresh'] = $btn['refresh'] ?? $params['refresh'];
                    }
                    unset($btn);
                }
                return ['type' => 'buttons', 'value' => $values];
            }
        }
        return null;
    }

    protected function funcPanelButton(string $paramsIn): ?array
    {
        if ($params = $this->getParamsArray($paramsIn,
            ['var'],
            ['text', 'code', 'icon', 'background', 'vars', 'refresh', 'condition', 'var'])) {
            if ($this->getConditionsResult($params)) {
                $params = $this->getParamsArray($paramsIn, ['var'], ['condition'], ['var']);

                $this->__checkNotEmptyParams($params, ['code']);
                $this->__checkNotArrayParams($params, ['text', 'code', 'icon', 'background', 'refresh']);

                $values = [array_intersect_key($params,
                    array_flip(['text', 'code', 'icon', 'background', 'vars', 'refresh']))];
                if(!empty($params['var'])){
                    foreach ($params['var'] as $v){
                        $values[0]['vars'][$v['field']]=$v['value'];
                    }
                }
                return ['type' => 'buttons', 'value' => $values];
            }
        }
        return null;
    }

    public function getPanelFormat($fieldName, $row, $tbl, aTable $table, $Vars = []): array
    {
        $result = $this->__getFormat($fieldName, $row, $tbl, $table, $Vars, 'p');

        $buttons = [];
        foreach ($result as &$r) {
            if ($r['type'] === 'buttons') {
                foreach ($r['value'] as $k => $button) {
                    $button['ind'] = (count($buttons) + 1) . '_' . md5(rand(1, 10000));

                    $button['id'] = $row['id'] ?? null;
                    $button['field'] = $fieldName;

                    $buttons[] = $button;

                    unset($button['code']);
                    unset($button['vars']);
                    unset($button['id']);
                    unset($button['field']);

                    $r['value'][$k] = $button;
                }
            }
            unset($r);
        }
        if ($buttons) {
            $model = $this->Table->getTotum()->getModel('_tmp_tables', true);

            do {
                $hash = md5(microtime(true) . '__panelbuttons_' . mt_srand());
                $key = ['table_name' => '_panelbuttons', 'user_id' => $this->Table->getTotum()->getUser()->getId(), 'hash' => $hash];
            } while ($model->executePrepared(true, $key, 'user_id', null, '0,1')->fetchColumn());

            $vars = array_merge(
                ['tbl' => json_encode(
                    $buttons,
                    JSON_UNESCAPED_UNICODE
                ),
                    'touched' => date('Y-m-d H:i')],
                $key
            );
            $model->insertPrepared(
                $vars,
                false
            );
        }
        return ['rows' => $result, 'hash' => $hash ?? null];
    }

    protected function __getFormat($fieldName, $row, $tbl, aTable $table, $Vars, $sectionPart): array
    {
        $this->formatArray = [];
        $this->fixedCodeVars = [];


        $this->Table = $table;
        $this->newLog = ['text' => $this->translate('Format sections'), 'children' => []];
        $this->newLogParent = &$this->newLog;
        $this->vars = $Vars;

        $this->varName = $fieldName;

        $this->row = $row;
        $this->tbl = $tbl;

        $result = [];

        try {
            if (empty($this->startSections) || !key_exists($sectionPart, $this->startSections)) {
                return [];
            }
            switch ($sectionPart) {
                case 'f':
                    foreach ($this->startSections[$sectionPart] as $k => $v) {
                        $this->execSubCode($v, $k);
                    }
                    $result = $this->formatArray;

                    break;
                case 'p':
                    $result = [];
                    foreach ($this->startSections[$sectionPart] as $k => $v) {
                        $r = $this->execSubCode($v, $k);
                        if (is_array($r)) {
                            if (key_exists('type', $r) && in_array($r['type'], ['text', 'html', 'buttons', 'img'])) {
                                $result[] = ['type' => $r['type'], 'value' => $r['value']];
                            }
                        } elseif (!empty($r)) {
                            $result[] = ['type' => 'text', 'value' => $r];
                        }
                    }
                    break;
            }

            $this->newLog['text'] .= ': ' . json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (SqlException $e) {
            $this->error = $this->translate('Database error: [[%s]]', $e->getMessage());
            $this->newLog['text'] .= ': ' . $this->error;


            throw $e;
        } catch (errorException $e) {
            $this->error = $e->getMessage();
            $this->newLog['text'] .= ': ' . $this->error;
        }

        return $result;
    }


    protected function funcExec(string $params): mixed
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            if (!empty($code = $params['code'] ?? $params['kod'])) {

                if (preg_match('/^[a-z_0-9]{3,}$/', $code) && key_exists($code, $this->Table->getFields())) {
                    $code = $this->Table->getFields()[$code]['format'] ?? '';
                }

                $CA = new static($code);
                try {
                    $Vars = [];
                    foreach ($params['var'] ?? [] as $v) {
                        $Vars = array_merge($Vars, $this->getExecParamVal($v, 'var'));
                    }
                    $r = $CA->getFormat($this->varName, $this->row, $this->tbl, $this->Table, $Vars);
                    $this->newLogParent['children'][] = $CA->getLogVar();

                    return $this->formatArray = array_merge($this->formatArray, $r);
                } catch (errorException $e) {
                    $this->newLogParent['children'][] = $CA->getLogVar();
                    throw $e;
                }
            }
        }
        return null;
    }

    protected function funcSetFormat($params)
    {
        if ($params = $this->getParamsArray(
            $params,
            ['condition', 'hide'],
            array_merge(['condition'], static::formats)
        )) {
            if ($this->getConditionsResult($params)) {
                if ($params['hideinpanel'] ?? false) {
                    $params['hide']['panel'] = true;
                }

                foreach (static::formats as $format) {
                    if (key_exists($format, $params)) {
                        switch ($format) {
                            case 'hide':
                                foreach ($params['hide'] as $_str) {
                                    $_strSplit = preg_split('/\s*=\s*/', $_str);

                                    if (count($_strSplit) !== 2) {
                                        throw new errorException($this->translate('The [[%s]] parameter must contain 2 elements.',
                                            'hide'));
                                    }
                                    $this->formatArray[$format][$this->__getValue($this->getCodes($_strSplit[0])[0])] = $this->__getValue($this->getCodes($_strSplit[1])[0]);
                                }
                                break;
                            default:
                                $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                        }
                    }
                }
            }
        }
    }

    protected function funcSetTableFormat($params)
    {
        if ($params = $this->getParamsArray(
            $params,
            ['fieldhide', 'fieldtitle'],
            array_merge(['condition'], static::tableformats)
        )) {

            if ($this->getConditionsResult($params)) {
                foreach (static::tableformats as $format) {
                    if (key_exists($format, $params)) {
                        if (in_array($format, ['fieldhide', 'fieldtitle'])) {
                            foreach ($params[$format] as $fieldparam) {
                                $fieldparam = $this->getCodes($fieldparam);
                                if (count($fieldparam) !== 3 || $fieldparam['comparison'] !== '=') {
                                    throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                                        $fieldparam));
                                }
                                $fieldname = $this->__getValue($fieldparam[0]);
                                $fieldvalue = $this->__getValue($fieldparam[1]);

                                $this->formatArray[$format][$fieldname] = $fieldvalue;
                            }
                        } else {
                            $this->formatArray[$format] = is_string($params[$format]) ? $this->__getValue($this->getCodes($params[$format])[0]) : $params[$format];

                        }
                    }
                }
            }
        }

    }

    protected function funcSetFormFieldFormat($params): ?array
    {
        if ($this->Table->getTotum()->getSpecialInterface() !== 'form') {
            return [];
        }

        $formats = ['hidden', 'width', 'height', 'classes'];
        if ($params = $this->getParamsArray(
            $params,
            ['condition'],
            array_merge(['condition', 'section'], $formats)
        )) {

            if ($this->getConditionsResult($params)) {
                foreach ($formats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = is_string($params[$format]) ? $this->__getValue($this->getCodes($params[$format])[0]) : $params[$format];
                    }
                }
            }
        }
        return null;
    }

    protected function funcSetFormSectionsFormat($params): ?array
    {
        if ($this->Table->getTotum()->getSpecialInterface() !== 'form') {
            return [];
        }

        $formats = ['status'];
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], $formats))) {

            $this->__checkNotEmptyParams($params, ['section']);
            $this->__checkNotArrayParams($params, ['section']);

            if (!preg_match(
                '/^[a-z_0-9]+$/',
                $params['section']
            )) {
                throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].',
                    ['section', 'string - [a-z_0-9]+']));
            }

            if ($this->getConditionsResult($params)) {
                foreach ($formats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray['sections'][$params['section']][$format] = is_string($params[$format]) ? $this->__getValue($this->getCodes($params[$format])[0]) : $params[$format];
                    }
                }
            }
        }
        return null;
    }

    protected function funcSetRowFormat($params)
    {
        if ($params = $this->getParamsArray(
            $params,
            ['condition'],
            array_merge(['condition'], static::rowformats)
        )) {
            if ($this->getConditionsResult($params)) {
                foreach (static::rowformats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                    }
                }
            }
        }
    }

}
