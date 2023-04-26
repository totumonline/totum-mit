<?php


namespace totum\fieldTypes;

use totum\common\NoValueField;
use totum\common\calculates\Calculate;
use totum\tableTypes\aTable;

class Chart extends NoValueField
{
    /**
     * @var Calculate
     */
    protected $chartFormat;

    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);

        if (!empty($this->data['codeChart']) && $this->data['codeChart'] !== '=:') {
            $this->chartFormat = new Calculate($this->data['codeChart']);
        }
    }

    public function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {
        return ['v' => null];
    }

    public function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {
        return ['v' => null];
    }

    public function addFormat(&$valArray, $row, $tbl, $pageIds, $vars = [])
    {
        parent::addFormat($valArray, $row, $tbl, $pageIds, $vars);
        if ($this->chartFormat) {
            $Log = $this->table->calcLog(['field' => $this->data['name'], 'cType' => 'format', 'itemId' => $row['id'] ?? null]);
            if ($format = $this->chartFormat->exec($this->data, [], $row, $row, $tbl, $tbl, $this->table, ['rows'=>$this->table->getRowsForFormat($pageIds)])) {
                $valArray['ch'] = $format;
            }
            $this->table->calcLog($Log, 'result', $valArray['ch']);
        }
    }
}
