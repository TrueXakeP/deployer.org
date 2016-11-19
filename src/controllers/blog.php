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

$controller->get('/blog', function (Request $request) use ($app) {
    $response = new Response();

    $file = new SplFileInfo($app['blog.path'] . '/index.md');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    list($body, $title) = parse_md(parse_links(file_get_contents($file->getPathname())));

    $response->setContent(render('blog.twig', [
        'url' => url("/blog"),
        'title' => $title,
        'content' => $body,
    ]));

    return $response;
});

$controller->get('/blog/{page}', function ($page, Request $request) use ($app) {
    $response = new Response();

    $file = new SplFileInfo($app['blog.path'] . '/' . $page . '.md');

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    list($body, $title) = parse_md(parse_links(file_get_contents($file->getPathname())));

    $response->setContent(render('blog.twig', [
        'url' => url("/blog/$page"),
        'page' => $page,
        'title' => $title,
        'content' => $body,
    ]));

    return $response;
})
    ->assert('page', '[\w/-]+');


return $controller;
