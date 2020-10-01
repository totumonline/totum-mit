<?php

namespace totum\commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\errorException;
use totum\common\Totum;
use totum\common\TotumInstall;
use totum\config\Conf;

class Install extends Command
{
    protected function configure()
    {
        $this->setName('install')
            ->setDescription('Install new schema and create Conf.php')
            ->addArgument('lang', InputOption::VALUE_REQUIRED, 'Enter language ('.implode('/', Totum::LANGUAGES).')')
            ->addArgument('multi', InputOption::VALUE_REQUIRED, 'Enter type of install (multi/no-multi)')
            ->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name')
            ->addArgument('admin_email', InputOption::VALUE_REQUIRED, 'Enter admin email', '')
            ->addArgument('totum_host', InputOption::VALUE_REQUIRED, 'Enter totum host')
            ->addArgument('user_login', InputOption::VALUE_REQUIRED, 'Enter totum admin login', 'admin')
            ->addArgument('user_pass', InputOption::VALUE_REQUIRED, 'Enter totum admin password', '1111')

            ->addArgument('dbname', InputOption::VALUE_REQUIRED, 'Enter database name', '')
            ->addArgument('dbhost', InputOption::VALUE_REQUIRED, 'Enter database host', '')
            ->addArgument('dbuser', InputOption::VALUE_REQUIRED, 'Enter database user', '')
            ->addArgument('dbpass', InputOption::VALUE_REQUIRED, 'Enter database user password', '')

            ->addOption('pgdump', null, InputOption::VALUE_REQUIRED, 'Enter pg_dump(): ', '')
            ->addOption('psql', null, InputOption::VALUE_REQUIRED, 'Enter psql(): ', '')
            ->addOption('schema_exists', 'e', InputOption::VALUE_OPTIONAL,'Enter Y for install in existing schema')
            ->addOption('db_string', 'd', InputOption::VALUE_OPTIONAL,'Enter dbstring: postgresql://user:pass@host/dbname');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (class_exists(Conf::class)) {
            throw new Exception('Conf exists');
        }
        $confs = [];
        $confs['lang'] = $input->getArgument('lang');
        if(!in_array($confs['lang'], Totum::LANGUAGES)){
            throw new errorException('Language '.$confs['lang'].' is not supported');
        }

        $confs['multy'] = $input->getArgument('multi') === 'multi' ? '1' : '0';

        if ($confs['multy'] === '1' && empty($input->getOption('schema_exists'))) {
            $confs['schema_exists'] = true;
        } else {
            $confs['schema_exists'] = $input->getOption('schema_exists') === 'Y';
        }

        if(!empty($dbString=$input->getOption('db_string'))){
            if(preg_match('/^postgresql:\/\/(?<USER>[^:]+):(?<PASS>[^@]+)@(?<HOST>[^\/]+)\/(?<DBNAME>.+)$/', $dbString, $matches)){
                $confs['db_name'] = $matches['DBNAME'];
                $confs['db_host'] = $matches['HOST'];
                $confs['db_user_login'] = $matches['USER'];
                $confs['db_user_password'] = $matches['PASS'];

            }else throw new Exception('db_string not correct format');
        }else{
            $confs['db_name'] = $input->getArgument('dbname');
            $confs['db_host'] = $input->getArgument('dbhost');
            $confs['db_user_login'] = $input->getArgument('dbuser');
            $confs['db_user_password'] = $input->getArgument('dbpass');
        }

        $confs['db_schema'] = $input->getArgument('schema');
         $confs['pg_dump'] = $input->getOption('pgdump');
        $confs['psql'] = $input->getOption('psql');
        $confs['host'] = $input->getArgument('totum_host');
        $confs['user_login'] = $input->getArgument('user_login');
        $confs['user_pass'] = $input->getArgument('user_pass');
        $confs['admin_email'] = $input->getArgument('admin_email');

        $TotumInstall = new TotumInstall($confs, 'admin', $output);
        $TotumInstall->install(function ($file) {
            return dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'moduls' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . $file;
        });
        $output->write('done', true);
    }
}
