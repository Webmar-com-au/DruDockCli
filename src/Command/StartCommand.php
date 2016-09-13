<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Filesystem\Filesystem;
use Docker\Drupal\Style\DockerDrupalStyle;

/**
 * Class DemoCommand
 * @package Docker\Drupal\Command
 */
class StartCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('docker:start')
            ->setAliases(['start'])
            ->setDescription('Start APP containers')
            ->setHelp("This command will start all containers for the current APP via the docker-compose.yml file.")
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $application = $this->getApplication();
        $io = new DockerDrupalStyle($input, $output);
        $io->section("STARTING CONTAINERS");

        $fs = new Filesystem();
        if(!$fs->exists('docker-compose.yml')){
            $io->warning("docker-compose.yml : Not Found");
            return;
        }

        $command = 'docker-compose start 2>&1';
        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        $out = $process->getOutput();
        $io->info($out);
    }


}