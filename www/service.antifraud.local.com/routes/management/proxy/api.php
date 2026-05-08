<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->get('users', 'AdminController@users');
$router->get('analysis-records', 'AdminController@records');
$router->get('analysis-records/{recordId}', 'AdminController@recordDetail');
$router->get('file-assets', 'AdminController@files');
$router->get('point-transactions', 'AdminController@pointTransactions');
$router->get('risk-rules', 'RiskRuleController@list');
$router->post('risk-rules', 'RiskRuleController@store');
$router->put('risk-rules/{ruleId}', 'RiskRuleController@update');
$router->post('analysis-records/{recordId}/retry', 'AdminController@retry');
