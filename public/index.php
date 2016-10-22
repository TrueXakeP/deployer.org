<?php
/* (c) Anton Medvedev <anton@medv.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

require __DIR__ . '/../src/app.php';

$filename = __DIR__ . '/../public/' . preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

#########################
#   Start application   #
#########################

$app->run();
