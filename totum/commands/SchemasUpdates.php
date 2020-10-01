<?php


namespace totum\commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class SchemasUpdates extends Command
{
    protected function configure()
    {

        $this->setName('schemas-updates')
            ->setDescription('Update schemas')
            ->addArgument('matches',
                InputOption::VALUE_REQUIRED,
                'Enter source name',
                'totum_' . (new Conf())->getLang())
            ->addArgument('file', InputOption::VALUE_REQUIRED, 'Enter schema file', 'sys_update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $matches=$input->getArgument('matches');
        $file=$input->getArgument('file');

        foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName) {
            $output->writeln('update ' . $schemaName." with source $matches from $file");

            $p=popen("bin/totum schema-update $matches $file -s $schemaName", 'r');
            while(is_resource($p) && $p && !feof($p))
                $output->write("  ".fread($p, 1024));
            pclose($p);
        }

    }
}