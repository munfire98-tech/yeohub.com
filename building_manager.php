<?php
/** Compatibility entry for existing bookmarks and integrations. */
declare(strict_types=1);
if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    header('Cache-Control: no-store');
    header('Location: FireDesk.php'.($query !== '' ? '?'.$query : ''), true, 307);
    exit;
}
require __DIR__.'/FireDesk.php';
