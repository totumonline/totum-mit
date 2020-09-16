<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\errorException;
use totum\config\Conf;

class SchemaFileDecode extends Command
{
    protected function configure()
    {
        $this->setName('decode')->addOption(
            'file',
            'f',
            InputOption::VALUE_REQUIRED,
            'path to file',
            false
        );
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($filepath = $input->getOption('file')) {
            if (!is_file($filepath)) {
                throw new \Exception('Файл не найден');
            }

            $data = json_decode(gzdecode(file_get_contents($filepath)), true);
            if (!$data) {
                throw new \Exception('Файл не распознан');
            }
            $output->writeln('Декодирую');
            $this->decode($data, $output);
            $data=json_encode($data, JSON_UNESCAPED_UNICODE);
            rename($filepath, $filepath.'.old');
            file_put_contents($filepath, gzencode($data));
            $output->writeln('done');
        } else {
            throw new \Exception('Файл не задан');
        }
        return 0;
    }

    private function decode(&$data, OutputInterface $output)
    {
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

        foreach ($data as $k => &$v) {
            if ($k === 'data_src') {
                if (key_exists('Field', $v) && key_exists('hash', $v)) {
                    $v = $crypt(
                        $v['Field'],
                        'DECRYPT'
                    )['FieldParams'];
                }
            } elseif (is_array($v)) {
                $this->decode($v, $output);
            }
        }
        unset($v);
    }
}
