<?php
namespace Deployer;

require 'recipe/drupal8.php';

// Project name
set('application', 'a15-drupal');

// Project repository
set('repository', 'git@github.com:scoutinga15/a15-drupal.git');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys
add('shared_files', [
  '.env'
]);
//add('shared_dirs', [
//  'web/sites/default/files'
//]);

// Writable dirs by web server
add('writable_dirs', [
  'config',
  'web/sites/default/files',
  'web/themes',
  'web/modules',
]);

// Hosts
host('159.65.196.174')
  ->user('root')
  ->port(22)
  ->forwardAgent(true)
  ->multiplexing(true)
  ->addSshOption('UserKnownHostsFile', '/dev/null')
  ->addSshOption('StrictHostKeyChecking', 'no')
  ->set('deploy_path', '/mnt/volume_ams3_01/a15')
  ->set('writable_mode', 'chmod');

set('execute-docker-compose', 'cd {{release_path}} && docker-compose -pa15');

// Tasks
task('dc:drush-cr', function () {
    run('{{ execute-docker-compose }} exec -T drupal drush cr');
});

task('dc:drush-cim', function () {
  run('{{ execute-docker-compose }} exec -T drupal drush cim -y');
});

task ('dc:build', function () {
  run('{{ execute-docker-compose }} build');
});

task ('dc:up', function () {
  run('{{ execute-docker-compose }} up -d');
});

task ('dc:down', function () {
  run('{{ execute-docker-compose }} down -v');
});

task('deploy', [
  //'deploy:prepare',
  'deploy:release',
  'deploy:update_code',
  'deploy:shared',
  'deploy:writable',
  // 'deploy:vendors',
  'dc:build',
  'dc:up',
  'dc:drush-cim',
  'dc:drush-cr',
  'deploy:symlink',
  'cleanup'
]);

// [Optional] if deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

# Shared files location
# /mnt/volume_ams3_01/a15/drupal-files/files
