<?php
// Container deployment configuration only; original controllers and views are unchanged.
Config::set('app.debug', false);
Config::set('session.domain', null);
Config::set('app.url', getenv('HAND_FORGED_LOCAL_TEST') === 'true' ? 'http://127.0.0.1:8080' : 'https://'.getenv('HAND_FORGED_HOST'));
// Laravel's existing pretend mode logs messages instead of sending real email.
Config::set('mail.pretend', true);
App::before(function($request) {
 $request->server->set('HTTPS',getenv('HAND_FORGED_LOCAL_TEST') === 'true' ? 'off' : 'on');
});
