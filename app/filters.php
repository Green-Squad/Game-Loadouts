<?php

/*
 * |-------------------------------------------------------------------------- | Application & Route Filters |-------------------------------------------------------------------------- | | Below you will find the "before" and "after" events for the application | which may be used to do any work before or after a request into your | application. Here you may also register your custom route filters. |
 */
App::before(function ($request) {
    $request->server->set('HTTPS', true);
});

App::after(function ($request, $response) {
    //
});

/*
 * |-------------------------------------------------------------------------- | Authentication Filters |-------------------------------------------------------------------------- | | The following filters are used to verify that the user of the current | session is logged into this application. The "basic" filter easily | integrates HTTP Basic authentication for quick, simple checking. |
 */

Route::filter('auth', function () {
    if (Auth::guest() || Auth::user() -> role == 'Guest')
        return Redirect::guest('login');
});

Route::filter('auth.basic', function () {
    return Auth::basic();
});

/*
 * |-------------------------------------------------------------------------- | Role Filters |-------------------------------------------------------------------------- |
 */

Route::filter('admin', function () {
    if (Auth::user() -> role != 'Admin')
        return Redirect::to('/');
});

Route::filter('premium', function ($route) {
    // passed in Post from the route
    $post = $route -> getParameter('post');
    $premium = false;
    foreach ( $post -> tags as $tag ) {
        // checks to see if the post is for premium users only
        if ($tag -> id == 'premium') {
            $premium = true;
        }
    }
    if ($premium) {
        // checks to make sure the user has role of either Admin or Premium
        if (Auth::guest() || Auth::user() -> role == 'User')
            return Redirect::to('/premium');
    }
});

Route::filter('standard', function () {
    if (Auth::user() -> role == 'Premium' || Auth::user() -> role == 'Admin')
        return Redirect::to('/');
});

/*
 * |-------------------------------------------------------------------------- | Non-live Games Filter |-------------------------------------------------------------------------- | 
 */

Route::filter('live', function ($route) {
    // passed in Post from the route
    $game = $route -> getParameter('game');
    if ($game -> live == 0) {
        if (Auth::guest() || Auth::user() -> role != 'Admin')
            App::abort(404);
    }
});

/*
 * |-------------------------------------------------------------------------- | Guest Filter |-------------------------------------------------------------------------- | | The "guest" filter is the counterpart of the authentication filters as | it simply checks that the current user is not logged in. A redirect | response will be issued if they are, which you may freely change. |
 */

Route::filter('guest', function () {
    if (Auth::check() && Auth::user() -> role != 'Guest')
        return Redirect::to('/');
});

/*
 * |-------------------------------------------------------------------------- | CSRF Protection Filter |-------------------------------------------------------------------------- | | The CSRF filter is responsible for protecting your application against | cross-site request forgery attacks. If this special token in a user | session does not match the one given in this request, we'll bail. |
 */

Route::filter('csrf', function () {
    if (Session::token() != Input::get('_token')) {
        throw new Illuminate\Session\TokenMismatchException();
    }
});
