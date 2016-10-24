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

$controller->get('', function (Request $request) use ($app) {
    $response = new Response();
    //$response->setPublic();
    $templateFile = new SplFileInfo($app['pages.path'] . '/index.twig');

    $templateParams = [
        'url' => url('')
    ];

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

    /*
    $response->setLastModified($templateLastModified > $manifestLastModified ? $templateLastModified : $manifestLastModified);
    if ($response->isNotModified($request)) {
        return $response;
    }
    */

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(render('index.twig', $templateParams));

    return $response;
});

return $controller;
