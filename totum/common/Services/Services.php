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
    protected ?\PDOStatement $getStWithValuesMark = null;

    public static function init(ConfParent $Conf): Services
    {
        if (!static::$Services) {
            static::$Services = new static($Conf->getSql(false), $Conf);
        }
        return static::$Services;
    }

    protected function __construct(protected Sql $sql, protected ConfParent $Config)
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

    protected function getLinkData($value)
    {
        $context = stream_context_create(
            [
                'http' => [
                    'header' => "User-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                    'method' => 'GET',
                ],
                'ssl' => [
                    'verify_peer' => $this->Config->isCheckSsl(),
                    'verify_peer_name' => $this->Config->isCheckSsl(),
                ],
            ]
        );

        if (empty($value['link']) || !($file = @file_get_contents($value['link'], true, $context))) {
            if (!empty($value['error'])) {
                throw new errorException('Generator error: ' . $value['error']);
            }
            if (!empty($value['link'])) {
                throw new errorException('Wrong data from service server: ' . $http_response_header);
            } else {
                throw new errorException('Unknown error');
            }
        }
        return $file;
    }

    public function waitVarValues(array $varNames, bool $getLinks = true, string $mark = 'done'): array
    {
        try {

            $executes = [];
            $notDoneHashes = array_flip($varNames);
            $st = $this->getVarValuePreparedWithMarks();
            while (count($notDoneHashes)) {
                $st->execute([json_encode(array_keys($notDoneHashes)), $mark]);
                $nameValues = $st->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($nameValues as $row) {
                    $value = json_decode($row['value'], true);
                    if ($getLinks) {
                        $executes[$row['name']] = $this->getLinkData($value);
                    } else {
                        $executes[$row['name']] = $value;
                    }
                    unset($notDoneHashes[$row['name']]);
                }
                usleep(0.02 * 10 ^ 6);
            }
            return $executes;

        } catch
        (\PDOException $exception) {
            if ($exception->getCode() === '42P01') {
                $this->createServicesTable();
                return $this->waitVarValues($varNames, $getLinks, $mark);
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
    function getVarValuePreparedWithMarks(): \PDOStatement
    {
        return $this->getStWithValuesMark ?? ($this->getStWithValuesMark = $this->sql->getPrepared('select name, value from _services_vars where name IN (SELECT jsonb_array_elements_text(?)) AND mark=?'));
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
            if ($timeout && $timer >= microtime(true)) {
                throw new errorException('timeout');
            }
            usleep(200);
        }
        return $data;
    }
}