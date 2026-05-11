<?php

use Laravel\Lumen\Routing\Router;

/** @var Router $router */
$router->group(
    [
        'prefix' => 'file',
        'namespace' => 'File',
    ],
    function ($router) {
        $router->post('upload', 'FileController@upload');
        $router->get('detail', 'FileController@detail');
        $router->get('download-url', 'FileController@downloadUrl');
        $router->get('download', 'FileController@download');
        $router->get('preview', 'FileController@preview');
        $router->get('disks', 'FileController@disks');
    }
);
