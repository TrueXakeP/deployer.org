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
    'twig.path' => __DIR__ . '/pages',
));

// Set paths for Docs local repository.
$app['docs.path'] = __DIR__ . '/documentation';

// Get docs pages from markdown source, manually parse `.md` links.
// Cache rendered response with validate file modify time.
$app->get('/docs/{page}', function ($page, Request $request) use ($app) {
    $response = new Response();
    $response->setPublic();

    $file = new SplFileInfo($app['docs.path'] . '/' . $page . '.md');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->setLastModified(new DateTime('@' . $file->getMTime()));
    if ($response->isNotModified($request)) {
        return $response;
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    $parse = function ($content) use ($request) {
        static $parsedown = null;

        if (null === $parsedown) {
            $parsedown = new Parsedown();
        }

        // Replace links urls.
        $content = preg_replace('/\((.*?)\.md\)/', '(' . $request->getBaseUrl() . '/docs/$1)', $content);

        return $parsedown->text($content);
    };

    $content = file_get_contents($file->getPathname());

    // Get title from first header.
    if (preg_match('/#\s*(.*)/u', $content, $matches)) {
        $title = $matches[1];
    } else {
        $title = '';
    }

    $content = $parse($content);

    // Get docs navigation from README.md
    $menu = $parse(file_get_contents($app['docs.path'] . '/README.md'));

    $response->setContent(render('docs.twig', [
        'page' => $page,
        'title' => $title,
        'menu' => $menu,
        'content' => $content,
    ]));

    return $response;
})
    ->assert('page', '[\w/-]+')
    ->value('page', 'getting-started');


// Check GitHub signature
$app->before(function (Request $request) use ($app) {
    if ($request->isMethod('POST') && preg_match('/^\/update/', $request->getRequestUri())) {
        $secret = $app['github_secret'];
        $hubSignature = $request->headers->get('X-Hub-Signature');

        list($algo, $hash) = explode('=', $hubSignature, 2);

        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        $payloadHash = hash_hmac($algo, $payload, $secret);

        if ($hash === $payloadHash) {
            $request->attributes->set('payload', $data);
        } else {
            return new Response('Payload hash does not match hub signature.', Response::HTTP_FORBIDDEN);
        }
    }
}, Silex\Application::EARLY_EVENT);

// Auto update docs on GitHub Webhook.
$app->post('update/docs', function (Request $request) use ($app) {
    $event = $request->headers->get('X-Github-Event');
    $payload = $request->attributes->get('payload');
    $output = [];

    if (
        (
            $event === 'pull_request' &&
            $payload['action'] === 'closed' &&
            $payload['pull_request']['merged']
        ) || (
            $event === 'push'
        )
    ) {
        exec('cd ' . $app['docs.path'] . ' && git pull https://github.com/deployphp/docs.git master 2>&1', $output);
    }

    return new Response(join("\n", $output), Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});

// Show pages. This route must be last.
// Cache rendered response with validate file modify time.
$app->get('/{page}', function (Request $request, $page) use ($app) {
    $response = new Response();
    $response->setPublic();

    $file = new SplFileInfo($app['twig.path'] . '/' . $page . '.twig');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->setLastModified(new DateTime('@' . $file->getMTime()));
    if ($response->isNotModified($request)) {
        return $response;
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(render($page . '.twig'));

    return $response;
})
    ->value('page', 'index');


/****************************\
|     Start application      |
\****************************/

if ($app['cache']) {
    $app['http_cache']->run();
} else {
    $app->run();
}

/**
 * Render file with twig.
 *
 * @param string $file
 * @param array $params
 * @return string
 */
function render($file, $params = [])
{
    global $app;
    return $app['twig']->render($file, $params);
}
