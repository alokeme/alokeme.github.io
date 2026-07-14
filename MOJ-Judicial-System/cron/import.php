<?php declare(strict_types=1); require __DIR__.'/../vendor/autoload.php'; use MOJ\JudicialSystem\Importer; $pages=(int)($argv[1]??0); print_r((new Importer())->run([], $pages));
