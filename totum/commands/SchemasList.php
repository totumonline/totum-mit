<?php
declare(strict_types=1);


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class SchemasList extends Command
{
    protected function configure()
    {
        $this->setName('schemas-list')
            ->setDescription('List of active schemas');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemas=array_unique(array_values(Conf::getSchemas()));
        sort($schemas, SORT_STRING);

        foreach ($schemas as $schemaName) {
            $output->writeln($schemaName);
        }

        return 0;
    }
}
