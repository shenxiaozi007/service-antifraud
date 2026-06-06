<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('system/health', 'SystemController@health');
$router->post('auth/wechat-login', 'AuthController@wechatLogin');
$router->post('auth/send-code', 'AuthController@sendCode');
$router->post('auth/code-login', 'AuthController@codeLogin');
$router->get('me', 'UserController@me');
$router->post('files/upload-token', 'FileController@uploadToken');
$router->post('files/register', 'FileController@register');
$router->post('analysis/image', 'AnalysisController@createImage');
$router->post('analysis/audio', 'AnalysisController@createAudio');
$router->get('analysis/{recordId}', 'AnalysisController@detail');
$router->get('analysis-records', 'AnalysisController@records');
$router->delete('analysis/{recordId}', 'AnalysisController@delete');
$router->get('points/transactions', 'PointController@transactions');
$router->get('payments/packages', 'PaymentController@packages');
$router->post('payments/wechat/order', 'PaymentController@wechatOrder');
