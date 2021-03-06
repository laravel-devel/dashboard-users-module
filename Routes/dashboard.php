<?php

Route::group([
    'prefix' => config('develusers.slug'),
], function() {
    /**
     * Users CRUD
     */
    Route::group([], function() {
        Route::get('/', [
            'as' => 'develusers.users.index',
            'uses' => 'UsersController@index',
            'dashboardSidebar' => 'Manage Users->Users',
            'permissions' => 'users.browse',
        ]);

        Route::get('/list', [
            'as' => 'develusers.users.get',
            'uses' => 'UsersController@get',
            'permissions' => 'users.browse',
        ]);

        Route::get('/add', [
            'as' => 'develusers.users.create',
            'uses' => 'UsersController@create',
            'permissions' => 'users.add',
        ]);

        Route::get('/{id}/edit', [
            'as' => 'develusers.users.edit',
            'uses' => 'UsersController@edit',
            'permissions' => 'users.view || users.edit',
        ]);

        Route::post('/', [
            'as' => 'develusers.users.store',
            'uses' => 'UsersController@store',
            'permissions' => 'users.add',
        ]);

        Route::post('/{id}', [
            'as' => 'develusers.users.update',
            'uses' => 'UsersController@update',
            'permissions' => 'users.edit',
        ]);

        Route::delete('/{id}', [
            'as' => 'develusers.users.destroy',
            'uses' => 'UsersController@destroy',
            'permissions' => 'users.delete',
        ]);
    });
});
