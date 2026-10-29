<?php
/** Backward-compatible entry point for saved links, photos and in-flight forms. */
declare(strict_types=1);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
if (in_array($method, ['GET', 'HEAD'], true) && !isset($_GET['photo_file'])) {
    $query = str_replace(["\r", "\n"], '', (string)($_SERVER['QUERY_STRING'] ?? ''));
    header('Cache-Control: no-store');
    header('Location: /partner_center.php' . ($query !== '' ? '?' . $query : ''), true, 302);
    exit;
}
// POST bodies and existing authenticated photo URLs are handled without redirecting.
require __DIR__ . '/partner_center.php';
