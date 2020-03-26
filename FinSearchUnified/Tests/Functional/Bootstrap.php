<?php

use Composer\Autoload\ClassLoader;

$loader = require_once __DIR__ . '/../../vendor/autoload.php';
// This is required, because FINDOLOGIC-API requires a later version of Guzzle than Shopware 5.
if ($loader instanceof ClassLoader) {
    $loader->unregister();
    $loader->register(false);
}
require_once __DIR__ . '/../../../../../tests/Functional/bootstrap.php';
