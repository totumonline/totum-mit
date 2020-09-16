<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 2018-12-05
 * Time: 11:26
 */

namespace totum\common\logs;

use Psr\Log\AbstractLogger;
use totum\common\criticalErrorException;
use totum\common\sql\SqlException;
use totum\common\Totum;

class OutersLog extends AbstractLogger
{
    protected $PDO;
    protected $userId;

    public function __construct(Totum $Totum, $userId)
    {
        $this->PDO = $Totum->getConfig()->getSql(false)->getPDO();
        $this->userId = $userId;
    }

    protected function add($category, $type, $data, $afterError = false)
    {
        try {
            $this->PDO->exec(sprintf(
                "insert into _bfl (uid, cat, type, data) values ( %s,%s, %s, %s)",
                $this->userId,
                $this->PDO->quote($category),
                $this->PDO->quote($type),
                $this->PDO->quote(json_encode($data, JSON_UNESCAPED_UNICODE))
            ));


            if (($error = $this->PDO->errorInfo()) && $error[2]) {
                throw new SqlException($error[2]);
            }
        } catch (SqlException $exception) {
            if ($afterError) {
                throw new criticalErrorException($exception->getMessage());
            }

            $this->PDO->exec('CREATE TABLE _bfl(
  dt timestamp NOT NULL default NOW()::timestamp,
  uid bigint,
  cat text,
  type text,
  data jsonb
)');

            $this->add($category, $type, $data, true);
        }
    }

    public function log($level, $message, array $context = [])
    {
        $this->add($message, $level, $context);
    }
}
