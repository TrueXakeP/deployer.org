<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application(parse_ini_file(is_readable('config.ini') ? 'config.ini' : 'config.ini.dist'));

$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => __DIR__ . '/cache/',
    'http_cache.esi' => null,
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/pages',
));

$app['docs.path'] = __DIR__ . '/documentation';

$app->get('/', function (Request $request) use ($app) {
    $response = new Response();
    $response->setPublic();

    $lastModified = new DateTime();
    $lastModified->setTimestamp(filemtime($app['twig.path'] . '/index.twig'));
    $response->setLastModified($lastModified);

    if ($response->isNotModified($request)) {
        return $response;
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(render('index.twig'));

    return $response;
});


$app->get('/docs/{page}', function ($page, Request $request) use ($app) {
    $response = new Response();
    $response->setPublic();

    $file = new SplFileInfo($app['docs.path'] . '/' . $page . '.md');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $lastModified = new DateTime();
    $lastModified->setTimestamp($file->getMTime());
    $response->setLastModified($lastModified);

    if ($response->isNotModified($request)) {
        return $response;
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    $parser = new Parsedown();

    $content = file_get_contents($file->getPathname());
    if (preg_match('/#\s*(.*)/u', $content, $matches)) {
        $title = $matches[1];
    } else {
        $title = '';
    }
    $content = preg_replace('/\((.*?)\.md\)/', '(' . $request->getBaseUrl() . '/docs/$1)', $content);
    $content = $parser->text($content);

    $menu = file_get_contents($app['docs.path'] . '/README.md');
    $menu = preg_replace('/\((.*?)\.md\)/', '(' . $request->getBaseUrl() . '/docs/$1)', $menu);
    $menu = $parser->text($menu);
    $menu = str_replace('<ul>', '<ul id="nav" class="nav nav-stacked">', $menu);

    $response->setContent(render('docs.twig', [
        'title' => $title,
        'menu' => $menu,
        'content' => $content,
    ]));

    return $response;
})
    ->assert('page', '[\w/-]+')
    ->value('page', 'getting-started');
    

$app->post('update/docs', function (Request $request) {
    $secret = $app['github_secret'];
    $hubSignature = $request->headers['X-Hub-Signature'];
 
    list($algo, $hash) = explode('=', $hubSignature, 2);
 
    $payload = file_get_contents('php://input');
    $data    = json_decode($payload, true);
 
    $payloadHash = hash_hmac($algo, $payload, $secret);
 
    if ($hash !== $payloadHash) {
        return new Response('', 500)
    }
});    


if ($app['cache']) {
    $app['http_cache']->run();
} else {
    $app->run();
}

/**
 * @param string $file
 * @param array $params
 * @return string
 */
function render($file, $params = [])
{
    global $app;
    return $app['twig']->render($file, $params);
}
