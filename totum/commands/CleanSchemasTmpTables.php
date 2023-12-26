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
            ->setDescription('Clean tmp_tables in schemas. For multi install. Set in crontab one time in 10 minutes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();
        $sql = $Conf->getSql(withSchema: false);
        $plus24 = date_create();
        $plus24->modify('-24 hours');
        $minus10 = date_create();
        $minus10->modify('-10 minutes');

        foreach (array_unique(array_values(Conf::getSchemas())) as $schema) {
            $sql->exec('delete from "'.$schema.'"._tmp_tables where touched<\'' . $plus24->format('Y-m-d H:i') . '\'');
            $sql->exec('delete from "'.$schema.'"._tmp_tables where table_name SIMILAR TO \'\_%\' AND touched<\'' . $minus10->format('Y-m-d H:i') . '\'');
            $sql->exec('VACUUM "'.$schema.'"._tmp_tables');
        }
        return 0;
    }
}
