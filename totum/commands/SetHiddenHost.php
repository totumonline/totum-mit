<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\config\Conf;

class SetHiddenHost extends Command
{

    protected function configure()
    {
        $this->setName('set-hidden-host')
            ->setDescription('Add/set hidden host')
            ->addArgument(
                'hidden_host',
                InputArgument::REQUIRED,
                'Hidden host name'
            )
            ->addArgument(
                'main_host',
                InputArgument::REQUIRED,
                'Main host name'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();
        if (!is_callable([$Conf, 'setHostSchema'])) {
            throw new errorException('Is is not-multiple installation');
        }

        $ConfFile = (new \ReflectionClass(Conf::class))->getFileName();
        $ConfFileContent = file_get_contents($ConfFile);

        if (!preg_match(
            '~\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return([^$]*)\}[^$]*/\*\*\*getSchemasEnd\*\*\*/~',
            $ConfFileContent,
            $matches
        )) {
            throw new errorException('Is is old installation file. Is is not possible to modify it this way');
        }
        $host = $input->getArgument('main_host');
        $newHost = $input->getArgument('hidden_host');
        $schemas = $Conf->getSchemas();

        if (empty($schemas[$newHost])) {
            $schema = $schemas[$host];
            if (empty($schema)) {
                throw new errorException('The specified main host is not found in Conf.php');
            }
            $schemas[$newHost] = $schema;

            $ConfFileContent = preg_replace(
                '~(\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getSchemasEnd\*\*\*/)~',
                '$1' . var_export($schemas, 1) . ';$3',
                $ConfFileContent
            );

        }

        $hiddenHosts = $Conf->getHiddenHosts();

        if(!empty($hiddenHosts[$newHost])){
            throw new errorException('The specified hidden host already setted in Conf.php');
        }

        $hiddenHosts[$newHost] = [
          'An'=>true,
          'Remotes'=>true,
          'Json'=>true,
          'Forms'=>true,
        ];

        if (!preg_match(
            '~\/\*\*\*getHiddenHosts\*\*\*\/[^$]*{[^$]*return([^$]*)\}[^$]*/\*\*\*getHiddenHostsEnd\*\*\*/~',
            $ConfFileContent,
            $matches
        )) {
            $hiddenHostsPhp=var_export($hiddenHosts, 1);

            $ConfFileContent = str_replace(
                '/***getSchemas***/',
<<<TXT

/***getHiddenHosts***/
    public function getHiddenHosts():array {
        return $hiddenHostsPhp;
    }
    /***getHiddenHostsEnd***/

    /***getSchemas***/
TXT,

                $ConfFileContent
            );

        }else{
            $ConfFileContent = preg_replace(
                '~(\/\*\*\*getHiddenHosts\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getHiddenHostsEnd\*\*\*/)~',
                '$1' . var_export($hiddenHosts, 1) . ';$3',
                $ConfFileContent
            );
        }


        copy($ConfFile, $ConfFile . '_old');

        file_put_contents($ConfFile, $ConfFileContent);
    }
}