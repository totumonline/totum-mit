<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\errorException;
use totum\config\Conf;

class SchemaDecode extends Command
{
    protected function configure()
    {
        $this->setName('decode')
            ->setDescription('Decode old schemas from Config::getSchemas')
            ->addOption(
                'schemas',
                's',
                InputOption::VALUE_OPTIONAL,
                'Pass the comma separated schemas names if you don\'t want to do it by one',
                ''
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Without confirmation in schemas',
                false
            );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $config = new Conf();

        if ($input->getOption('schemas')) {
            $withQuestion = false;
            $schemas = explode(",", $input->getOption('schemas'));

            if (!is_array($schemas) && count($schemas)) {
                $output->writeln("Input schemas comma separated or not use <schemas> option");
                return 0;
            }
            $count = count($schemas);
        } else {
            $schemas = array_unique(array_values($config::getSchemas()));
            $count = count($schemas);
            $output->writeln("Найдено $count схем");
            $withQuestion = $input->getOption('force') === false;
        }


        for ($i = 0; $i < $count; $i++) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion("Decode schema << {$schemas[$i]} >>?", false, '/^(y)/i');

            if (!$withQuestion || $helper->ask($input, $output, $question)) {
                $this->decode($config, $schemas[$i], $output);
            }
        }
        return 0;
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
