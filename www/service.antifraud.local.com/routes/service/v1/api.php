<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->post('auth/wechat-login', 'AuthController@wechatLogin');
$router->get('me', 'UserController@me');
$router->post('files/upload-token', 'FileController@uploadToken');
$router->post('analysis/image', 'AnalysisController@createImage');
$router->post('analysis/audio', 'AnalysisController@createAudio');
$router->get('analysis/{recordId}', 'AnalysisController@detail');
$router->get('analysis-records', 'AnalysisController@records');
$router->delete('analysis/{recordId}', 'AnalysisController@delete');
$router->get('points/transactions', 'PointController@transactions');
$router->post('payments/wechat/order', 'PaymentController@wechatOrder');
