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

class SchemaAdd extends Command
{
    protected function configure()
    {

        $this->setName('schema-add')
            ->setDescription('Add and install new schema')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Enter schema name')
            ->addArgument('host', InputOption::VALUE_REQUIRED, 'Enter schema host')
            ->addArgument('user_login', InputOption::VALUE_REQUIRED, 'Enter totum admin login', 'admin')
            ->addArgument('user_pass', InputOption::VALUE_REQUIRED, 'Enter totum admin password', '1111');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf=new Conf('dev');
        $Conf->setHostSchema($input->getArgument('host'), $input->getArgument('name'));

        $TotumInstall=new TotumInstall($Conf, new User(['login' => 'service', 'roles' => ["1"], 'id' => 1], $Conf), $output);

        $confs=[];
        $confs['schema_exists'] = false;
        $confs['user_login'] = $input->getArgument('user_login');
        $confs['user_pass'] = $input->getArgument('user_pass');


        $TotumInstall->createSchema($confs, function($file){
            return dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'moduls' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . $file;
        });



        $output->writeln('save Conf.php');

        $ConfFile= (new \ReflectionClass(Conf::class))->getFileName();
        $ConfFileContent=file_get_contents($ConfFile);

        if(!preg_match('~\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return([^$]*)\}[^$]*/\*\*\*getSchemasEnd\*\*\*/~', $ConfFileContent, $matches)){
            throw new \Exception('Format of file not correct. Can\'t replace function getSchemas');
        }
        eval("\$schemas={$matches[1]}");
        $schemas[$input->getArgument('host')]=$input->getArgument('name');
        $ConfFileContent= preg_replace('~(\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getSchemasEnd\*\*\*/)~', '$1'.var_export($schemas, 1).';$3', $ConfFileContent);
        copy($ConfFile, $ConfFile.'_old');
        file_put_contents($ConfFile, $ConfFileContent);


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
                    $stmtUpdate->execute([json_encode(["v"=>$crypt($dataSrc['Field'], 'DECRYPT')['FieldParams']]), $row['id']]);
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
