<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
use Monolog\Handler\RotatingFileHandler;use Monolog\Logger as MonoLogger;use Monolog\Formatter\JsonFormatter;
final class Logger{public static function channel(string $name):MonoLogger{$cfg=require __DIR__.'/../config/config.php';$logger=new MonoLogger($name);$h=new RotatingFileHandler($cfg['paths']['logs'].'/'.$name.'.log',30,MonoLogger::INFO);$h->setFormatter(new JsonFormatter());$logger->pushHandler($h);return $logger;}}
