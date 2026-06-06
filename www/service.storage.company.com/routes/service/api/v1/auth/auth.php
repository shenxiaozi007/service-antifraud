<?php

use Laravel\Lumen\Routing\Router;

/** @var Router $router */
$router->group(
    [
        'prefix' => 'auth',
        'namespace' => 'Auth',
    ],
    function ($router) {
        $router->post('wechat-login', 'AuthController@wechatLogin');
        $router->post('send-code', 'AuthController@sendCode');
        $router->post('code-login', 'AuthController@codeLogin');
        $router->post('introspect', 'AuthController@introspect');
    }
);
