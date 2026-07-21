<?php declare(strict_types=1);
require __DIR__.'/../vendor/autoload.php';
use MOJ\JudicialSystem\Security;
date_default_timezone_set((require __DIR__.'/../config/config.php')['app']['timezone']);
Security::boot();
