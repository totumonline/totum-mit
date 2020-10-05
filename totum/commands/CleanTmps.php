<?php


namespace totum\commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use totum\config\Conf;

class CleanTmps extends Command
{
    protected function configure()
    {
        $this->setName('clean-tmp-dir')
            ->setDescription('Clean tmp dir. Set in crontab one time in 10 minutes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $Conf = new Conf();
        $dir = $Conf->getTmpDir();
        if (is_dir($dir)) {
            if ($dh = opendir($dir)) {
                while (($file = readdir($dh)) !== false) {
                    if (is_file($fName = $dir . '/' . $file) && fileatime($fName) < time() - 360 * 20) {
                        unlink($fName);
                    }
                }
                closedir($dh);
            }
        }
    }
}
