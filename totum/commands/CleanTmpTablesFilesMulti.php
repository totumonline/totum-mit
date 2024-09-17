<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class CleanTmpTablesFilesMulti extends Command
{
    protected function configure()
    {
        $this->setName('clean-tmp-tables-files-multi')
            ->setDescription('Clean tmp tables files for all schemas. Set in crontab one time in hour.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName) {
            `{$_SERVER['SCRIPT_FILENAME']} clean-tmp-tables-files -s $schemaName > /dev/null 2>&1 &`;
        }
        return 0;
    }
}
