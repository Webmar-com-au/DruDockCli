<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command\Prod;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Docker\Drupal\Style\DockerDrupalStyle;

/**
 * Class ProdUpdateCommand
 * @package Docker\Drupal\Command\redis
 */
class ProdUpdateCommand extends Command {
  protected function configure() {
    $this
      ->setName('prod:update')
      ->setDescription('Rebuild app and deploy latest code into app containers')
      ->setHelp("Deploy host /app code into new/latest build [dockerdrupal prod:update]");
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $application = $this->getApplication();
    $io = new DockerDrupalStyle($input, $output);

    $io->section("PROD ::: Update");

    if ($config = $application->getAppConfig($io)) {
      $appname = $config['appname'];
      $appreqs = $config['reqs'];
    }

    if (isset($appreqs) && !$appreqs == 'Prod') {
      $io->warning("This is not a production app.");
      return;
    }

    if (isset($appreqs) && $appreqs == 'Prod') {

      if ($application->checkForAppContainers($appname, $io)) {

        $date =  date('Y-m-d--H-i-s');
        $system_appname = strtolower(str_replace(' ', '', $appname));
        $projectname = $system_appname . '--' . $date;

        // RUN APP BUILD.
        $command = 'docker-compose -f ./docker_' . $system_appname . '/docker-compose.yml --project-name=' . $projectname . ' build --no-cache';
        $application->runcommand($command, $io);

        // RUN APP.
        $command = 'docker-compose -f ./docker_' . $system_appname . '/docker-compose.yml --project-name=' . $projectname . ' up -d app';
        $application->runcommand($command, $io);

        // START PROJECT.
        $command = 'docker-compose -f ./docker_' . $system_appname . '/docker-compose.yml --project-name=' . $projectname . ' up -d';
        $application->runcommand($command, $io);

        $previous_build = end($config['builds']);
        $previous_build_projectname = $system_appname . '--' . $previous_build;
        // STOP PREVIOUS BUILD.
        $command = 'docker-compose -f ./docker_' . $system_appname . '/docker-compose.yml --project-name=' . $previous_build_projectname . ' down -v';
        $application->runcommand($command, $io);

        $config['builds'][] = $date;
        $application->setAppConfig($config, $appname, $io);

      }
    }
  }
}
