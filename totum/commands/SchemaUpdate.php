<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\errorException;
use totum\common\TotumInstall;
use totum\common\User;
use totum\config\Conf;
use totum\config\Conf2;

class SchemaUpdate extends Command
{
    protected function configure()
    {

        $this->setName('schema-update')
            ->setDescription('Update schema')
            ->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name', '')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Enter source name', 'totum_'.(new Conf())->getLang())
            ->addArgument('file', InputOption::VALUE_REQUIRED, 'Enter schema file', 'sys_update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf('dev');
        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }
        $sourceName = $input->getArgument('name');

        $file = $input->getArgument('file');

        if ($file === 'sys_update')
            $file = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'moduls' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'start_'.$Conf->getLang().'.json.gz';

        $TotumInstall = new TotumInstall($Conf,
            new User(['login' => 'service', 'roles' => ["1"], 'id' => 1], $Conf),
            $output);

        if (!is_file($file))
            throw new errorException('Файл не найден');
        if (!($cont = file_get_contents($file))) {
            throw new errorException('Файл пуст');
        }
        if (!($cont = gzdecode($cont))) {
            throw new errorException('Файл не gzip');
        }
        if (!($cont = json_decode($cont, true))) {
            throw new errorException('Файл не json');
        }

        $matches = $TotumInstall->getTotum()->getTable('ttm__updates')->getTbl()['params']['h_matches']['v'][$sourceName] ?? [];
        $cont = TotumInstall::applyMatches($cont, $matches);

        $TotumInstall->updateSchema($cont, true, $sourceName);
    }

    private function decode(Conf $config, string $schemaName, $output)
    {
        $sql = $config->getSql(false);
        $sql->exec('set search_path to "' . $schemaName . '"');
        $sql->transactionStart();

        $crypt = function ($string, $direction) {
            if (!in_array(
                    $cipher = "AES-128-CBC",
                    openssl_get_cipher_methods()
                ) && !in_array(
                    $cipher = strtolower($cipher),
                    openssl_get_cipher_methods()
                )) {
                throw new \Exception('Метод шифрования ' . $cipher . ' не поддержвается вашим PHP');
            }
            $options = OPENSSL_RAW_DATA;
            $key = "b7fa3a71e992becb9a530d01c807710edfeae334762c9e5562dbfd7d9965baebdf6822fc4e297c288cae10e99ef4cd1c196933bfad1fdea5f47ff2d9c734e098";

            switch ($direction) {
                case 'CRYPT':
                    if (!is_array($string)) {
                        throw new errorException('Строка вместо массива');
                    }
                    $string = json_encode($string, true);

                    $ivlen = openssl_cipher_iv_length($cipher);
                    $iv = openssl_random_pseudo_bytes($ivlen);
                    $ciphertext_raw = openssl_encrypt($string, $cipher, $key, $options, $iv);
                    $hmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
                    $str = $iv . $hmac . $ciphertext_raw;
                    return base64_encode($str);
                    break;
                case 'DECRYPT':

                    $c = base64_decode($string);
                    $ivlen = openssl_cipher_iv_length($cipher);
                    $iv = substr($c, 0, $ivlen);
                    $hmac = substr($c, $ivlen, $sha2len = 32);
                    $ciphertext_raw = substr($c, $ivlen + $sha2len);
                    $plaintext = openssl_decrypt($ciphertext_raw, $cipher, $key, $options, $iv);
                    $calcmac = hash_hmac('sha256', $ciphertext_raw, $key, $as_binary = true);
                    if (hash_equals($hmac, $calcmac)) {
                        return json_decode($plaintext, true);
                    }
                    break;
            }
        };

        $stmt = $sql->getPrepared('select id, data_src->>\'v\' as data_src from tables_fields');
        if ($stmt->execute()) {
            $stmtUpdate = $sql->getPrepared('update tables_fields set data_src=? where id=?');

            foreach ($stmt as $row) {
                $dataSrc = json_decode($row['data_src'], true);
                if (key_exists('Field', $dataSrc) && key_exists('hash', $dataSrc)) {
                    $stmtUpdate->execute([json_encode(["v" => $crypt($dataSrc['Field'],
                        'DECRYPT')['FieldParams']]), $row['id']]);
                } else {
                    $sql->transactionRollBack();
                    $output->writeln("Schema << {$schemaName} >> already decoded");
                    return;
                }
            }
        }
        $sql->transactionCommit();
        $output->writeln("Schema << {$schemaName} >> decoded");
    }
}
