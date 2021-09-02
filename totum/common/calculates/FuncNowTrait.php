<?php

namespace totum\common\calculates;

use totum\common\errorException;

trait FuncNowTrait
{

    protected function funcNowCycleId():int
    {
        if ($this->Table->getTableRow()['type'] != 'calcs') {
            throw new errorException($this->translate('[[%s]] is available only for the calculation table in the cycle.',
                'NowCycleId'));
        }
        return $this->Table->getCycle()->getId();
    }

    protected function funcNowField(): string
    {
        if (empty($this->varName)) {
            throw new errorException($this->translate('There is no NowField enabled in this type of code. We\'ll fix it - write us.'));
        }
        return $this->varName;
    }

    protected function funcNowFieldValue()
    {
        if (empty($this->varName)) {
            throw new errorException($this->translate('There is no NowField enabled in this type of code. We\'ll fix it - write us.'));
        }

        return $this->getParam('#' . $this->varName, ['type' => 'param', 'param' => '#' . $this->varName]);
    }

    protected function funcNowRoles(): ?array
    {
        return $this->Table->getTotum()->getUser()->getRoles();
    }

    protected function funcNowSchema(): string
    {
        return $this->Table->getTotum()->getConfig()->getSchema();
    }

    protected function funcNowTableHash(): ?string
    {
        if ($this->Table->getTableRow()['type'] != 'tmp') {
            throw new errorException($this->translate('For temporary tables only.'));
        }
        return $this->Table->getTableRow()['sess_hash'];
    }

    protected function funcNowTableId(): int
    {
        return $this->Table->getTableRow()['id'];
    }

    protected function funcNowTableName(): string
    {
        return $this->Table->getTableRow()['name'];
    }

    protected function funcNowTableUpdatedDt(): ?string
    {
        return json_decode($this->Table->getSavedUpdated(), true)['dt'];
    }

    protected function funcNowUser(): string
    {
        return strval($this->Table->getTotum()->getUser()->getId());
    }
}