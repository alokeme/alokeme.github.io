<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
use PDO;
final class Database{private static ?PDO $pdo=null; public static function connect(?array $config=null):PDO{if(self::$pdo){return self::$pdo;} $config??=require __DIR__.'/../config/config.php'; return self::$pdo=new PDO($config['db']['dsn'],$config['db']['user'],$config['db']['pass'],$config['db']['options']);} public static function transaction(callable $cb):mixed{$pdo=self::connect();$pdo->beginTransaction();try{$r=$cb($pdo);$pdo->commit();return $r;}catch(\Throwable $e){$pdo->rollBack();throw $e;}}}
