<?php
// Original Laravel public query semantics, against an isolated fixture only.
// PHP CLI + pdo_mysql; this is a correctness oracle, NOT a Laravel performance baseline.
if (getenv('DB_DATABASE') !== 'loadouts_fixture' || !in_array(getenv('DB_HOST'), ['127.0.0.1','localhost'], true)) {
    fwrite(STDERR, "Only loopback loadouts_fixture is allowed\n"); exit(1);
}
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '3307').';dbname=loadouts_fixture;charset=utf8mb4', getenv('DB_USERNAME') ?: 'loadouts_reader', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
function rows($sql,$params=[]) {global $pdo;$q=$pdo->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC);}
$games=rows('SELECT * FROM games WHERE live=1 ORDER BY created_at DESC LIMIT 3');
$popular=[];
foreach($games as $game){$popular[$game['id']]=rows('SELECT l.id, COUNT(lu.loadout_id) count FROM weapons w JOIN loadouts l ON l.weapon_id=w.id JOIN loadout_user lu ON l.id=lu.loadout_id WHERE game_id=? GROUP BY l.id ORDER BY COUNT(lu.loadout_id) DESC,l.id LIMIT 5',[$game['id']]);}
$loadouts=rows('SELECT * FROM loadouts WHERE weapon_id=1 ORDER BY id');
foreach($loadouts as &$loadout){$loadout['count']=(int)rows('SELECT COUNT(*) n FROM loadout_user WHERE loadout_id=?',[$loadout['id']])[0]['n'];$loadout['attachments']=rows('SELECT a.name FROM attachments a JOIN attachment_loadout al ON a.id=al.attachment_id WHERE al.loadout_id=? ORDER BY a.id',[$loadout['id']]);}
unset($loadout);uasort($loadouts,function($a,$b){return $b['count']-$a['count'];});
echo json_encode(['popular'=>$popular,'weapon'=>array_values($loadouts)],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
