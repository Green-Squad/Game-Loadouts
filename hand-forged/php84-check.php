<?php
// Isolated regressions: only the bundled disposable database; no external integrations.
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
check(PHP_VERSION === '8.4.27', 'Unexpected PHP runtime');
try {
require __DIR__.'/../bootstrap/autoload.php';
$app = require __DIR__.'/../bootstrap/start.php';
$app->boot();
$value = 'Hand-Forged — café 🛠';
check(Crypt::decrypt(Crypt::encrypt($value)) === $value, 'Legacy encryption round-trip');
$hash = Hash::make('synthetic-compatibility-password');
check(Hash::check('synthetic-compatibility-password', $hash), 'Password verification');
check(Carbon\Carbon::create(2026, 10, 22)->format('Y-m-d') === '2026-10-22', 'Date creation');
$parser = new Less_Parser;
$parser->parse('a { width: (1px + 2px); }');
check(strpos($parser->getCss(), '3px') !== false, 'Original LESS compiler');
trigger_error('PHP compatibility test deprecation: must be logged, not thrown', E_USER_DEPRECATED);
$thrown = false;
try { trigger_error('PHP compatibility test warning: must still throw', E_USER_WARNING); }
catch (ErrorException $error) { $thrown = true; }
check($thrown, 'Regular warnings must retain Laravel error handling');
check(Form::file('image', '', array('class' => 'historically-ignored')) === Form::file('image'), 'Original file-input markup');
check(strpos(Form::file('image', array('class' => 'kept')), 'class="kept"') !== false, 'Valid file-input options');
$temporary = tempnam(sys_get_temp_dir(), 'hf-upload-');
try {
    $upload = array('name' => 'image.png', 'full_path' => 'untrusted/image.png', 'type' => 'image/png',
        'tmp_name' => $temporary, 'error' => UPLOAD_ERR_OK, 'size' => 0);
    $bag = new Symfony\Component\HttpFoundation\FileBag(array('image' => $upload));
    check($bag->get('image') instanceof Symfony\Component\HttpFoundation\File\UploadedFile, 'PHP 8 upload metadata');
    check($bag->get('image')->getClientOriginalName() === 'image.png', 'Upload basename preserved');
    $nested = array();
    foreach ($upload as $key => $value) $nested[$key] = array('child' => $value);
    $bag = new Symfony\Component\HttpFoundation\FileBag(array('images' => $nested));
    check($bag->get('images')['child'] instanceof Symfony\Component\HttpFoundation\File\UploadedFile, 'Nested upload metadata');
} finally { unlink($temporary); }
$home = (new HelperController)->showHome()->render();
check(strpos($home, 'Game Loadouts') !== false, 'Original home view renders');
$game = Game::findOrFail('Call of Duty Black Ops 7');
(new GameController)->listWeapons($game)->render();
WeaponController::listLoadouts($game, 'AK-27')->render();
echo "PASS patched PHP runtime, Unicode encryption, password hashing, dates, LESS and error handling\n";

} catch (Throwable $error) {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
}
