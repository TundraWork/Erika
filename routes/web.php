<?php

$router->get('/', function () use ($router) {
    return 'Hello Erika';
});

$router->group(['prefix' => 'v1'], function () use ($router) {
    $router->group(['prefix' => 'portal', 'middleware' => 'Authenticate'], function () use ($router) {
        $router->group(['prefix' => 'user'], function () use ($router) {
            $router->post('/{user_id}', 'UserController@create');
            $router->get('/{user_id}', 'UserController@info');
            $router->delete('/{user_id}', 'UserController@destroy');
        });
        $router->group(['prefix' => 'bucket'], function () use ($router) {
            $router->post('/', 'BucketController@create');
            $router->get('/{bucket_id}', 'BucketController@info');
            $router->delete('/{bucket_id}', 'BucketController@destroy');
        });
    });
    $router->group(['prefix' => 'spica'], function () use ($router) {
        $router->post('/{bucket_id}/single[/{mode}]', 'DataController@single');
        $router->post('/{bucket_id}/batch[/{mode}]', 'DataController@batch');
    });
});

