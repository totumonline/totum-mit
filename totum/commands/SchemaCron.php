<?php


namespace totum\commands;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\Auth;
use totum\common\CalculateAction;
use totum\common\calculates\Calculate;
use totum\common\errorException;
use totum\common\Model;
use totum\common\tableSaveException;
use totum\common\Totum;
use totum\config\Conf;

class SchemaCron extends Command
{
    protected function configure()
    {

        $this->setName('schema-cron')
            ->setDescription('Execute totum code of table settings')
            ->addArgument('cronId', InputOption::VALUE_REQUIRED, 'Enter cron id')
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

        if ($cronId = $input->getArgument('cronId')) {
            if ($cronRow = $Conf->getModel('settings')->get(['id' => (int)$cronId, 'status' => 'true'])) {
                $cronRow = Model::getClearValuesWithExtract($cronRow);
            }
        }

        if (empty($cronRow)) {
            throw new \Exception('Не задан или не найден ид крона');
        }


        $User = Auth::loadAuthUserByLogin($Conf, 'cron', false);
        $i = 0;
        while (++$i <= 4) {
            try {
                try {
                    $Totum = new Totum($Conf, $User);
                    $Totum->transactionStart();
                    $Table = $Totum->getTable('settings');
                    $Calc = new CalculateAction($cronRow['code']);
                    $Calc->execAction('CRON', $cronRow, $cronRow, $Table->getTbl(), $Table->getTbl(), $Table, []);
                    $Totum->transactionCommit();
                } catch (errorException $e) {
                    $Conf = $Conf->getClearConf();
                    $Conf->cronErrorActions($cronRow, $User, $e);
                }
                break;
            } catch (tableSaveException $exception) {
                $Conf = $Conf->getClearConf();
            }
        }
    }
}