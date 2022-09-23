<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\Auth;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\common\Totum;
use totum\config\Conf;
use totum\models\Table;
use totum\tableTypes\RealTables;

class SchemaUserUnblock extends Command
{
    protected function configure()
    {
        $this->setName('schema-user-unblock')
            ->setDescription('Unblock user authorization')
            ->addArgument('login', InputOption::VALUE_REQUIRED, 'Enter user login');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }


        if (empty($login = $input->getArgument('login'))) {
            throw new errorException('Enter user login');
        }

        if (($block_time = $Conf->getSettings('h_time')) && ($error_count = (int)$Conf->getSettings('error_count'))) {
            $BlockDate = date_create()->modify('-' . $block_time . 'minutes');
            $block_date = $BlockDate->format('Y-m-d H:i');
        } else {
            $output->writeln('<error>User authorization blocking is off for this scheme. Users are not blocked because of incorrect passwords.</error>');
            return 0;
        }

        $model = $Conf->getModel('auth_log');
        $record = $model->get(['login' => $login, 'status' => 2, 'datetime->>\'v\'>=\'' . $block_date . '\'',],
            '*',
            'id desc');

        if (!$record) {
            if (!$Conf->getModel('users')->get(['login' => $login])) {
                throw new errorException('Record with block status for this login was not found. Check user login');
            }
            $output->writeln('<comment>Record with block status for this login was not found. Blocking may have expired</comment>');
        } else {
            $record = RealTables::decodeRow($record);
            $model->update(['status' => 1], ['id' => $record['id']]);
            $output->writeln('<info>' . $login . ' blocking has been removed for ip ' . $record['user_ip']['v'] . '</info>');
        }

        return 0;
    }
}
