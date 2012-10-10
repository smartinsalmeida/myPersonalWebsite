<?php
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config/settings.php';

$app = new Silex\Application();
$app['debug'] = false;

require_once __DIR__.'/../config/services.php';
require_once __DIR__.'/../config/routing.php';
require_once __DIR__.'/../config/events.php';

/* with cache */
//use Symfony\Component\HttpKernel\HttpCache\HttpCache;
//use Symfony\Component\HttpKernel\HttpCache\Store;
//use Symfony\Component\HttpFoundation\Request;
//$cache = new HttpCache($app, new Store(__DIR__.'/../data/cache'));
//$request = Request::createFromGlobals();
//$response = $cache->handle($request);

/* without cache */
$app->run();