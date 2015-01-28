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

// Set path for docs local repository.
$app['docs.path'] = __DIR__ . '/documentation';

// Set path for releases.
$app['releases.path'] = __DIR__ . '/releases';

// Set cli file.
$app['cli'] = __FILE__;

// Set schedule file.
$app['schedule'] = __DIR__ . '/logs/schedule.log';

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


// Auto update docs on GitHub WebHook.
$app->post('update/docs', function (Request $request) use ($app) {
    $event = $request->headers->get('X-Github-Event');
    $payload = $request->attributes->get('payload');

    if (
        (
            $event === 'pull_request' &&
            $payload['action'] === 'closed' &&
            $payload['pull_request']['merged']
        ) || (
            $event === 'push'
        )
    ) {

        $process = new \Symfony\Component\Process\Process('php ' . __FILE__ . ' update-documentation');
        $process->run();

        return new Response("Documentation updated successful.\n\n" . $process->getOutput(), Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    return new Response('Documentation was not updated.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});


// Auto update deployer.phar on GitHub WebHook.
$app->post('update/deployer', function (Request $request) use ($app) {
    $event = $request->headers->get('X-Github-Event');
    $payload = $request->attributes->get('payload');

    if ($event === 'create' && $payload['ref_type'] === 'tag') {

        if (!is_writable($app['releases.path'])) {
            return new Response("Release path does not writable.", Response::HTTP_I_AM_A_TEAPOT);
        }

        file_put_contents($app['schedule'], "update-deployer\n", FILE_APPEND);

        return new Response('Deployer updated successful.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
        
    }

    return new Response('Deployer was not updated.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});


// Return manifest.
$app->get('/manifest.json', function (Request $request) use ($app) {
    $response = new Response();
    $response->setPublic();

    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->setLastModified(new DateTime('@' . $file->getMTime()));
    if ($response->isNotModified($request)) {
        return $response;
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setCharset('UTF-8');
    $response->setContent(file_get_contents($file->getPathname()));

    return $response;
});


// Return latest release phar.
$app->get('/deployer.phar', function (Request $request) use ($app) {
    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $manifest = json_decode(file_get_contents($file->getPathname()), true);
    $latest = array_pop($manifest);

    return new \Symfony\Component\HttpFoundation\RedirectResponse("/releases/v$latest[version]/deployer.phar");
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
