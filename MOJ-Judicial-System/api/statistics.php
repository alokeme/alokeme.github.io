<?php declare(strict_types=1); require __DIR__.'/../includes/bootstrap.php'; use MOJ\JudicialSystem\{Helper,Statistics}; Helper::json((new Statistics())->counts());
