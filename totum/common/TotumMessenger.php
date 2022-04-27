<?php

namespace totum\common;

class TotumMessenger
{

    private bool $formatUseRows = false;

    public function formatUseRows(bool $formatUseRows): void
    {
        $this->formatUseRows = $formatUseRows;
    }
    public function isFormatUseRows(): bool
    {
        return $this->formatUseRows;
    }
}