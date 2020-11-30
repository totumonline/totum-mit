<?php

namespace totum\commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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
            ->addArgument('lang', InputArgument::REQUIRED, 'Enter language ('.implode('/', Totum::LANGUAGES).')')
            ->addArgument('multi', InputArgument::REQUIRED, 'Enter type of install (multi/no-multi)')
            ->addArgument('schema', InputArgument::REQUIRED, 'Enter schema name')
            ->addArgument('admin_email', InputArgument::REQUIRED, 'Enter admin email')
            ->addArgument('totum_host', InputArgument::REQUIRED, 'Enter totum host')

            ->addArgument('user_login', InputArgument::OPTIONAL, 'Enter totum admin login', 'admin')
            ->addArgument('user_pass', InputArgument::OPTIONAL, 'Enter totum admin password', '1111')

            ->addArgument('dbname', InputArgument::OPTIONAL, 'Enter database name')
            ->addArgument('dbhost', InputArgument::OPTIONAL, 'Enter database host')
            ->addArgument('dbuser', InputArgument::OPTIONAL, 'Enter database user')
            ->addArgument('dbpass', InputArgument::OPTIONAL, 'Enter database user password')
            ->addArgument('dbport', InputArgument::OPTIONAL, 'Enter database database port', 5432)

             ->addOption('pgdump', null, InputOption::VALUE_REQUIRED, 'Enter pg_dump(): ', '')
            ->addOption('psql', null, InputOption::VALUE_REQUIRED, 'Enter psql(): ', '')
            ->addOption('schema_exists', 'e', InputOption::VALUE_NONE, 'Set for install in existing schema')
            ->addOption('db_string', 'd', InputOption::VALUE_OPTIONAL, 'Enter dbstring: postgresql://user:pass@host/dbname');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (class_exists(Conf::class)) {
            throw new Exception('Conf exists');
        }
        $confs = [];
        $confs['lang'] = $input->getArgument('lang');
        if (!in_array($confs['lang'], Totum::LANGUAGES)) {
            throw new errorException('Language '.$confs['lang'].' is not supported');
        }

        $confs['multy'] = $input->getArgument('multi') === 'multi' ? '1' : '0';

        if ($confs['multy'] === '1' && empty($input->getOption('schema_exists'))) {
            $confs['schema_exists'] = true;
        } else {
            $confs['schema_exists'] = (bool)$input->getOption('schema_exists');
        }

        if (!empty($dbString=$input->getOption('db_string'))) {
            if (preg_match('/^postgresql:\/\/(?<USER>[^:]+):(?<PASS>[^@]+)@(?<HOST>[^\/]+)\/(?<DBNAME>.+)$/', $dbString, $matches)) {
                $confs['db_name'] = $matches['DBNAME'];
                $confs['db_host'] = $matches['HOST'];
                $confs['db_port'] = 5432;
                $confs['db_user_login'] = $matches['USER'];
                $confs['db_user_password'] = $matches['PASS'];
            } else {
                throw new Exception('db_string not correct format');
            }
        } else {
            $confs['db_name'] = $input->getArgument('dbname');
            $confs['db_host'] = $input->getArgument('dbhost');
            $confs['db_port'] = $input->getArgument('dbport');
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
            return __DIR__ . '/../moduls/install/' . $file;
        });
        $output->write('done', true);
    }
}
