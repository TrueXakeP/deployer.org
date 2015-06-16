<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @var \Silex\Application $app */
$controller = $app['controllers_factory'];

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
$controller->post('update/docs', function (Request $request) use ($app) {
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
$controller->post('update/deployer', function (Request $request) use ($app) {
    $event = $request->headers->get('X-Github-Event');
    $payload = $request->attributes->get('payload');

    if ($event === 'create' && $payload['ref_type'] === 'tag') {

        file_put_contents($app['schedule'], "update-deployer\n", FILE_APPEND);

        return new Response('Schedule task to update deployer created.', Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    return new Response('', Response::HTTP_FORBIDDEN, ['Content-Type' => 'text/plain']);
});

return $controller;
