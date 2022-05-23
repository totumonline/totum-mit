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

class GitUpdate extends Command
{
    protected function configure()
    {
        $this->setName('git-update')
            ->addOption('force', '', InputOption::VALUE_NONE, 'Force update without checking changing major version.')
            ->setDescription('update from git origin master && composer && schema(s)-update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$input->getOption('force')) {
            $error = true;
            if ($totumClass = file_get_contents('https://raw.githubusercontent.com/totumonline/totum-mit/master/totum/common/Totum.php')) {
                if (preg_match('/public\s*const\s*VERSION = \'(\d+)/', $totumClass, $matches)) {
                    $oldVersion = (string)preg_replace('/^(\d+).*$/', '$1', Totum::VERSION);
                    if ($oldVersion !== $matches[1]) {
                        die('This update will change the major version from '.$oldVersion.' to ' . $matches[1].' '.
                            'Check server settings and backward compatibility violations at https://github.com/totumonline/totum-mit/blob/master/UPDATES.md ' .
                            'Use --force if you are sure about the update.');
                    } else {
                        $error = false;
                    }
                }
            }
            if ($error) {
                die('Access to the files on GitHub has been restricted. The major version change check failed. ' .
                    'Check yourself at https://github.com/totumonline/totum-mit/blob/master/totum/common/Totum.php ' .
                    'Use --force if you are sure about the update.');
            }
        }

        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            passthru('git pull origin master && php -f composer.phar self-update --2 && php -f composer.phar install --no-dev && bin/totum schemas-update');
        } else {
            passthru('git pull origin master && php -f composer.phar self-update --2 && php -f composer.phar install --no-dev && bin/totum schema-update');
        }

        return 0;
    }
}
