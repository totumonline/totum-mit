<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\config\Conf;

class Vacuum extends Command
{
    protected function configure()
    {
        $this->setName('vacuum')
            ->setDescription('vacuum all database or table in schema');

        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_OPTIONAL, 'Enter schema name');
        }
        $this->addOption('table', 't', InputOption::VALUE_OPTIONAL, 'Enter table name');
        $this->addOption('analyze', 'a', InputOption::VALUE_NONE, 'With analyze');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();

        if ($table=$input->getOption('table')) {
            if (is_callable([$Conf, 'setHostSchema'])) {
                if ($schema = $input->getOption('schema')) {
                    $Conf->setHostSchema(null, $schema);
                } else {
                    throw new errorException('set schema');
                }
            }
            $sql = $Conf->getSql(true, true);

            $sql->exec('VACUUM '.($input->getOption('analyze')?' ANALYZE ':'').$table);

        } else {
            $sql = $Conf->getSql(true, false);
            $sql->exec('VACUUM '.$table);
        }

        return 0;
    }
}
