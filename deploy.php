<?php
namespace Deployer;

require 'recipe/common.php';

// Configuration
set('repository', 'git@github.com:deployphp/deployer.org.git');
set('shared_files', ['config.ini']);
set('shared_dirs', [
    'logs',
    'repos',
    'releases',
    'public/assets'
]);
set('writable_dirs', ['logs']);


// Servers
host('deployer.org')->set('deploy_path', '/home/elfet/deployer.org');

// Tasks
desc('npm install');
task('npm:install', function () {
    $releases = get('releases_list');

    if (isset($releases[1])) {
        if(run("if [ -d {{deploy_path}}/releases/{$releases[1]}/node_modules ]; then echo 'true'; fi")->toBool()) {
            run("cp --recursive {{deploy_path}}/releases/{$releases[1]}/node_modules {{release_path}}");
        }
    }

    run("cd {{release_path}} && npm install");
});

desc('Build js/css');
task('build',function () {
    run('cd {{release_path}} && node node_modules/.bin/gulp build');
});

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
]);
after('deploy', 'success');
