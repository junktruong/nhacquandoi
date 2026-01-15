<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$rows = $pdo->query("SELECT id, title, filename FROM songs ORDER BY id ASC")->fetchAll();

$baseDir = realpath(__DIR__ . '/../uploads'); // public/uploads
$songs = [];

foreach ($rows as $r) {
  $fn = (string)($r['filename'] ?? '');
  if ($fn === '') continue;

  $abs = $baseDir ? ($baseDir . DIRECTORY_SEPARATOR . $fn) : null;
  if (!$abs || !is_file($abs)) continue;

  $mtime = @filemtime($abs) ?: 0;
  $size  = @filesize($abs) ?: 0;

  $songs[] = [
    'id' => (int)$r['id'],
    'title' => (string)($r['title'] ?? ''),
    'filename' => $fn,
    'path' => '/uploads/' . rawurlencode($fn),
    'mtime' => $mtime,
    'size' => $size,
  ];
}

// hash để so nhanh “có đổi gì không”
$hash = sha1(json_encode(array_map(fn($s) => [$s['filename'],$s['mtime'],$s['size']], $songs)));

echo json_encode([
  'ok' => true,
  'count' => count($songs),
  'hash' => $hash,
  'songs' => $songs,
], JSON_UNESCAPED_UNICODE);
