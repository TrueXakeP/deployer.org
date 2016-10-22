<?php
require 'recipe/common.php';

// Set configurations
set('repository', 'git@github.com:deployphp/deployer.org.git');
set('shared_files', ['config.ini']);
set('shared_dirs', [
    'logs',
    'repos',
    'releases'
]);
set('writable_dirs', ['logs']);

// Configure servers
server('production', 'deployer.org')
    ->user('elfet')
    ->identityFile()
    ->env('deploy_path', '/home/elfet/deployer.org');

/**
 * npm install.
 */
task('npm:install', function () {
    $releases = env('releases_list');

    if (isset($releases[1])) {
        if(run("if [ -d {{deploy_path}}/releases/{$releases[1]}/node_modules ]; then echo 'true'; fi")->toBool()) {
            run("cp --recursive {{deploy_path}}/releases/{$releases[1]}/node_modules {{release_path}}");
        }
    }

    run("cd {{release_path}} && npm install");
});

/**
 * Build js/css.
 */
task('build',function () {
    run('cd {{release_path}} && node node_modules/.bin/gulp build');
});

/**
 * Restart php-fpm on success deploy.
 */
task('php-fpm:restart', function () {
    run('sudo service php5-fpm reload');
})->desc('Restart PHP-FPM service');

/**
 * Main task
 */
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
])->desc('Deploy your project');

after('deploy', 'success');
