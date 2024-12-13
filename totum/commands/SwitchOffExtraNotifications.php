<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\ConfParent;
use totum\common\configs\MultiTrait;
use totum\common\Services\Services;
use totum\config\Conf;

class SwitchOffExtraNotifications extends Command
{
    protected function configure()
    {
        $this->setName('switch-off-extra-notifications')
            ->setDescription('Set off status for older notifications more than max argument')
        ->addArgument('max', InputOption::VALUE_REQUIRED, 'Max limit for notifications');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema', InputOption::VALUE_REQUIRED, 'Enter schema name');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getArgument('schema')) {
            }
        }
        if (empty($schema)) {
            $schema = $Conf->getSchema(true);
        }


        $sql = $Conf->getSql(true, false);

        $max = $input->getArgument('max') ?? '';
        if (!ctype_digit($max)){
            throw new \Exception('Argument max must be integer');
        }
        $max = (int)$max;
        if ($max < 1){
            throw new \Exception('Argument max must be > 0');
        }

        $sql->exec('WITH ranked_entries AS (
    SELECT
        id,
        ROW_NUMBER() OVER (PARTITION BY user_id->>\'v\' ORDER BY active_dt_from->>\'v\' DESC) AS rnk
    FROM
        notifications
    WHERE active->>\'v\' = \'true\'
)
UPDATE notifications set active = \'{"v":false}\'
WHERE id IN (
    SELECT id FROM ranked_entries WHERE rnk > '.$max.'
)');

        return 0;
    }

}
