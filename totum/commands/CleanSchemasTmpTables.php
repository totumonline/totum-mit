<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class CleanSchemasTmpTables extends Command
{
    protected function configure()
    {
        $this->setName('clean-schemas-tmp-tables')
            ->setDescription('Clean tmp_tables in schemas. For multi install. Set in crontab one time in 10 minutes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();
        foreach (array_unique(array_values(Conf::getSchemas())) as $schema) {
            CleanSchemaTmpTables::doSqlWorks($schema, $Conf);
        }
        return 0;
    }
}
