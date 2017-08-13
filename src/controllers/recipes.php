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

// Get recipes docs pages from markdown source, manually parse `.md` links.
// Cache rendered response with validate file modify time.
$controller->get('/recipes/{page}', function ($page, Request $request) use ($app) {
    $response = new Response();

    $recipeDocsPath = $app['recipes.path'] . '/docs/';

    if ($page === 'index') {
        $file = new SplFileInfo($app['recipes.path'] . '/README.md');
    } else {
        $file = new SplFileInfo($recipeDocsPath . $page . '.md');
    }

    if (!$file->isReadable()) {
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response->headers->set('Content-Type', 'text/html');
    $response->setCharset('UTF-8');

    $content = file_get_contents($file->getPathname());
    $content = preg_replace('/\(docs\/(.*?)\.md\)/', '(' . request()->getBaseUrl() . '/recipes/$1)', $content);

    list($body, $title) = parse_md($content);

    // Generate menu based on files list
    $finder = new Finder();
    $finder->files()->in($recipeDocsPath);

    $nav = [];
    foreach ($finder as $file) {
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        list($_, $name) = parse_md($file->getContents());
        $nav[] = [
            'title' => $name,
            'link' => $request->getBaseUrl() . '/recipes/' . $file->getBasename('.md')
        ];
    }

    // Set correct url.
    $canonical = url("/recipes/$page");
    if ($page === 'index') {
        $canonical = url("/recipes");
    }

    $response->setContent(render('recipes.twig', [
        'url' => $canonical,
        'title' => $title,
        'nav' => $nav,
        'content' => $body,
    ]));

    return $response;
})
    ->assert('page', '[\w/-]+')
    ->value('page', 'index');

$controller->get('/slack', function (Request $request) use ($app) {
    $query = http_build_query([
        'client_id' => $app['slack_client_id'],
        'client_secret' => $app['slack_client_secret'],
        'code' => $request->get('code'),
    ]);
    $response = file_get_contents("https://slack.com/api/oauth.access?$query");
    $data = json_decode($response, true);
    $token = $data['ok'] ? $data['access_token'] : $data['error'];
    $hook = $data['ok'] ? $data['incoming_webhook']['url'] : $data['error'];


    $response = new Response();
    $response->setContent(render('slack.twig', [
        'url' => url('/slack'),
        'token' => $token,
        'webhook' => $hook,
    ]));

    return $response;
});

return $controller;
