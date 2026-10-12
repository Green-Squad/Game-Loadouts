<?php
// Demo-only integration adapters; the original handwritten controllers still run.
Config::set('hand_forged_demo', true);
Config::set('app.debug', false);
Config::set('session.cookie', 'gameloadouts_hand_forged_session');
Config::set('session.domain', null);
Config::set('session.secure', getenv('HAND_FORGED_LOCAL_TEST') !== 'true');
Config::set('app.url', (getenv('HAND_FORGED_LOCAL_TEST') === 'true' ? 'http://127.0.0.1:8080' : 'https://'.getenv('HAND_FORGED_HOST')));
class HandForgedGuard extends Illuminate\Auth\Guard {
 public function getRecallerName() { return 'gameloadouts_hand_forged_remember'; }
}
Auth::extend('hand-forged', function($app) {
 return new HandForgedGuard(new Illuminate\Auth\EloquentUserProvider($app['hash'],$app['config']['auth.model']),$app['session.store']);
});
Config::set('auth.driver','hand-forged');
class HandForgedMailer extends Illuminate\Mail\Mailer {
 protected function sendSwiftMessage($message) {
  Session::push('hand_forged_mail',array('subject'=>$message->getSubject(),'body'=>str_replace(array('http://www.gameloadouts.com','https://www.gameloadouts.com','http://gameloadouts.com','https://gameloadouts.com'),Config::get('app.url'),$message->getBody())));
  return 1; // Captured in this browser's original file session; never sent to SMTP.
 }
}
$original=App::make('mailer');
$local=new HandForgedMailer($original->getViewEnvironment(),$original->getSwiftMailer());
$local->alwaysFrom('mail@handforged.invalid','Hand-Forged Demo');
$local->setContainer(App::getFacadeRoot());
$local->setQueue(App::make('queue'));
$local->setLogger(App::make('log'));
Mail::swap($local);
App::before(function($request) {
 $request->server->set('HTTPS',getenv('HAND_FORGED_LOCAL_TEST') === 'true' ? 'off' : 'on');
});
Route::get('hand-forged/mail',function() {
 return View::make('hand-forged.mail',array('messages'=>Session::get('hand_forged_mail',array())));
});
