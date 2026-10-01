<?php
/**
 * Bootstrap the standalone PHPStan extension tests.
 *
 * @package Activitypub
 */

use Activitypub\Autoloader;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../includes/class-autoloader.php';

Autoloader::register_path( 'Activitypub', __DIR__ . '/../../includes/' );
Autoloader::register_path( 'Activitypub\Tests\PHPStan', __DIR__ . '/data/' );
