<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class SchemasUpdates extends Command
{
    protected function configure()
    {
        $this->setName('schemas-updates')
            ->setDescription('Update schemas')
            ->addArgument(
                'matches',
                InputOption::VALUE_REQUIRED,
                'Enter source name',
                'totum_' . (new Conf())->getLang()
            )
            ->addArgument('file', InputOption::VALUE_REQUIRED, 'Enter schema update filepath', 'sys_update')
            ->addOption('exclude', mode: InputOption::VALUE_IS_ARRAY|InputOption::VALUE_OPTIONAL, description: 'Enter schema names for exclude', default: []);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $matches = $input->getArgument('matches');
        $file = $input->getArgument('file');

        $excludeIn = $input->getOption('exclude') ?? [];
        $exclude=[];
        foreach ($excludeIn as $_){
            foreach (preg_split('/\s*?[, ]\s*?/', $_) as $s){
                if($s=trim($s)){
                    $exclude[]=$s;
                }
            }
        }

        foreach (array_unique(array_values(Conf::getSchemas())) as $schemaName) {
            if (in_array($schemaName, $exclude)) {
                $output->writeln('EXCLUDE ' . $schemaName);
                continue;
            }

            $output->writeln('update ' . $schemaName . " with source $matches from $file");

            $p = popen("{$_SERVER['SCRIPT_FILENAME']} schema-update $matches $file -s $schemaName", 'r');
            while (is_resource($p) && $p && !feof($p)) {
                $output->write("  " . fread($p, 1024));
            }
            pclose($p);
        }
    }
}
