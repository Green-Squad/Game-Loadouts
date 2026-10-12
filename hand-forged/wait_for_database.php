<?php
$ready=false;
for($i=0;$i<60;$i++) {
 try {
  $db=new PDO('mysql:host=127.0.0.1;dbname=game_loadouts_hand_forged;charset=utf8','handforged',getenv('DB_PASSWORD'),array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
  $ready=(int)$db->query('SELECT COUNT(*) FROM weapons')->fetchColumn()===843 && (int)$db->query('SELECT COUNT(*) FROM attachments')->fetchColumn()===5855;
  if($ready) break;
 } catch(Exception $e) {}
 sleep(1);
}
if(!$ready) { fwrite(STDERR,"Disposable MySQL startup/seeding failed\n");exit(1); }
echo "HAND_FORGED_DATABASE_READY: populated local MySQL\n";
