<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use totum\common\configs\MultiTrait;
use totum\common\errorException;
use totum\common\TotumInstall;
use totum\common\User;
use totum\config\Conf;
use totum\config\Conf2;

class SchemaDuplicate extends Command
{
    protected function configure()
    {

        $this->setName('schema-duplicate')
            ->setDescription('Duplicate schema')
            ->addArgument('base', InputOption::VALUE_REQUIRED, 'Enter base schema name')
            ->addArgument('name', InputOption::VALUE_REQUIRED, 'Enter schema name')
            ->addArgument('host', InputOption::VALUE_REQUIRED, 'Enter schema host');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!class_exists(Conf::class)) {
            $output->writeln('ERROR: config class not found');
        }
        $Conf = new Conf();


        $baseName = $input->getArgument('base');
        if (empty($baseName)) {
            throw new errorException('Заполните имя схемы-источника');
        }
        if (!in_array($baseName, array_values($Conf::getSchemas()))) {
            throw new errorException("$baseName схема не найдена в Conf");
        }
        if(is_callable([$Conf, 'setHostSchema'])){
            $Conf->setHostSchema(null, $baseName);
        }

        $desName = $input->getArgument('name');
        if (empty($desName)) {
            throw new errorException('Заполните имя схемы-дубля');
        }

        if (in_array($desName, array_values($Conf::getSchemas()))) {
            throw new errorException("$desName схема уже есть в Conf");
        }

        $pgDump = $Conf->getSshPostgreConnect('pg_dump');

        $tmpFilenameOld = tempnam($Conf->getTmpDir(), 'schemaDuplicate.' . $baseName);

        $exclude = "--exclude-table-data='_tmp_tables'";
        $exclude .= " --exclude-table-data='_bfl'";
        if ($withLog ?? false) {
            $exclude .= " --exclude-table-data='_log'";
        }
        `$pgDump -O --schema '{$baseName}' --no-tablespaces {$exclude} | grep -v '^--' > "{$tmpFilenameOld}"`;
        if (filesize($tmpFilenameOld) < 20) {
            $output->writeln(file_get_contents($tmpFilenameOld));
        } else {
            $handle = fopen($tmpFilenameOld, 'r');

            $tmpFileName = tempnam($Conf->getTmpDir(), 'schemaDuplicate.' . $baseName . '-dest');

            if (!($handleTmp = @fopen($tmpFileName, "a"))) {
                throw new errorException('Временный файл ' . $tmpFileName . ' не получается открыть');
            }


            $is_schema_replaced = false;
            while (($buffer = fgets($handle)) !== false) {
                if (!$is_schema_replaced && preg_match('/^CREATE SCHEMA/', $buffer)) {
                    $tmpold = $baseName . '_tmpold';
                    $buffer = 'ALTER SCHEMA "' . $baseName . '" RENAME TO "' . $tmpold . '";' . $buffer;
                    $is_schema_replaced = true;
                }
                fputs($handleTmp, $buffer);
            }
            $buffer = 'update "' . $baseName . '".crons set status=jsonb_build_object(\'v\', false);';
            $buffer .= 'ALTER SCHEMA "' . $baseName . '" RENAME TO "' . $desName . '";';
            $buffer .= 'ALTER SCHEMA "' . $tmpold . '" RENAME TO "' . $baseName . '";';
            fputs($handleTmp, $buffer);

            if (!feof($handle)) {
                throw new errorException("Error: unexpected fgets() fail");
            }

            fclose($handle);
            unlink($tmpFilenameOld);
            fclose($handleTmp);

            $pathPsql = $Conf->getSshPostgreConnect('psql');
            echo `$pathPsql -1 -v ON_ERROR_STOP=1 -f $tmpFileName | grep ERROR`;
            unlink($tmpFileName);

            if ($host = $input->getArgument('host')) {

                $ConfFile = (new \ReflectionClass(Conf::class))->getFileName();
                $ConfFileContent = file_get_contents($ConfFile);

                if (preg_match('~\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return([^$]*)\}[^$]*/\*\*\*getSchemasEnd\*\*\*/~',
                    $ConfFileContent,
                    $matches)) {
                    $output->writeln('save Conf.php');

                    eval("\$schemas={$matches[1]}");
                    $schemas[$host] = $desName;
                    $ConfFileContent = preg_replace('~(\/\*\*\*getSchemas\*\*\*\/[^$]*{[^$]*return\s*)([^$]*)(\}[^$]*/\*\*\*getSchemasEnd\*\*\*/)~',
                        '$1' . var_export($schemas, 1) . ';$3',
                        $ConfFileContent);
                    copy($ConfFile, $ConfFile . '_old');
                    file_put_contents($ConfFile, $ConfFileContent);
                }
            }
        }
    }
}
