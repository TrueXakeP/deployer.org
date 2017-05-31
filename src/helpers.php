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
    return $app['request_context'];
}

/**
 * @param string $path
 * @return string
 */
function url($path)
{
    global $app; // What? :)
    return $app['base_url'] . $path;
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

        // Drop title.
        $content = preg_replace('/#\s*(.*)/u', '', $content, 1);
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
function parse_links($content)
{
    return preg_replace('/\((.*?)\.md(.*?)\)/', '(' . request()->getBaseUrl() . '/docs/$1$2)', $content);
}

/**
 * @param $body
 * @return string
 */
function add_anchors($body)
{
    $replace = function ($matches) {
        $l = $matches['level'];
        $title = $matches['title'];

        $id = preg_replace('/\s/i', '-', $title);
        $id = preg_replace('/[^a-z0-9\-_\:]/i', '', $id);
        $id = strtolower($id);

        $svg = '<svg aria-hidden="true" class="octicon-link" height="16" version="1.1" viewBox="0 0 16 16" width="16"><path fill-rule="evenodd" d="M4 9h1v1H4c-1.5 0-3-1.69-3-3.5S2.55 3 4 3h4c1.45 0 3 1.69 3 3.5 0 1.41-.91 2.72-2 3.25V8.59c.58-.45 1-1.27 1-2.09C10 5.22 8.98 4 8 4H4c-.98 0-2 1.22-2 2.5S3 9 4 9zm9-3h-1v1h1c1 0 2 1.22 2 2.5S13.98 12 13 12H9c-.98 0-2-1.22-2-2.5 0-.83.42-1.64 1-2.09V6.25c-1.09.53-2 1.84-2 3.25C6 11.31 7.55 13 9 13h4c1.45 0 3-1.69 3-3.5S14.5 6 13 6z"></path></svg>';
        $a =
            '<a id="' . $id . '"></a>' .
            '<a class="anchor" href="#' . $id . '" aria-hidden="true">' . $svg . '</a>';

        return "<h$l>$a$title</h$l>";
    };
    $body = preg_replace_callback('/<h(?P<level>[1-6])>(?P<title>.+?)<\/h([1-6])>/i', $replace, $body);
    return $body;
}
