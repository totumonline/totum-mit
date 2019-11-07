<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.08.17
 * Time: 16:37
 */

namespace totum\common;


use totum\tableTypes\aTable;

class CalculcateFormat extends Calculate
{
    static $logClassName="format";

    const formats = ['block', 'color', 'bold', 'background', 'italic', 'decoration', 'progress', 'progresscolor', 'icon', 'text', 'comment', 'hideinpanel', 'tab', 'align'];
    const tableformats = ['blockadd', 'blockdelete', 'block', 'blockorder', 'background', 'blockduplicate'];
    const rowformats = ['block', 'blockdelete', 'blockorder', 'blockduplicate', 'color', 'bold', 'background', 'italic', 'decoration'];
    protected $startSections = [];
    protected $formatArray = [];

    function __construct($code)
    {
        parent::__construct($code);

        foreach ($this->code as $k => $v) {
            if (preg_match('/^f[\d]+=$/', $k)) {
                $this->startSections[$k] = $v;
                unset($this->code[$k]);
            }
        }
        ksort($this->startSections);
    }

    function getFormat($fieldName, $row, $tbl, aTable $table, $Vars=[])
    {
        $dtStart=microtime(true);

        $this->formatArray = [];
        $this->fixedCodeVars = [];

        $this->newLog = ['text'=>'Секции форматирования', 'children'=>[]];
        $this->newLogParent = &$this->newLog;
        $this->vars= $Vars;

        $this->varName=$fieldName;

        $this->row = $row;
        $this->tbl = $tbl;
        $this->aTable = $table;
        try {
            if (empty($this->startSections))
                throw new errorException('Ошибка кода - нет стартовой секции [[f1=]]');
            foreach ($this->startSections as $k => $v) {
                $this->execSubCode($v, $k);
            }

            $this->newLog['text'].=': '.json_encode($this->formatArray, JSON_UNESCAPED_UNICODE);

        } catch (SqlExeption $e) {
            $this->error = 'Ошибка базы данных при обработке кода [[' . $e->getMessage() . ']]';
            $this->newLog['text'].=': '.$this->error;
        } catch (errorException $e) {
            $this->error = $e->getMessage();
            $this->newLog['text'].=': '.$this->error;
        }

        static::$calcLog[$var]['time']=(static::$calcLog[$var=$table->getTableRow()["id"].'/'.$fieldName.'/'.static::$logClassName]['time']??0)+(microtime(true) - $dtStart);
        static::$calcLog[$var]['cnt']=(static::$calcLog[$var]['cnt']??0)+1;


        return $this->formatArray;
    }
    function funcExec($params)
    {
        if ($params = $this->getParamsArray($params, ['var'], ['var'])) {
            if (!empty($params['code']??$params['kod'])) {
                $CA = new static($params['code']??$params['kod']);
                try {
                    $Vars = [];
                    foreach ($params['var']??[] as $v){
                        $Vars=array_merge($Vars, $this->getExecParamVal($v));
                    }
                    $r = $CA->getFormat($this->varName, $this->row, $this->tbl, $this->aTable, $Vars);
                    $this->newLogParent['children'][]=$CA->getLogVar();

                    return $this->formatArray=array_merge($this->formatArray, $r);
                } catch (errorException $e) {
                    $this->newLogParent['children'][]=$CA->getLogVar();
                    throw $e;
                }
            }
        }
    }
    protected function funcSetFormat($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], static::formats))) {

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
                foreach (static::formats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = $this->__getValue($this->getCodes($params[$format])[0]);
                    }
                }
            }
        }
    }
        protected function funcSetTableFormat($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], static::tableformats))) {

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
           ;
            if ($conditionTest) {
                foreach (static::tableformats as $format) {
                    if (key_exists($format, $params)) {
                        $this->formatArray[$format] = is_string($params[$format])?$this->__getValue($this->getCodes($params[$format])[0]):$params[$format];
                    }
                }
            }
        }
    }
    protected function funcSetRowFormat($params)
    {
        if ($params = $this->getParamsArray($params, ['condition'], array_merge(['condition'], static::rowformats))) {

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