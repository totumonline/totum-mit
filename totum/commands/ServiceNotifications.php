<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\Auth;
use totum\common\configs\MultiTrait;
use totum\common\FormatParamsForSelectFromTable;
use totum\common\Services\Services;
use totum\common\Totum;
use totum\config\Conf;

class ServiceNotifications extends Command
{
    protected function configure()
    {
        $this->setName('check-service-notifications')
            ->setDescription('Check service notifications and add them for Creator users');

        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getArgument('schema')) {
                $Conf->setHostSchema(null, $schema);
            }
        }

        $varName = 'last-check-creator-notifications';

        $Services = Services::init($Conf);
        $lastDateTime = $Services->getVarValue($varName);

        $weekAgo = date('Y-m-d H:i:s', time() - 7 * 24 * 3600);

        if (!$lastDateTime || $lastDateTime < $weekAgo) {
            if (!$lastDateTime) {
                $Services->insertName($varName);
            }
            $uri = 'https://' . ($Conf->getLang() === 'ru' ? $Conf->getLang() . '.' : '') . 'totum.online/service_notifications';


            $context = stream_context_create(
                [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                        'method' => 'POST',
                        'content' => http_build_query(['from' => $lastDateTime])
                    ],
                    'ssl' => [
                        'verify_peer' => $Conf->isCheckSsl(),
                        'verify_peer_name' => $Conf->isCheckSsl(),
                    ],
                ]
            );
            $data = file_get_contents($uri, true, $context);
            if ($data && $data = json_decode($data, true)) {
                if ($data['type'] === 'service_notifications') {
                    if (!empty($data['value']) && !empty($data['value'][0])) {
                        $User = Auth::loadAuthUserByLogin($Conf, 'cron', false);
                        $Totum = new Totum($Conf, $User);

                        $users = $Totum->getTable('users')->getByParams(
                            (new FormatParamsForSelectFromTable())
                                ->where('roles', 1)
                                ->field('id')
                                ->params(),
                            'list');

                        $add = [];
                        foreach ($users as $id) {
                            foreach ($data['value'] as $row) {
                                $add[] = [
                                    'title' => $row['title'],
                                    'user_id' => $id,
                                    'vars' => ['text' => $row['html']],
                                    'code' => 'admin_text',
                                    'active' => true
                                ];
                            }
                        }
                        $Totum->getTable('notifications')->reCalculateFromOvers([
                            'add' => $add
                        ]);
                    }
                }
                $Services->setVarValue($varName, date('Y-m-d H:i'));
            }

        }
    }
}
