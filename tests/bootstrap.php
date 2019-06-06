<?php

use LeNats\Tests\Mocks\Kernel;

$loader = require dirname(__DIR__) . '/vendor/autoload.php';

$kernelVersion = Symfony\Component\HttpKernel\Kernel::MAJOR_VERSION;

if (!in_array($kernelVersion, [3, 4], true)) {
    throw new Exception('Not supported Symfony HttpKernel version. Supports only v3.*, v4.* versions');
}

$_ENV['KERNEL_DIR'] = dirname(__DIR__) . "/tests/Mocks";
$_ENV['KERNEL_CLASS'] = Kernel::class;
