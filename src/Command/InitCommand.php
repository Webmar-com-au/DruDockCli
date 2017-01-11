<?php

/**
 * @file
 * Contains \Docker\Drupal\Command\DemoCommand.
 */

namespace Docker\Drupal\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Process\Process;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Docker\Drupal\Style\DockerDrupalStyle;
use Symfony\Component\Yaml\Yaml;


/**
 * Class DemoCommand
 * @package Docker\Drupal\ContainerAwareCommand
 */
class InitCommand extends ContainerAwareCommand
{
  protected function configure()
  {
    $this
      ->setName('env:init')
      ->setAliases(['env'])
      ->setDescription('Fetch and build DockerDrupal containers')
      ->setHelp('This command will fetch the specified DockerDrupal config, download and build all necessary images.  NB: The first time you run this command it will need to download 4GB+ images from DockerHUB so make take some time.  Subsequent runs will be much quicker.')
      ->addArgument('appname', InputArgument::OPTIONAL, 'Specify NAME of application to build [app-dd-mm-YYYY]')
      ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Specify app version [D7,D8,DEFAULT]')
      ->addOption('reqs', 'r', InputOption::VALUE_OPTIONAL, 'Specify app requirements [Basic,Full]')
      ->addOption('appsrc', 's', InputOption::VALUE_OPTIONAL, 'Specify app src [New, Git]');
  }

  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $application = $this->getApplication();
    $utilRoot = $application->getUtilRoot();

    $io = new DockerDrupalStyle($input, $output);

    // check Docker is running
    $application->checkDocker($io, TRUE);

    $fs = new Filesystem();
    $date =  date('Y-m-d--H-i-s');

    // check if this folder is has APP config
    if(file_exists('.config.yml')){
      $io->error('You\'re currently in an APP directory');
      return;
    }

    $message = "If prompted, please type admin password to add '127.0.0.1 docker.dev' to /etc/hosts \n && COPY ifconfig alias.plist to /Library/LaunchDaemons/";
    $io->note($message);
    $application->addHostConfig($io);

    // GET AND SET APPNAME.
    $appname = $input->getArgument('appname');
    if (!$appname) {
      $io->title("SET APP NAME");
      $helper = $this->getHelper('question');
      $question = new Question('Enter App name [dockerdrupal_app_' . $date . '] : ', 'my-app-' . $date);
      $appname = $helper->ask($input, $output, $question);
    }

    // GET AND SET APP PREFERRED HOST.
    $host = $input->getOption('apphost');

    if (!$host) {
      // use default host http://docker.dev
      $host = 'docker.dev';
    }

    // GET AND SET APP SOURCE.
    $src = $input->getOption('appsrc');
    $available_src = array('New', 'Git');

    if ($src && !in_array($src, $available_src)) {
      $io->warning('APP SRC : ' . $src . ' not allowed.');
      $src = NULL;
    }

    if (!$src) {
      $io->info(' ');
      $io->title("SET APP SOURCE");
      $helper = $this->getHelper('question');
      $question = new ChoiceQuestion(
        'Is this app a new build or loaded from a remote GIT repository [New, Git] : ',
        $available_src,
        'New'
      );
      $src = $helper->ask($input, $output, $question);
    }

    if($src == 'Git') {
      $io->title("SET APP GIT URL");
      $helper = $this->getHelper('question');
      $question = new Question('Enter remote GIT url [https://github.com/<me>/<myapp>.git] : ');
      $gitrepo = $helper->ask($input, $output, $question);
    }

    // GET AND SET APP REQUIREMENTS.
    $reqs = $input->getOption('reqs');
    $available_reqs = array('Basic', 'Full');

    if ($reqs && !in_array($reqs, $available_reqs)) {
      $io->warning('REQS : ' . $reqs . ' not allowed.');
      $reqs = NULL;
    }

    if (!$reqs) {
      $io->info(' ');
      $io->title("SET APP REQS");
      $helper = $this->getHelper('question');
      $question = new ChoiceQuestion(
        'Select your APP reqs [basic] : ',
        $available_reqs,
        'basic'
      );
      $reqs = $helper->ask($input, $output, $question);
    }

    // GET AND SET APP TYPE.
    $type = $input->getOption('type');
    $available_types = array('DEFAULT', 'D7', 'D8');

    if ($type && !in_array($type, $available_types)) {
      $io->warning('TYPE : ' . $type . ' not allowed.');
      $type = NULL;
    }

    if (!$type) {
      $io->info(' ');
      $io->title("SET APP TYPE");
      $helper = $this->getHelper('question');
      $question = new ChoiceQuestion(
        'Select your APP type [0] : ',
        $available_types,
        '0'
      );
      $type = $helper->ask($input, $output, $question);
    }

    $system_appname = strtolower(str_replace(' ', '', $appname));

    if (!$fs->exists($system_appname)) {
      $fs->mkdir($system_appname, 0755);
      $fs->mkdir($system_appname . '/docker_' . $system_appname, 0755);
    }
    else {
      $io->error('This app already exists');
      return;
    }

    // SETUP APP CONFIG FILE.
    $config = array(
      'appname' => $appname,
      'apptype' => $type,
      'host'=> $host,
      'reqs' => $reqs,
      'appsrc' => $src,
      'repo' =>  $gitrepo ? $gitrepo : '',
      'dockerdrupal' => array('version' => $application->getVersion(), 'date' => $date),
    );

    $yaml = Yaml::dump($config);
    file_put_contents($system_appname . '/.config.yml', $yaml);

    $message = 'Fetching DockerDrupal v' . $application->getVersion();
    $io->info(' ');
    $io->note($message);

    if ($reqs == 'Basic') {
      $fs->mirror($utilRoot . '/bundles/dockerdrupal-lite/', $system_appname . '/docker_' . $system_appname);
    }

    if ($reqs == 'Full') {
      $fs->mirror($utilRoot . '/bundles/dockerdrupal/', $system_appname . '/docker_' . $system_appname);
    }

    $application->runcommand($command, $io, TRUE);

    $this->initDocker($application, $io, $system_appname);

    $message = 'DockerDrupal containers ready. Navigate to your app folder [cd ' . $system_appname . '] and build your app using ::: [dockerdrupal build:init]';
    $io->info(' ');
    $io->note($message);

  }

  /**
   * @param $application
   * @param $io
   * @param $appname
   */
  private function initDocker($application, $io, $appname) {

    if (exec('docker ps -q 2>&1', $exec_output)) {
      $dockerstopcmd = 'docker stop $(docker ps -q)';
      $application->runcommand($dockerstopcmd, $io, TRUE);
    }

    $message = 'Download and configure DockerDrupal.... This may take a few minutes....';
    $io->note($message);

    $dockerlogs = 'docker-compose -f ' . $appname . '/docker_' . $appname . '/docker-compose.yml logs -f';
    $application->runcommand($dockerlogs, $io, TRUE);

    if (exec('docker ps -q 2>&1', $exec_output)) {
      $dockercmd = 'docker kill $(docker ps -q)';
      $application->runcommand($dockercmd, $io, TRUE);
    }

    $dockercmd = 'docker-compose -f ' . $appname . '/docker_' . $appname . '/docker-compose.yml pull';
    $application->runcommand($dockercmd, $io, TRUE);
  }
}
