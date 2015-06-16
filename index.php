<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__ . '/vendor/autoload.php';

// Init app with parameters from config.ini
$app = new Silex\Application(parse_ini_file(is_readable('config.ini') ? 'config.ini' : 'config.ini.dist'));

// Register HTTP Cache and Twig
$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__ . '/cache/',
    'http_cache.esi' => null,
));
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => [__DIR__ . '/pages', __DIR__ . '/includes'],
));

// set path for pages.
$app['pages.path'] = __DIR__ . '/pages';

// Set path for docs local repository.
$app['docs.path'] = __DIR__ . '/documentation';

// Set path for releases.
$app['releases.path'] = __DIR__ . '/releases';

// Set cli file.
$app['cli'] = __FILE__;

// Set schedule file.
$app['schedule'] = __DIR__ . '/logs/schedule.log';


$app->mount('/', include __DIR__ . '/controllers/update.php');
$app->mount('/', include __DIR__ . '/controllers/docs.php');
$app->mount('/', include __DIR__ . '/controllers/download.php');
$app->mount('/', include __DIR__ . '/controllers/pages.php'); // Must be last, because match everything.


#########################
#   Start application   #
#########################

if (php_sapi_name() == "cli") {
    require __DIR__ . '/cli.php';
} else {
    if ($app['cache']) {
        $app['http_cache']->run();
    } else {
        $app->run();
    }
}


#########################
#   Helper functions    #
#########################


/**
 * Render file with twig.
 *
 * @param string $file
 * @param array $params
 * @return string
 */
function render($file, $params = [])
{
    global $app; // Yes, I know that =)
    return $app['twig']->render($file, $params);
}
