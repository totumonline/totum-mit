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

        $dateVarName = 'last-check-creator-notifications';

        $Services = Services::init($Conf);
        $lastNoticeDate = $Services->getVarValue($dateVarName);

        $instanceVarName = 'service-notifications-instance';
        $instance = $Services->getVarValue($instanceVarName);

        if (!$instance) {
            $instance = md5($Conf->getFullHostName()) . bin2hex(random_bytes(7));
            $Services->insertName($instanceVarName);
            $Services->setVarValue($instanceVarName, $instance);
        }

        $weekAgo = date_create();
        $weekAgo->modify('-7 days');


        if (!$lastNoticeDate || $lastNoticeDate < $weekAgo->format('Y-m-d')) {
            if (!$lastNoticeDate) {
                $Services->insertName($dateVarName);
            }
            $uri = 'https://sn.totum.online/check-service-notifications';

            $context = stream_context_create(
                [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                        'method' => 'POST',
                        'content' => http_build_query([
                            'date' => $lastNoticeDate,
                            'lang' => $Conf->getLang(),
                            'version' => Totum::VERSION,
                            'instance' => $instance

                        ])
                    ],
                    'ssl' => [
                        'verify_peer' => $Conf->isCheckSsl(),
                        'verify_peer_name' => $Conf->isCheckSsl(),
                    ],
                ]
            );
            $data = file_get_contents($uri, true, $context);
            if ($data && $data = json_decode($data, true)) {
                if (($data['type'] ?? null) === 'service_notifications') {
                    if (!empty($data['value']) && !empty($data['value'][0])) {
                        $User = Auth::loadAuthUserByLogin($Conf, 'cron', false);
                        $Totum = new Totum($Conf, $User);

                        $users = $Totum->getTable('users')->getByParams(
                            (new FormatParamsForSelectFromTable())
                                ->where('roles', 1)
                                ->where('interface', 'web')
                                ->where('on_off', true)
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
                    $Services->setVarValue($dateVarName, $data['date']);
                }
            }

        }
    }
}
