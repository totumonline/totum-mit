<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\configs\MultiTrait;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Model;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\config\Conf;
use totum\tableTypes\RealTables;

class SchemaCron extends Command
{
    protected function configure()
    {
        $this->setName('schema-cron')
            ->setDescription('Execute exact totum code of table crons')
            ->addArgument('cronId', InputOption::VALUE_REQUIRED, 'Enter cron id');
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
