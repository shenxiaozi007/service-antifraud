<?php

use Laravel\Lumen\Routing\Router;

/** @var Router $router */
$router->group(
    [
        'prefix' => 'payment',
        'namespace' => 'Payment',
    ],
    function ($router) {
        $router->get('packages', 'PaymentController@packages');
        $router->post('wechat/jsapi-order', 'PaymentController@wechatJsapiOrder');
        $router->post('wechat/notify', 'PaymentController@wechatNotify');
        $router->post('alipay/precreate-order', 'PaymentController@alipayPrecreateOrder');
        $router->post('alipay/notify', 'PaymentController@alipayNotify');
        $router->get('orders/{orderNo}', 'PaymentController@orderStatus');
    }
);
