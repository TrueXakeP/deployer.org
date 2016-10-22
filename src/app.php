<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/helpers.php';

#########################
#     Configuration     #
#########################


// Init app with parameters from config.ini
$app = new Silex\Application(
    parse_ini_file(
        is_readable(__DIR__ . '/../config.ini') ?
            __DIR__ . '/../config.ini' :
            __DIR__ . '/../config.ini.dist'
    )
);

// Register HTTP Cache and Twig
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => [__DIR__ . '/pages', __DIR__ . '/includes'],
));
$app->extend('twig', function ($twig, $app) {
    $twig->addFunction(new \Twig_SimpleFunction('asset', function ($name) use ($app) {
        if (empty($app['rev_manifest'])) {
            return "/$name";
        } else {
            return "/" . $app['rev_manifest'][$name];
        }
    }));
    return $twig;
});

$app['base_url'] = 'https://deployer.org';

// Set path for pages.
$app['pages.path'] = __DIR__ . '/pages';

// Set path for docs local repository.
$app['docs.path'] = __DIR__ . '/../repos/docs';

// Set path for recipes local repository.
$app['recipes.path'] = __DIR__ . '/../repos/recipes';

// Set path for releases.
$app['releases.path'] = __DIR__ . '/../releases';

// Set cli file.
$app['cli'] = __FILE__;

// Set schedule file.
$app['schedule'] = __DIR__ . '/../logs/schedule.log';

// Revision manifest
$app['rev_manifest'] = function () {
    $filename = __DIR__ . '/../rev-manifest.json';
    if (is_readable($filename)) {
        return json_decode(file_get_contents($filename), true);
    } else {
        return [];
    }
};

#########################
#   Mount controller    #
#########################

$app->mount('/', include __DIR__ . '/controllers/update.php');
$app->mount('/', include __DIR__ . '/controllers/docs.php');
$app->mount('/', include __DIR__ . '/controllers/recipes.php');
$app->mount('/', include __DIR__ . '/controllers/download.php');
$app->mount('/', include __DIR__ . '/controllers/sitemap.php');
$app->mount('/', include __DIR__ . '/controllers/index.php');
$app->mount('/', include __DIR__ . '/controllers/pages.php'); // Must be last, because match everything.
