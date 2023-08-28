<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\config\Conf;

class SchemaBackup extends Command
{
    protected function configure()
    {
        $this->setName('schema-backup')
            ->setDescription('Backup schema to file');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addOption('schema', 's', InputOption::VALUE_REQUIRED, 'Enter dumping schema name');
        }
        $this->addOption('gz', '', InputOption::VALUE_NONE, 'Use for file gziping')
            ->addOption('no-logs', '', InputOption::VALUE_NONE, 'For not duplicating logs')
            ->addOption('no-privileges', 'x', InputOption::VALUE_NONE, 'Prevent dumping of access privileges (grant/revoke commands)')
            ->addOption('no-content',
                '',
                InputOption::VALUE_OPTIONAL,
                'Enter table names separated by commas for not duplicating it\'s content');
        $this->addArgument('filename',
            InputArgument::REQUIRED,
            'Path for save backup. You can use placeholders (%schema%, and date-time values: %d%, %H% etc');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();
        if (is_callable([$Conf, 'setHostSchema'])) {
            if ($baseName = $input->getOption('schema')) {
                $Conf->setHostSchema(null, $baseName);
            }
        }

        $schema = $Conf->getSchema();

        if (!$input->getArgument('filename')) {
            throw new errorException('Filename argument is required');
        }
        $path = preg_replace_callback('/%([^%]+)%/', function ($match) use ($schema) {
            $match = $match[1];
            if ($match === 'schema') {
                return $schema;
            }
            return date($match);
        }, $input->getArgument('filename'));

        $gz = $input->getOption('gz') ?? false;

        if (!preg_match('/.gz$/', $path) && $gz) {
            $path .= '.gz';
        }

        $pgDump = $Conf->getSshPostgreConnect('pg_dump');

        $exclude = "--exclude-table-data='{$schema}._tmp_tables'";
        $exclude .= " --exclude-table-data='{$schema}._bfl'";

        if ($input->getOption('no-logs')) {
            $exclude .= " --exclude-table-data='{$schema}._log'";
        }
        if ($input->getOption('no-content')) {
            foreach (explode(',', $input->getOption('no-content')) as $tName) {
                $exclude .= " --exclude-table-data='{$schema}.$tName'";
            }
        }
        $gz=($gz ? '| gzip' : '');

        if ($input->getOption('no-privileges')) {
            $exclude .= ' -x';
        }

        `$pgDump -O --schema '{$schema}' --no-tablespaces {$exclude} | grep -v '^--' $gz > "{$path}"`;
    }
}