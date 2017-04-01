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

// Get docs pages from markdown source, manually parse `.md` links.
// Cache rendered response with validate file modify time.
$controller->get('/docs/{page}', function ($page, Request $request) use ($app) {
    $response = new Response();

    $file = new SplFileInfo($app['docs.path'] . '/' . $page . '.md');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    list($body, $title) = parse_md(parse_links(file_get_contents($file->getPathname())));

    // Add anchors
    $body = add_anchors($body);

    // Get docs navigation from README.md
    list($menu, $_) = parse_md(parse_links(file_get_contents($app['docs.path'] . '/README.md')));

    // Set correct url.
    $canonical = url("/docs/$page");
    if ($page === 'getting-started') {
        $canonical = url("/docs");
    }

    $response->setContent(render('docs.twig', [
        'url' => $canonical,
        'page' => $page,
        'title' => $title,
        'menu' => $menu,
        'content' => $body,
    ]));

    return $response;
})
    ->assert('page', '[\w/-]+')
    ->value('page', 'getting-started');


return $controller;
