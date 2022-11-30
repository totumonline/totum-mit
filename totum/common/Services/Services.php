<?php

namespace totum\common\Services;

use totum\common\configs\ConfParent;
use totum\common\errorException;
use totum\common\sql\Sql;
use totum\common\sql\SqlException;

class Services implements ServicesVarsInterface
{
    protected static ?Services $Services = null;
    protected ?\PDOStatement $insertExpiredSt = null;

    protected ?\PDOStatement $insertSt = null;
    protected ?\PDOStatement $setSt = null;
    protected ?\PDOStatement $setStWithMark = null;
    protected ?\PDOStatement $getSt = null;
    protected ?\PDOStatement $getStWithMark = null;

    public static function init(ConfParent $Conf): Services
    {
        if (!static::$Services) {
            static::$Services = new static($Conf->getSql(false));
        }
        return static::$Services;
    }

    protected function __construct(protected Sql $sql)
    {

    }

    public function getNewVarnameHash(int $expired = null): string
    {
        do {
            $hash = bin2hex(random_bytes(50));
        } while (!$this->insertName($hash, $expired));
        return $hash;
    }

    public function getVarValue(string $varName, string $mark = null)
    {
        try {
            if ($mark) {
                $st = $this->getVarValuePreparedWithMark();
                $st->execute([$varName, $mark]);
            } else {
                $st = $this->getVarValuePrepared();
                $st->execute([$varName]);
            }
            return json_decode($st->fetchColumn(), true);
        } catch
        (\PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                $this->createServicesTable();
                return $this->getVarValue($varName, $mark);
            } else {
                throw new SqlException($exception->getMessage());
            }
        }
    }

    public function setVarValue(string $varName, $value, string $mark = null): void
    {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($mark) {
            $st = $this->setPreparedWithMark();
            $st->execute([$value, $mark, $varName,]);
        } else {
            $st = $this->setPrepared();
            $st->execute([$value, $varName]);
        }
    }

    public function insertName(string $varName, int $expired = null): bool
    {
        try {
            if ($expired) {
                $st = $this->insertPreparedWithExpire();
                $st->execute([$varName, date('Y-m-d H:i:s', time() + $expired)]);
            } else {
                $st = $this->insertPrepared();
                $st->execute([$varName]);
            }
            return true;
        } catch (\PDOException $e) {
            if ($e->getCode() == '23505') {
                return false;
            }
            throw $e;
        }

    }

    protected
    function getVarValuePrepared(): \PDOStatement
    {
        return $this->getSt ?? ($this->getSt = $this->sql->getPrepared('select value from _services_vars where name=?'));
    }

    protected
    function getVarValuePreparedWithMark(): \PDOStatement
    {
        return $this->getStWithMark ?? ($this->getStWithMark = $this->sql->getPrepared('select value from _services_vars where name=? and mark=?'));
    }

    protected
    function setPrepared(): \PDOStatement
    {
        return $this->setSt ?? ($this->setSt = $this->sql->getPrepared('update _services_vars set value=(?) where name=?'));
    }

    protected
    function setPreparedWithMark(): \PDOStatement
    {
        return $this->setStWithMark ?? ($this->setStWithMark = $this->sql->getPrepared('update _services_vars set value=?, mark=? where name=?'));
    }

    protected
    function insertPrepared(): \PDOStatement
    {
        return $this->insertSt ?? ($this->insertSt = $this->sql->getPrepared('insert into _services_vars (name) values (?)'));
    }

    protected
    function insertPreparedWithExpire(): \PDOStatement
    {
        return $this->insertExpiredSt ?? ($this->insertExpiredSt = $this->sql->getPrepared('insert into _services_vars (name, expire) values (?, ?)'));
    }


    public
    function createServicesTable()
    {
        $this->sql->exec(
            <<<SQL
create table "_services_vars"
(
    name     text not null,
    value     text,
    mark     text,
    expire   timestamp without time zone
);
create UNIQUE INDEX _services_vars_name_index on _services_vars (name);
SQL
            ,
            null,
            true);
    }

    public function waitVarValue(string $varName, $timeout = 10)
    {
        $timer = microtime(true) + $timeout;
        while (($data = $this->getVarValue($varName, 'done')) === null) {
            if ($timer >= microtime(true)) {
                throw new errorException('timeout');
            }
        }
        return $data;
    }
}