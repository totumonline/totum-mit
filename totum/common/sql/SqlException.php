<?php


namespace totum\common\sql;

use totum\common\criticalErrorException;

class SqlException extends criticalErrorException
{
    protected string $sqlErrorCode;

    public function addSqlErrorCode(string $errorCode)
    {
        $this->sqlErrorCode=$errorCode;
    }

    /**
     * @return string
     */
    public function getSqlErrorCode(): string
    {
        return $this->sqlErrorCode;
    }
}
