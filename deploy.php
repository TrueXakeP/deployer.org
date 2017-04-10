<?php
namespace Deployer;

require 'recipe/common.php';
require 'npm.php';

// Configuration

set('repository', 'git@github.com:deployphp/deployer.org.git');
set('shared_files', [
    'config.ini'
]);
set('shared_dirs', [
    'logs',
    'repos',
    'releases',
    'public/assets'
]);
set('writable_dirs', ['logs']);

// Hosts

host('deployer.org')
    ->set('deploy_path', '/home/elfet/deployer.org');


// Tasks

desc('Build js/css');
task('build', 'cd {{release_path}} && node node_modules/.bin/gulp build');

desc('Restart PHP-FPM service');
task('php-fpm:restart', function () {
    run('sudo service php7.0-fpm reload');
});

desc('Deploy your project');
task('deploy', [
    'deploy:prepare',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:vendors',
    'npm:install',
    'build',
    'deploy:symlink',
    'php-fpm:restart',
    'cleanup',
    'success',
]);
