<?php
// Offline regression checks: no connection to the original database or external integrations.
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
check(PHP_VERSION === '8.4.27', 'Unexpected PHP runtime');
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
echo "PASS patched PHP runtime, Unicode encryption, password hashing, dates, LESS and error handling\n";
