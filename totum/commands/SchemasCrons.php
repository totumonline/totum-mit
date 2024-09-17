<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class SchemasCrons extends Command
{
    protected function configure()
    {
        $this->setName('schemas-crons')
            ->setDescription('Execute crons of schemas for muti-install');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName) {
            `{$_SERVER['SCRIPT_FILENAME']} schema-crons "" $schemaName > /dev/null 2>&1 &`;
        }

        return 0;
    }
}
