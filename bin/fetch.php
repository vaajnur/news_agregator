#!/usr/bin/env php
<?php

declare(strict_types=1);

/** CLI: php bin/fetch.php */

$services = require dirname(__DIR__) . '/bootstrap.php';

/** @var \App\Service\NewsImporter $importer */
$importer = $services['importer'];

try {
    $result = $importer->import();
    fwrite(STDOUT, sprintf(
        "[%s] RSS import OK: fetched=%d, inserted=%d, updated=%d\n",
        date('c'),
        $result['fetched'],
        $result['inserted'],
        $result['updated']
    ));
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, sprintf("[%s] RSS import FAILED: %s\n", date('c'), $e->getMessage()));
    exit(1);
}
