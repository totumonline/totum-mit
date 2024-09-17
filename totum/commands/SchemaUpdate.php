<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\common\TotumInstall;
use totum\common\User;
use totum\config\Conf;

class SchemaUpdate extends Command
{
    protected function configure()
    {
        $this->setName('schema-update')
            ->setDescription('Update schema')
            ->addArgument(
                'matches',
                InputArgument::OPTIONAL,
                'Enter source name',
                'totum_' . (new Conf())->getLang()
            )
            ->addArgument('file', InputArgument::OPTIONAL, 'Enter schema update filepath', 'sys_update');

        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter schema name', '');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();
        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($schema = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $schema);
            }else{
                $output->writeln('Set option -s for identify the schema or use schemas-update for update all ones');
                return 0;
            }
        }
        $sourceName = $input->getArgument('matches');

        $file = $input->getArgument('file');

        $TotumInstall = new TotumInstall(
            $Conf,
            new User(['login' => 'service', 'roles' => ["1"], 'id' => 1], $Conf),
            $output
        );

        if ($file === 'sys_update') {
            $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'moduls' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR;
            $file = $path. 'start.json.gz.ttm';
            $cont = $TotumInstall->getDataFromFile($file);
            $cont = $TotumInstall->schemaTranslate($cont, $path.$Conf->getLang() . '.json', $Conf->getLang() !== 'en' ? $path.'en.json' : null);
        }else{
            $cont = $TotumInstall->getDataFromFile($file);
        }

        if (($matches = json_decode($sourceName, true)) && is_array($matches) && key_exists(
            'name',
            $matches
        ) && key_exists('matches', $matches)) {
            $sourceName=$matches['name'];
            $matches=$matches['matches'];
        } else {
            $matches = $TotumInstall->getTotum()->getTable('ttm__updates')->getTbl()['params']['h_matches']['v'][$sourceName] ?? [];
        }
        $cont = $TotumInstall->applyMatches($cont, $matches);

        $TotumInstall->updateSchema($cont, true, $sourceName);

        return 0;
    }
}
