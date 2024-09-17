<?php

namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\config\Conf;

class SchemaReplace extends Command
{
    protected function configure()
    {
        $this->setName('schema-replace')
            ->setDescription('Replace or add schema');
        $this->addArgument('filename',
            InputArgument::REQUIRED,
            'Path to schema sql file');
        if (key_exists(MultiTrait::class, class_uses(Conf::class, false))) {
            $this->addArgument('schema',
                InputArgument::REQUIRED,
                'Enter add/replace schema name (Latin letters, numbers and "_-" symbols.)');
            $this->addArgument('host',
                InputArgument::OPTIONAL,
                'Enter host if schema is new');
        }
        $this->addOption('with-active-crons',
            '',
            InputOption::VALUE_NONE,
            'Do not switch off crons in replaced schema.');
        $this->addOption('without-host',
            '',
            InputOption::VALUE_NONE,
            'Do not add new host in Conf.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();

        if (!is_callable([$Conf, 'setHostSchema'])) {
            $schemaName = $Conf->getSchema();
        } else {
            $schemaName = $input->getArgument('schema');
            if (empty($schemaName)) {
                throw new errorException('Schema cann\'t be empty');
            }
            if (!preg_match('/^[a-z_0-9\-]+$/', $schemaName)) {
                throw new errorException('Schema must contain only Latin letters, numbers and "_-" symbols.');
            }

            if (!($host = $input->getArgument('host'))) {
                $exists = false;
                foreach ($Conf::getSchemas() as $h => $s) {
                    if ($s === $schemaName) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists && !$input->getOption('without-host')) {
                    $helper = $this->getHelper('question');
                    $question = new Question('Please enter the name of the new host: ');

                    if (!($host = $helper->ask($input, $output, $question))) {
                        $output->writeln('Host is required');
                        return 0;
                    }
                }
            }

        }

        $checkschemaExists = function ($schemaName) use ($Conf) {
            return $Conf->getSql(true, false)
                ->get("SELECT schema_name FROM information_schema.schemata WHERE schema_name = '$schemaName'");
        };


        if ($checkschemaExists($schemaName)) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Schema ' . $schemaName . ' will be recreated from this file. Do you really want this?',
                false);

            if (!$helper->ask($input, $output, $question)) {
                $output->write('Nothing\'s done.');
                return 0;
            }
        }

        if (!$input->getArgument('filename')) {
            throw new errorException('Filename argument is required');
        }
        if (!is_file($filename = $input->getArgument('filename'))) {
            throw new errorException('File not exists');
        }

        set_time_limit(0);


        $tmpFileName = tempnam($Conf->getTmpDir(), 'schemaReplace.' . $schemaName . '-dest');
        if (!($handleTmp = @fopen($tmpFileName, 'a'))) {
            throw new errorException('Temporary file ' . $tmpFileName . ' not writeable');
        }
        $handle = gzopen($filename, 'r');

        $is_schema_replaced = false;
        $addedSchemaName = null;
        $addedSchemaTmpName = null;
        while (($buffer = gzgets($handle)) !== false) {
            if (!$is_schema_replaced && preg_match('/^CREATE SCHEMA ["\']?([a-z_0-9\-]+)["\']?/',
                    $buffer,
                    $schemaMatch)) {
                $dropSchema = 'DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE;' . "\n";
                $addedSchemaName = $schemaMatch[1];
                if ($addedSchemaName != $schemaName && $checkschemaExists($addedSchemaName)) {
                    do {
                        $addedSchemaTmpName = ($addedSchemaTmpName ?? $addedSchemaName) . '---totum-tmp-renaming';
                    } while ($checkschemaExists($addedSchemaTmpName));
                    $buffer = 'ALTER SCHEMA "' . $addedSchemaName . '" RENAME TO "' . $addedSchemaTmpName . '";' . "\n" . $buffer;
                }
                /*Удаляем схему с новым именем, если она существует (уже спросили подтверждение)*/
                $buffer = $dropSchema . $buffer;
                $is_schema_replaced = true;
            }
            fputs($handleTmp, $buffer);
        }


        if ($schemaName != $addedSchemaName) {
            fputs($handleTmp, 'ALTER SCHEMA "' . $addedSchemaName . '" RENAME TO "' . $schemaName . '";' . "\n");
        }
        if ($addedSchemaTmpName) {
            fputs($handleTmp,
                'ALTER SCHEMA "' . $addedSchemaTmpName . '" RENAME TO "' . $addedSchemaName . '";' . "\n");
        }

        if (!$input->getOption('with-active-crons')) {
            fputs($handleTmp, 'update "' . $schemaName . '".crons set status=jsonb_build_object(\'v\', false);');
        }

        fclose($handle);
        fclose($handleTmp);

        $pathPsql = $Conf->getSshPostgreConnect('psql');
        $Conf->getSql(true, false);
        $result = `$pathPsql -1 -v ON_ERROR_STOP=1 -f $tmpFileName | grep ERROR`;
        $output->writeln('Sql data loaded' . ($result ? ' with:' . $result : ''));

        unlink($tmpFileName);

        if (!empty($host)) {
            $ConfFile = (new \ReflectionClass(Conf::class))->getFileName();
            $ConfFileContent = file_get_contents($ConfFile);

            if (preg_match(
                '~(\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getSchemasEnd\*\*\*/)~',
                $ConfFileContent,
                $matches
            )) {
                eval("\$schemas={$matches[2]}");
                $schemas[$host] = $schemaName;
                $ConfFileContent = preg_replace(
                    '~(\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getSchemasEnd\*\*\*/)~',
                    '$1' . var_export($schemas, 1) . ';$3',
                    $ConfFileContent
                );
                copy($ConfFile, $ConfFile . '_old');
                if (file_put_contents($ConfFile, $ConfFileContent)) {
                    $output->writeln($ConfFile . ' replaced. Backup in ' . $ConfFile . '_old');
                }
            }
        }

        if ($result) {
            $output->writeln('We found some errors in process');
        } else {
            $output->writeln('Done');
        }

        return 0;
    }
}