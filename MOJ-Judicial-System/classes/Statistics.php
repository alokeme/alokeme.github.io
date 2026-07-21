<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
final class Statistics{public function counts():array{$db=Database::connect();$tables=['judicial_decisions','courts','cities','subjects','keywords','crawler_errors'];$out=[];foreach($tables as $t){$out[$t]=(int)$db->query("SELECT COUNT(*) FROM $t")->fetchColumn();}return $out;}}
