<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group([
    'prefix' => 'api/v1',
    'namespace' => 'Service\V1',
], function () use ($router) {
    require __DIR__.'/service/v1/api.php';
});

$router->group([
    'prefix' => 'management/proxy',
    'namespace' => 'Management\Proxy',
], function () use ($router) {
    require __DIR__.'/management/proxy/api.php';
});
