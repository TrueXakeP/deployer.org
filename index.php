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

        return new Response("Documentation updated successfully.\n\n" . $process->getOutput(), Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    return new Response('Documentation was not updated.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
});


// Auto update deployer.phar on GitHub WebHook.
$app->post('update/deployer', function (Request $request) use ($app) {
    $event = $request->headers->get('X-Github-Event');
    $payload = $request->attributes->get('payload');

    if ($event === 'create' && $payload['ref_type'] === 'tag') {

        file_put_contents($app['schedule'], "update-deployer\n", FILE_APPEND);

        return new Response('Schedule task to update deployer created.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    return new Response('', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']);
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
$app->get('/{stable}deployer.phar', function (Request $request, $stable) use ($app) {
    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $manifest = json_decode(file_get_contents($file->getPathname()), true);

    // Find latest stable version or unstable if $stable variable not equals empty string.

    $latest  = new \Herrera\Version\Version();
    $builder = new \Herrera\Version\Builder();

    foreach ($manifest as $row) {
        $version = $builder->importString($row['version'])->getVersion();

        if ($stable === '' && !$version->isStable()) {
            continue;
        }

        if (\Herrera\Version\Comparator::isLessThan($latest, $version)) {
            $latest = $version;
        }
    }

    return new \Symfony\Component\HttpFoundation\RedirectResponse("/releases/v$latest/deployer.phar");
})
    ->assert('stable', '(beta\/)?')
    ->value('stable', '');

// Download page
$app->get('/download', function (Request $request) use ($app) {
    // Getting the manifest data
    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');
    $manifestData = null;

    $response = new Response();

    // Caching setup
    $response->setPublic();

    // Handling the manifest data
    if ($file->isReadable()) {
        // If there were no new releases, then just return with the last on
        $response->setLastModified(new DateTime('@' . $file->getMTime()));
        if ($response->isNotModified($request)) {
            return $response;
        }

        $manifestData = json_decode(file_get_contents($file->getPathname()), true);

        // Sorting the versions in descending order
        $builder = new \Herrera\Version\Builder();
        uasort($manifestData, function($a, $b) use ($builder) {
            if ($a['version'] === $b['version']) {
                return 0;
            }

            return \Herrera\Version\Comparator::isLessThan(
                $builder->importString($a['version'])->getVersion(),
                $builder->importString($b['version'])->getVersion()
            );
        });

        // Adding the "highlighted" bool value to every version.
        // Only the latest stable release is highlighted in every major version.
        $prevMajorVersion = null;
        foreach ($manifestData as $key => $data) {
            $manifestData[$key]['highlighted'] = false;

            $version = $builder->importString($data['version'])->getVersion();
            if (
                $version->getMajor() !== $prevMajorVersion &&
                $version->isStable()
            ) {
                $manifestData[$key]['highlighted'] = true;
                $prevMajorVersion = $version->getMajor();
            }
        }
    }

    // Rendering the template, setting up the response
    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(
        // I couldn't get myself to use that `render()` function... ><
        $app['twig']->render('download.twig', [
            'manifest_data' => $manifestData,
        ]
    ));

    return $response;
});

// Show pages. This route must be last.
// Cache rendered response with validate file modify time.
$app->get('/{page}', function (Request $request, $page) use ($app) {
    $response = new Response();
    $response->setPublic();

    $templateFile = new SplFileInfo($app['twig.path'] . '/' . $page . '.twig');

    if (!$templateFile->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $templateParams = [];
    if ($page === 'index') {
        $manifestFile = new SplFileInfo($app['releases.path'] . '/manifest.json');

        // Getting the latest stable release
        $manifestData = json_decode(file_get_contents($manifestFile->getPathname()), true);

        $stable = '';
        $latest = new \Herrera\Version\Version();
        $builder = new \Herrera\Version\Builder();

        foreach ($manifestData as $versionData) {
            $version = $builder->importString($versionData['version'])->getVersion();

            if ($stable === '' && !$version->isStable()) {
                continue;
            }

            if (\Herrera\Version\Comparator::isLessThan($latest, $version)) {
                $latest = $version;
            }
        }

        $templateParams['latest_deployer_version'] = $latest;

        $templateLastModified = new DateTime('@' . $templateFile->getMTime());
        $manifestLastModified = new DateTime('@' . $manifestFile->getMTime());

        $response->setLastModified($templateLastModified > $manifestLastModified ? $templateLastModified : $manifestLastModified);
        if ($response->isNotModified($request)) {
            return $response;
        }
    } else {
        $response->setLastModified(new DateTime('@' . $templateFile->getMTime()));
        if ($response->isNotModified($request)) {
            return $response;
        }
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(render($page . '.twig', $templateParams));

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
