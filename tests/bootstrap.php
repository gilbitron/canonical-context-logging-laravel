<?php

declare(strict_types=1);

/**
 * Bootstrap file for PHPUnit tests.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Suppress error_log output during tests by redirecting to /dev/null
ini_set('error_log', '/dev/null');

// Set environment variable to indicate we're running tests
putenv('PHPUNIT=1');
