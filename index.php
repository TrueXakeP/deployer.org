<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
require __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application(parse_ini_file(is_readable('config.ini') ? 'config.ini' : 'config.ini.dist'));

$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__ . '/cache/',
    'http_cache.esi' => null,
));



$app['http_cache']->run();
