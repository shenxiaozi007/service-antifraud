<?php

use Laravel\Lumen\Routing\Router;

/** @var Router $router */
$router->group(
    [
        'prefix' => 'ad',
        'namespace' => 'Ad',
    ],
    function ($router) {
        $router->post('reward', 'AdRewardController@reward');
    }
);
