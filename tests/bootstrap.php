<?php

// Bootstrap for PHPUnit testing
define('RUNNING_TESTS', true);

require_once __DIR__.'/../vendor/autoload.php';

// Set environment variables from phpunit.xml attributes
// These values are set by phpunit.xml but need to be in PHP environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

$_SERVER['APP_ENV'] = 'testing';
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = ':memory:';

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
