<?php


namespace totum\commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class CleanSchemasTmpTables extends Command
{
    protected function configure()
    {

        $this->setName('clean-schemas-tmp-tables')
            ->setDescription('Execute crons of schemas');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
       foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName){
           `bin/totum clean-schema-tmp-tables $schemaName > /dev/null 2>&1 &`;
       }

    }
}