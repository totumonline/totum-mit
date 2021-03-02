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
            ->setDescription('update from git origin master && composer && schema(s)-update');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Conf = new Conf();

        if (is_callable([$Conf, 'setHostSchema'])) {
            passthru('git pull origin master && php -f composer.phar install --no-dev && bin/totum schemas-update');
        } else {
            passthru('git pull origin master && php -f composer.phar install --no-dev && bin/totum schema-update');
        }

        return 0;
    }
}
