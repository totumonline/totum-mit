<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\criticalErrorException;
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
        $Conf = new Conf();
        throw new criticalErrorException($Conf->getLangObj()->translate('This option works only in PRO.'));
    }
}
