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
            ->setDescription('Add new schema')
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
}
