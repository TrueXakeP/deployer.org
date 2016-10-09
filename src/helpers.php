<?php
/* (c) Anton Medvedev <anton@medv.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Render file with twig.
 *
 * @param string $file
 * @param array $params
 * @return string
 */
function render($file, $params = [])
{
    global $app; // Yes, I know that =)
    return $app['twig']->render($file, $params);
}

/**
 * Return http request object.
 *
 * @return \Symfony\Component\HttpFoundation\Request
 */
function request()
{
    global $app; // I know what you say :)
                 // I do not care =)
    return $app['request'];
}

/**
 * Parse markdown test into html and title.
 *
 * @param string $content
 * @return array
 */
function parse_md($content)
{
    static $parsedown = null;

    if (null === $parsedown) {
        $parsedown = new Parsedown();
    }

    // Get title from first header.
    if (preg_match('/#\s*(.*)/u', $content, $matches)) {
        $title = $matches[1];
    } else {
        $title = '';
    }

    $body = $parsedown->text($content);

    // Add classes.
    $body = str_replace('<table>', '<table class="table table-bordered">', $body);

    return [$body, $title];
}

/**
 * @param string $content
 * @return string
 */
function parse_links($content) {
    return preg_replace('/\((.*?)\.md\)/', '(' . request()->getBaseUrl() . '/docs/$1)', $content);
}