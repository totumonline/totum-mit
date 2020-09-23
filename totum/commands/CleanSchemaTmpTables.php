<?php


namespace totum\commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class CleanSchemaTmpTables extends Command
{
    protected function configure()
    {
        $this->setName('clean-schema-tmp-tables')
            ->setDescription('Remove old tmp tables')
            ->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Conf = new Conf();
        if ($schema = $input->getArgument('schema')) {
            if (is_callable([$Conf, 'setHostSchema'])) {
                $Conf->setHostSchema(null, $schema);
            }
        }

        $plus24 = date_create();
        $plus24->modify('-24 hours');
        $Conf->getSql()->exec('delete from _tmp_tables where touched<\'' . $plus24->format('Y-m-d H:i') . '\'');

        $minus10 = date_create();
        $minus10->modify('-10 minutes');
        $Conf->getSql()->exec('delete from _tmp_tables where table_name IN (\'_panelbuttons\', \'_linkToButtons\') AND touched<\'' . $minus10->format('Y-m-d H:i') . '\'');

        $Conf->getSql()->exec('vacuum');
    }
}