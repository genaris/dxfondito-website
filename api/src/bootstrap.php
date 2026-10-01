<?php

declare(strict_types=1);

use DxFondito\App;
use DxFondito\Http\Request;

$root = dirname(__DIR__);

require $root . '/vendor/autoload.php';

date_default_timezone_set('UTC');

(new App($root))->handle(Request::fromGlobals())->send();
