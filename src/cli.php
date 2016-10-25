<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

if (!$app instanceof Silex\Application) {
    exit(1);
}

$console = new \Symfony\Component\Console\Application('deployer.org');

$updateDeployerCommand = new \Symfony\Component\Console\Command\Command('update:deployer');
$updateDeployerCommand->setCode(function ($input, $output) use ($app) {
    $releases = $app['releases.path'];

    $run = function ($command) use ($releases, $output) {
        $process = new \Symfony\Component\Process\Process("cd $releases && $command");
        $process->setTimeout(null);
        $process->mustRun();
        $out = $process->getOutput();
        $output->write($out);
        return $out;
    };

    // Clear to be sure. 
    $run("rm -rf $releases/deployer");

    // Clone deployer to deployer dir in releases path.
    $run('git clone https://github.com/deployphp/deployer.git deployer 2>&1');

    // Get list of tags.
    $tags = $run('cd deployer && git tag');

    $manifest = [];

    // Read manifest if it is exist.
    if (is_readable("$releases/manifest.json")) {
        $manifest = json_decode(file_get_contents("$releases/manifest.json"), true);
    }

    // For all tags.
    foreach (explode("\n", $tags) as $tag) {
        if (empty($tag)) {
            continue;
        }

        // Skip if tag already released.
        if (is_dir($dir = "$releases/$tag")) {
            continue;
        }

        $output->write("Building Phar for $tag tag.\n");

        try {
            $version = str_replace('v', '', $tag); // Set version from tag without leading "v".
            
            // Checkout tag, update vendors, run build tool.
            $run("cd deployer && git checkout tags/$tag --force 2>&1");

            // Require what Deployer suggests. 
            $run('cd deployer && /usr/local/bin/composer require herzult/php-ssh:~1.0 --ignore-platform-reqs');

            // Install vendors.
            $run('cd deployer && /usr/local/bin/composer install --no-dev --verbose --prefer-dist --optimize-autoloader --no-progress --no-scripts --ignore-platform-reqs');

            // And build.
            $run('cd deployer && php build -v="' . $version . '"');

            // Create new dir and copy phar there.
            mkdir($dir);
            copy("$releases/deployer/deployer.phar", "$dir/deployer.phar");

            // Generate sha1 sum and put it to manifest.json
            $newPharManifest = [
                'name' => 'deployer.phar',
                'sha1' => sha1_file("$dir/deployer.phar"),
                'url' => "https://deployer.org/releases/$tag/deployer.phar",
                'version' => $version, 
            ];

            // Check if this version already in manifest.json.
            $alreadyExistVersion = null;
            foreach ($manifest as $i => $old) {
                if ($old['version'] === $version) {
                    $alreadyExistVersion = $i;
                }
            }

            // Save or update.
            if (null === $alreadyExistVersion) {
                $manifest[] = $newPharManifest;
            } else {
                $manifest[$alreadyExistVersion] = $newPharManifest;
            }
        } catch (Exception $exception) {
            $output->write("Exception `" . get_class($exception) . "` caught.\n");
            $output->write($exception->getMessage());

            // Remove this tag dir.
            $run("rm -rf $releases/$tag");
        }
    }

    // Write manifest to manifest.json.
    file_put_contents("$releases/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));

    // Remove deployer dir.
    $run("rm -rf $releases/deployer");
});
$console->add($updateDeployerCommand);

$updateDocumentationCommand = new \Symfony\Component\Console\Command\Command('update:documentation');
$updateDocumentationCommand->setCode(function ($input, $output) use ($app) {
    $run = function ($command) use ($app) {
        $process = new \Symfony\Component\Process\Process('cd ' . $app['docs.path'] . ' && ' . $command);
        $process->mustRun();
        return $process->getOutput();
    };

    if (is_file($app['docs.path'] . '/README.md')) {
        $output->write($run('git reset --hard origin/master'));
        $output->write($run('git pull https://github.com/deployphp/docs.git master 2>&1'));
    } else {
        $output->write($run('git clone --depth 1 https://github.com/deployphp/docs.git . 2>&1'));
    }
});
$console->add($updateDocumentationCommand);


$updateRecipesCommand = new \Symfony\Component\Console\Command\Command('update:recipes');
$updateRecipesCommand->setCode(function ($input, $output) use ($app) {
    $run = function ($command) use ($app) {
        $process = new \Symfony\Component\Process\Process('cd ' . $app['recipes.path'] . ' && ' . $command);
        $process->mustRun();
        return $process->getOutput();
    };

    if (is_file($app['recipes.path'] . '/README.md')) {
        $output->write($run('git reset --hard origin/master'));
        $output->write($run('git pull https://github.com/deployphp/recipes.git master 2>&1'));
    } else {
        $output->write($run('git clone --depth 1 https://github.com/deployphp/recipes.git . 2>&1'));
    }
});
$console->add($updateRecipesCommand);


$scheduleCommand = new \Symfony\Component\Console\Command\Command('schedule');
$scheduleCommand->setCode(function ($input, $output) use ($app) {
    $commands = explode("\n", file_get_contents($app['schedule']));

    while (count($commands) > 0) {
        $command = array_shift($commands);
            
        if (in_array($command, ['update:deployer', 'update:documentation', 'update:recipes'], true)) {
            
            // Remove same commands.
            $commands = array_filter($commands, function ($i) use ($command) {
                return $i !== $command;
            });
            
            $output->write("Running command $command.\n");
            
            $process = new \Symfony\Component\Process\Process("php $app[cli] $command");
            $process->run();
            file_put_contents($app['logs.path'] . '/' . $command . '.log', $process->getOutput());
        }
    }
    
    file_put_contents($app['schedule'], implode("\n", $commands));
});
$console->add($scheduleCommand);
