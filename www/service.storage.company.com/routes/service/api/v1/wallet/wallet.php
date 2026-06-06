<?php

use Laravel\Lumen\Routing\Router;

/** @var Router $router */
$router->group(
    [
        'prefix' => 'wallet',
        'namespace' => 'Wallet',
    ],
    function ($router) {
        $router->get('balance', 'WalletController@balance');
        $router->get('transactions', 'WalletController@transactions');
        $router->get('transactions-by-user', 'WalletController@transactionsByUser');
        $router->post('freeze', 'WalletController@freeze');
        $router->post('confirm', 'WalletController@confirm');
        $router->post('release', 'WalletController@release');
    }
);
