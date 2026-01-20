<?php
// NÊN: chặn bằng login/session nội bộ trước khi cho tải
// if (!isset($_SESSION['user'])) { http_response_code(403); exit; }

$base = realpath(__DIR__ . '/audio');
if ($base === false || !is_dir($base)) {
  http_response_code(404);
  echo "Audio folder not found";
  exit;
}

$tmpZip = tempnam(sys_get_temp_dir(), 'offline_pack_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  http_response_code(500);
  echo "Cannot create zip";
  exit;
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));
foreach ($it as $file) {
  if ($file->isDir()) continue;
  $full = $file->getPathname();
  $rel  = substr($full, strlen($base) + 1);
  $rel  = str_replace('\\', '/', $rel);
  $zip->addFile($full, 'audio/' . $rel);
}
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="offline-pack.zip"');
header('Content-Length: ' . filesize($tmpZip));
header('Cache-Control: no-store');

readfile($tmpZip);
@unlink($tmpZip);
