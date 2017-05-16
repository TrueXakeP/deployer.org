<?php
namespace Deployer;

require 'recipe/common.php';
require 'recipe/npm.php';
//require 'repos/recipes/recipe/slack.php';

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

set('slack', [
    'access_token' => 'xoxp-162408975313-162379416432-167035038322-6a7e439606aa55906f0e31a329b39907',
]);

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

desc('Publish docs');
task('docs:publish', function () {
    cd(__DIR__ . '/repos/docs');
    run('git checkout published');
    run('git pull --rebase origin published');
    run('git merge master --ff');
    run('git push origin published');
    run('git checkout master');
})->local();