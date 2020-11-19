<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\config\Conf;

class CleanTmpTablesFilesMulti extends Command
{
    protected function configure()
    {
        $this->setName('clean-tmp-tables-files-multi')
            ->setDescription('Clean tmp tables files for all schemas. Set in crontab one time in hour.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName) {
            `bin/totum clean-tmp-tables-files -s $schemaName > /dev/null 2>&1 &`;
        }
    }
}
