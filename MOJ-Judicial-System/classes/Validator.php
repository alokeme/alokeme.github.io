<?php declare(strict_types=1);
namespace MOJ\JudicialSystem;
final class Validator{public static function int(mixed $v,int $min=0,int $max=PHP_INT_MAX):int{return min($max,max($min,(int)$v));} public static function text(mixed $v,int $max=255):string{return mb_substr(trim(strip_tags((string)$v)),0,$max);}}
