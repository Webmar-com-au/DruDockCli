<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DrushConfigImportCommand.
 */

namespace Docker\Drupal\Command\Drush;

use Docker\Drupal\Application;
use Docker\Drupal\Extension\ApplicationContainerExtension;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Docker\Drupal\Style\DruDockStyle;

/**
 * Class DrushConfigImportCommand
 *
 * @package Docker\Drupal\Command
 */
class DrushConfigImportCommand extends Command {

  protected function configure() {
    $this
      ->setName('drush:cim')
      ->setAliases(['dcim'])
      ->setDescription('Run drush config-import ')
      ->setHelp("This command will import config from a config directory.")
      ->addArgument('label', InputArgument::OPTIONAL, "A config directory label (i.e. a key in \$config_directories array in settings.php). Defaults to 'sync'")
      ->addOption('preview', 'p', InputOption::VALUE_OPTIONAL, "Preview config")
      ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'Config source')
      ->addOption('partial', 'P', InputOption::VALUE_OPTIONAL, 'Import configuration; do not remove missing configuration');
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $application = new Application();
    $container_application = new ApplicationContainerExtension();

    $label = $input->getArgument('label');
    $options = $input->getOptions();

    $io = new DruDockStyle($input, $output);

    if (!$config = $application->getAppConfig($io)) {
      $io->error('No config found. You\'re not currently in an Drupal APP directory');
      return;
    }
    else {
      $appname = $config['appname'];
    }

    switch ($config['apptype']) {
      case 'D8':
        $cmd = implode(' ', array_merge(['config-import', $label], $options));
        break;
      case 'D7':
          $io->error('This command is only available for D8');
        break;
      default:
        $io->error('You\'re not currently in an Drupal APP directory');
        return;
    }

    $io->section('PHP ::: drush ' . $cmd);

    if ($container_application->checkForAppContainers($appname, $io)) {
      $command = $container_application->getComposePath($appname, $io) . ' exec -T php drush ' . $cmd;
      $application->runcommand($command, $io);
    }
  }

}
