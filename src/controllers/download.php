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

// Return manifest.
$controller->get('/manifest.json', function (Request $request) use ($app) {
    $response = new Response();

    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->headers->set('Content-Type', 'application/json');
    $response->setCharset('UTF-8');
    $response->setContent(file_get_contents($file->getPathname()));

    return $response;
});


// Return latest release phar.
$controller->get('/{stable}deployer.phar', function (Request $request, $stable) use ($app) {
    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $manifest = json_decode(file_get_contents($file->getPathname()), true);

    // Find latest stable version or unstable if $stable variable not equals empty string.

    $latest = new \Herrera\Version\Version();
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
$controller->get('/download', function (Request $request) use ($app) {
    // Getting the manifest data
    $file = new SplFileInfo($app['releases.path'] . '/manifest.json');
    $manifestData = null;
    $latestVersion = new \Herrera\Version\Version();
    $latest = null;

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
        uasort($manifestData, function ($a, $b) use ($builder) {
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

            if (\Herrera\Version\Comparator::isLessThan($latestVersion, $version)) {
                $latestVersion = $version;
                $latest = $data;
            }
        }
    }

    // Rendering the template, setting up the response
    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(
        render('download.twig', [
                'url' => url('/download'),
                'manifest_data' => $manifestData,
                'latest' => $latest,
            ]
        )
    );

    return $response;
});

return $controller;
