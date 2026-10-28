<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
 * Use only when the main domain must serve /home/CPANEL_USER/public_html.
 * Keep Laravel at /home/CPANEL_USER/portfolio, outside public_html.
 * Copy the contents of portfolio/public into public_html, including .htaccess
 * and .user.ini, then replace public_html/index.php with this file.
 * Set APP_PUBLIC_PATH=/home/CPANEL_USER/public_html in portfolio/.env.
 * Rebuild config cache and run storage:link so CLI uploads and assets use it.
 * Adjust $applicationPath if the private application folder has another name.
 */
define('LARAVEL_START', microtime(true));

$applicationPath = dirname(__DIR__).'/portfolio';

if (file_exists($maintenance = $applicationPath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $applicationPath.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $applicationPath.'/bootstrap/app.php';
$app->usePublicPath(__DIR__);
$app->handleRequest(Request::capture());
