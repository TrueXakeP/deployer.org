<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @var \Silex\Application $app */
$controller = $app['controllers_factory'];

$controller->get('/sitemap.xml', function (Request $request) use ($app) {
    $response = new Response();
    $response->setPublic();

    $map = [];

    // Main

    $file = new SplFileInfo($app['pages.path'] . '/index.twig');
    $map[] = [
        'loc' => $app['base_url'],
        'lastmod' => date('Y-m-d', $file->getMTime()),
        'changefreq' => 'daily',
        'priority' => 1,
    ];

    // Pages

    $finder = new Finder();
    $finder
        ->name('*.twig')
        ->files()
        ->notName('index.twig')
        ->in($app['pages.path']);

    foreach ($finder as $file) {
        $map[] = [
            'loc' => $app['base_url'] . '/' . ($name = $file->getBasename('.twig')),
            'lastmod' => date('Y-m-d', $file->getMTime()),
            'changefreq' => 'weekly',
            'priority' => in_array($name, ['docs', 'recipes', 'download'], true) ? '1.0' : '0.7',
        ];
    }


    // Docs

    $finder = new Finder();
    $finder
        ->name('*.md')
        ->files()
        ->in($app['docs.path']);

    foreach ($finder as $file) {
        $map[] = [
            'loc' => $app['base_url'] . '/docs/' . $file->getBasename('.md'),
            'lastmod' => date('Y-m-d', $file->getMTime()),
            'changefreq' => 'weekly',
            'priority' => 0.5,
        ];
    }

    // Recipes

    $finder = new Finder();
    $finder
        ->name('*.md')
        ->files()
        ->in($app['recipes.path'] . '/docs');

    foreach ($finder as $file) {
        $map[] = [
            'loc' => $app['base_url'] . '/recipes/' . $file->getBasename('.md'),
            'lastmod' => date('Y-m-d', $file->getMTime()),
            'changefreq' => 'weekly',
            'priority' => 0.5,
        ];
    }


    $response->headers->set('Content-Type', 'text/xml');
    $response->setCharset('UTF-8');
    $response->setContent(render('sitemap.xml.twig', [
        'map' => $map,
    ]));

    return $response;
});

return $controller;
