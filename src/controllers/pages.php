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

    $response->setLastModified(revisionTime([$templateFile]));
    if ($response->isNotModified($request)) {
        return $response;
    }

    $templateParams = [
        'url' => url("/$page")
    ];

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');
    $response->setContent(render($page . '.twig', $templateParams));

    return $response;
})->value('page', 'index');

return $controller;
