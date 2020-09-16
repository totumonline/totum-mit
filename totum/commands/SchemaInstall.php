<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use totum\common\TotumInstall;
use totum\config\Conf;

class SchemaInstall extends Command
{
    protected function configure()
    {
        $this->setName('install')
            ->setDescription('install schema')
            ->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name (new_totum): ', 'new_totum')
            ->addArgument('dbname', InputOption::VALUE_REQUIRED, 'Enter database name: ')
            ->addArgument('dbhost', InputOption::VALUE_REQUIRED, 'Enter database host: ')
            ->addArgument('dbuser', InputOption::VALUE_REQUIRED, 'Enter database user: ')
            ->addArgument('dbpass', InputOption::VALUE_REQUIRED, 'Enter database user password: ')
            ->addOption('pgdump', 'pgd', InputOption::VALUE_REQUIRED, 'Enter pg_dump(): ', '')
            ->addOption('psql', 'pgs', InputOption::VALUE_REQUIRED, 'Enter psql(): ', '')
            ->addArgument('admin_email', InputOption::VALUE_REQUIRED, 'Enter admin email: ', '')
            ->addArgument('totum_host', InputOption::VALUE_REQUIRED, 'Enter totum host: ')
            ->addArgument('user_login', InputOption::VALUE_REQUIRED, 'Enter totum admin login(admin): ', 'admin')
            ->addArgument('user_pass', InputOption::VALUE_REQUIRED, 'Enter totum admin password(1111): ', '1111')
            ->addOption('schema_exists', 'ex', InputOption::VALUE_REQUIRED, 'Install in existing schema(N): ', 'N');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (class_exists(Conf::class)) {
            throw new \Exception('Conf exists');
        }
        $confs = [];
        $confs['db_schema'] = $input->getArgument('schema');
        $confs['db_name'] = $input->getArgument('dbname');
        $confs['db_host'] = $input->getArgument('dbhost');
        $confs['db_user_login'] = $input->getArgument('dbuser');
        $confs['db_user_password'] = $input->getArgument('dbpass');
        $confs['pg_dump'] = $input->getOption('pgdump');
        $confs['psql'] = $input->getOption('psql');
        $confs['host'] = $input->getArgument('totum_host');
        $confs['user_login'] = $input->getArgument('user_login');
        $confs['user_pass'] = $input->getArgument('user_pass');
        $confs['admin_email'] = $input->getArgument('admin_email');

        $confs['schema_exists'] = $input->getOption('schema_exists') === 'Y';


        $TotumInstall = new TotumInstall($confs, 'admin', $output);
        $TotumInstall->install(function ($file) {
            return dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'moduls' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . $file;
        });
    }
}
