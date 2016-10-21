<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @var \Silex\Application $app */
$controller = $app['controllers_factory'];

// Show pages. This route must be last.
// Cache rendered response with validate file modify time.
$controller->get('/{page}', function (Request $request, $page) use ($app) {
    $response = new Response();
    $response->setPublic();

    $templateFile = new SplFileInfo($app['pages.path'] . '/' . $page . '.twig');

    if (!$templateFile->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $templateParams = [
        'url' => url("/$page")
    ];
    if ($page === 'index') {
        $templateParams['url'] = url("");
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

return $controller;
