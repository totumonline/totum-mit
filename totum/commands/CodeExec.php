<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\common\Totum;
use totum\config\Conf;

class CodeExec extends Command
{
    protected function configure()
    {
        $this->setName('exec')
            ->addArgument('userId', InputArgument::REQUIRED, 'Int UserId in schema')
            ->addArgument('code', InputArgument::REQUIRED, 'base64 {"code":"CODE","vars":{}}')
            ->setDescription('Technical function for run Totum-code from CLI.');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name to execute code');
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

        $User = Auth::getUserById($Conf, $input->getArgument('userId'));
        if (!$User) {
            throw new errorException('userId not correct');
        }

        $data = base64_decode($input->getArgument('code'));
        if (empty($data) || !($data = json_decode($data, true)) || !key_exists('code',
                $data) || (!is_string($data['code']) || (!empty($data['vars']) && !is_array($data['vars'])))) {
            throw new errorException('Format of argument code is not correct');
        }

        $Totum = new Totum($Conf, $User);

        $Totum->transactionStart();

        $Table = $Totum->getTable(1, null, true);

        $calc = new CalculateAction($data['code']);
        $vars = $data['vars'] ?? [];
        $vars['tpa'] = 'exec';
        $calc->exec(['name' => 'SSH CODE'], [], [], [], [], [], $Table, $vars);

        $Totum->transactionCommit();

        return 0;
    }
}
