<?php

/*
 |--------------------------------------------------------------------------
 | Application Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register all of the routes for an application.
 | It's a breeze. Simply tell Laravel the URIs it should respond to
 | and give it the Closure to execute when that URI is requested.
 |
 */

Route::model('user', 'User');
Route::model('game', 'Game');

Route::get('/', array(
    'as' => 'home',
    function() {
        return View::make('home');
    }

));

Route::group(array('before' => 'guest'), function() {
    Route::get('login', array(
        'as' => 'login',
        function() {
            return View::make('login');
        }

    ));
    Route::post('login', 'UserController@login');

    Route::get('join', array(
        'as' => 'join',
        function() {
            return View::make('join');
        }

    ));
    Route::post('join', 'UserController@join');

    Route::get('reminder', array(
        'as' => 'reminder',
        'uses' => 'RemindersController@getRemind'
    ));
    Route::post('reminder', 'RemindersController@postRemind');

    Route::get('password/reset/{token}', array(
        'as' => 'reset',
        'uses' => 'RemindersController@getReset'
    ));
    Route::post('password/reset', 'RemindersController@postReset');

    Route::get('user/confirm/{token}', array(
        'as' => 'confirm',
        'uses' => 'UserController@confirm'
    ));
});

Route::group(array('before' => 'auth'), function() {
    Route::get('logout', array(
        'as' => 'logout',
        'uses' => 'UserController@logout'
    ));

    Route::group(array('before' => 'standard'), function() {
        /* Standard users only */
    });

    Route::get('account', array(
        'as' => 'account',
        'uses' => 'UserController@showAccount'
    ));
    Route::post('account', 'UserController@saveAccount');

    Route::group(array('before' => 'admin'), function() {
        Route::group(array('prefix' => 'admin'), function() {
            Route::get('/', array(
                'as' => 'adminDashboard',
                function() {
                    return View::make('admin.home');
                }

            ));
            Route::group(array('prefix' => 'user'), function() {
                Route::get('create', array(
                    'as' => 'createUser',
                    function() {
                        return View::make('admin.user.create');
                    }

                ));

                Route::post('create', 'UserController@create');

                Route::get('edit/{user}', array(
                    'as' => 'editUser',
                    function(User $user) {
                        return View::make('admin.user.edit', compact('user'));
                    }

                ));

                Route::post('edit/{user}', 'UserController@save');

                Route::get('delete/{user}', array(
                    'as' => 'deleteUser',
                    function(User $user) {
                        return View::make('admin.user.delete', compact('user'));
                    }

                ));

                Route::post('delete/{user}', 'UserController@delete');
            });

            Route::get('users', array(
                'as' => 'modUsers',
                'uses' => 'UserController@listUsers'
            ));

            Route::resource('game', 'GameController');

            Route::group(array('prefix' => 'game'), function() {

                Route::get('{game}/delete', array(
                    'as' => 'gameDelete',
                    function(Game $game) {
                        return View::make('admin.game.delete', compact('game'));
                    }

                ));

            });

            Route::group(array('prefix' => '{game}'), function() {

                // WEAPON
                Route::get('weapon/create', array(
                    'as' => 'weaponCreate',
                    'uses' => 'WeaponController@create'
                ));
                Route::post('weapon/create', 'WeaponController@store');

                Route::get('{weapon}/edit', array(
                    'as' => 'weaponEdit',
                    'uses' => 'WeaponController@edit'
                ));
                Route::post('{weapon}/edit', 'WeaponController@update');

                Route::get('{weapon}/delete', array(
                    'as' => 'weaponDelete',
                    'uses' => 'WeaponController@delete'
                ));
                Route::post('{weapon}/delete', 'WeaponController@destroy');

                // ATTACHMENT
                Route::get('attachment/create', array(
                    'as' => 'attachmentCreate',
                    'uses' => 'AttachmentController@create'
                ));
                Route::post('attachment/create', 'AttachmentController@store');

                Route::get('attachment/{attachment}/edit', array(
                    'as' => 'attachmentEdit',
                    'uses' => 'AttachmentController@edit'
                ));
                Route::post('attachment/{attachment}/edit', 'AttachmentController@update');

                Route::get('attachment/{attachment}/delete', array(
                    'as' => 'attachmentDelete',
                    'uses' => 'AttachmentController@delete'
                ));
                Route::post('attachment/{attachment}/delete', 'AttachmentController@destroy');
            });
        });
    });
});

Route::get('{game}', array(
    'as' => 'showGame',
    'uses' => 'GameController@listWeapons'
));

Route::get('{game}/{weapon}', array(
    'as' => 'showLoadouts',
    'uses' => 'WeaponController@listLoadouts'
));

Route::post('{game}/{weapon}', 'LoadoutController@store');