<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @var \Silex\Application $app */

$app->error(function (NotFoundHttpException $exception, Request $request, $code) use ($app) {
    $response = new Response('', 404, ['Content-Type' => 'text/html']);
    $response->setCharset('UTF-8');
    $response->setContent(render('404.twig', ['url' => $request->getBaseUrl()]));
    return $response;
});
