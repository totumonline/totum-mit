<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\common\criticalErrorException;
use totum\config\Conf;

class SchemaCrons extends Command
{
    protected function configure()
    {
        $this->setName('schema-crons')
            ->setDescription('Execute totum codes of table crons for single install')
            ->addArgument('datetime', InputOption::VALUE_REQUIRED, 'Enter datetime');

        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();
        throw new criticalErrorException($Conf->getLangObj()->translate('This option works only in PRO.'));
    }
}
