<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.08.17
 * Time: 16:37
 */

namespace totum\common\calculates;

use totum\common\Controller;
use totum\common\errorException;
use totum\common\sql\SqlException;
use totum\moduls\Forms\FormsController;
use totum\tableTypes\aTable;

class CalculcateFormat extends Calculate
{
    const formats = ['block', 'color', 'bold', 'background', 'italic', 'decoration', 'progress', 'progresscolor', 'icon', 'text', 'comment', 'hideinpanel', 'tab', 'align', 'editbutton', 'hide', 'placeholder'];
    const tableformats = ['blockadd', 'blockdelete', 'block', 'blockorder', 'background', 'blockduplicate', 'tabletitle', 'rowstitle', 'fieldtitle', 'tabletext', 'tablecomment'];
    const rowformats = ['block', 'blockdelete', 'blockorder', 'blockduplicate', 'color', 'bold', 'background', 'italic', 'decoration'];
    const floatFormat = ["fill", "glue", "maxheight", "maxwidth", "nextline", "blocknum", "height", "breakwidth"];
    protected $startSections = [];
    protected $startPanelSections = [];
    protected $formatArray = [];

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

    public function funcSetFloatFormat($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], static::floatFormat))) {
            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            }

            if ($conditionTest) {
                foreach (static::floatFormat as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                    }
                }
            }
        }
    }

    public function getFormat($fieldName, $row, $tbl, aTable $table, $Vars = [])
    {
        return $this->__getFormat($fieldName, $row, $tbl, $table, $Vars, $this->startSections, 'f1');
    }

    protected function funcPanelHtml($params)
    {
        $params = $this->getParamsArray($params);
        return ['type' => 'html', 'value' => $params['html']];
    }

    protected function funcPanelImg($params)
    {
        $params = $this->getParamsArray($params);
        return ['type' => 'img', 'value' => $params['img']];
    }

    protected function funcPanelButtons($params)
    {
        $params = $this->getParamsArray($params, ['button']);
        $values = array_merge($params['button'] ?? [], $params['buttons'] ?? []);
        $btns = ['type' => 'buttons', 'value' => $values];
        return $btns;
    }

    public function getPanelFormat($fieldName, $row, $tbl, aTable $table, $Vars = [])
    {
        $result = $this->__getFormat($fieldName, $row, $tbl, $table, $Vars, $this->startPanelSections, 'p1');

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
            } while ($model->executePrepared(true, $key, 'user_id', null, '0,1')->fetchColumn(0));

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

    protected function __getFormat($fieldName, $row, $tbl, aTable $table, $Vars, $startSections, $startSectionName)
    {
        $this->formatArray = [];
        $this->fixedCodeVars = [];

        $this->newLog = ['text' => 'Секции форматирования', 'children' => []];
        $this->newLogParent = &$this->newLog;
        $this->vars = $Vars;

        $this->varName = $fieldName;

        $this->row = $row;
        $this->tbl = $tbl;
        $this->Table = $table;
        $result = [];

        try {
            if (empty($startSections)) {
                return [];
            }
            switch ($startSectionName) {
                case 'f1':
                    foreach ($startSections as $k => $v) {
                        $this->execSubCode($v, $k);
                    }
                    $result = $this->formatArray;

                    break;
                case 'p1':
                    $result = [];
                    foreach ($startSections as $k => $v) {
                        $r = $this->execSubCode($v, $k);
                        if (is_array($r)) {
                            if (key_exists('type', $r) && in_array($r['type'], ['text', 'html', 'buttons', 'img'])) {
                                $result[] = ['type' => $r['type'], 'value' => $r['value']];
                            }
                        } else {
                            $result[] = ['type' => 'text', 'value' => $r];
                        }
                    }
                    break;
            }

            $this->newLog['text'] .= ': ' . json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (SqlException $e) {
            $this->error = 'Ошибка базы данных при обработке кода [[' . $e->getMessage() . ']]';
            $this->newLog['text'] .= ': ' . $this->error;


            throw $e;
        } catch (errorException $e) {
            $this->error = $e->getMessage();
            $this->newLog['text'] .= ': ' . $this->error;
        }

        return $result;
    }


    protected function funcExec($params)
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            if (!empty($params['code'] ?? $params['kod'])) {
                $CA = new static($params['code'] ?? $params['kod']);
                try {
                    $Vars = [];
                    foreach ($params['var'] ?? [] as $v) {
                        $Vars = array_merge($Vars, $this->getExecParamVal($v));
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
    }

    protected function funcSetFormat($params)
    {
        if ($params = $this->getParamsArray(
            $params,
            ['condition', 'hide'],
            array_merge(['condition'], static::formats)
        )) {
            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            }

            if ($conditionTest) {
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
                                        throw new errorException('Ошибка форматирования параметра [[hide]]');
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
            ['condition', 'fieldtitle'],
            array_merge(['condition'], static::tableformats)
        )) {
            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            };
            if ($conditionTest) {
                foreach (static::tableformats as $format) {
                    if (key_exists($format, $params)) {
                        if ($format === 'fieldtitle') {
                            foreach ($params[$format] as $fieldparam) {
                                $fieldparam = $this->getCodes($fieldparam);
                                if (count($fieldparam) !== 3 || $fieldparam['comparison'] !== '=') {
                                    throw new errorException('Неверное оформление параметра fieldtitle');
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

    protected function funcSetFormFieldFormat($params)
    {
        if (get_class(Controller::getActiveController()) !== FormsController::class) {
            return [];
        }

        $formats = ['hidden', 'width', 'height', 'classes'];
        if ($params = $this->getParamsArray(
            $params,
            ['condition'],
            array_merge(['condition', 'section'], $formats)
        )) {
            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            }
            if ($conditionTest) {
                foreach ($formats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = is_string($params[$format]) ? $this->__getValue($this->getCodes($params[$format])[0]) : $params[$format];
                    }
                }
            }
        }
    }

    protected function funcSetFormSectionsFormat($params)
    {
        if (get_class(Controller::getActiveController()) !== FormsController::class) {
            return [];
        }

        $formats = ['status'];
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], $formats))) {
            if (empty($params['section'])) {
                throw new errorException('Укажите section');
            }
            if (!preg_match(
                '/^[a-z_0-9]+$/',
                $params['section']
            )) {
                throw new errorException('Неверный формат параметра section (ожидаются цифны, строчные английские буквы и _)');
            }

            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            }
            if ($conditionTest) {
                foreach ($formats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray['sections'][$params['section']][$format] = is_string($params[$format]) ? $this->__getValue($this->getCodes($params[$format])[0]) : $params[$format];
                    }
                }
            }
        }
    }

    protected function funcSetRowFormat($params)
    {
        if ($params = $this->getParamsArray(
            $params,
            ['condition'],
            array_merge(['condition'], static::rowformats)
        )) {
            $conditionTest = true;
            if (!empty($params['condition'])) {
                foreach ($params['condition'] as $i => $c) {
                    $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                    if (!is_bool($condition)) {
                        throw new errorException('Параметр [[condition' . (1 + $i) . ']] вернул не true/false');
                    }
                    if (!$condition) {
                        $conditionTest = false;
                        break;
                    }
                }
            }

            if ($conditionTest) {
                foreach (static::rowformats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                    }
                }
            }
        }
    }
}
