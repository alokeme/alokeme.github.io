<?php declare(strict_types=1); require __DIR__.'/../includes/bootstrap.php'; use MOJ\JudicialSystem\{Helper,Search}; Helper::json((new Search())->query($_GET));
