<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
final class Cache{public function get(string $key):mixed{$f=$this->file($key);return is_file($f)&&filemtime($f)>time()?unserialize((string)file_get_contents($f)):null;} public function put(string $key,mixed $value,int $ttl=3600):void{$f=$this->file($key);file_put_contents($f,serialize($value));touch($f,time()+$ttl);} private function file(string $key):string{$p=(require __DIR__.'/../config/config.php')['paths']['cache'];is_dir($p)||mkdir($p,0755,true);return $p.'/'.Helper::hash($key).'.cache';}}
