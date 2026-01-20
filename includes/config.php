<?php
declare(strict_types=1);

/**
 * Phát nhạc Quân đội - v2
 * - Web root: trỏ Nginx/hosting vào thư mục /public
 * - DB: SQLite (data/app.sqlite)
 */

define('APP_NAME', 'Quân nhạc số');
define('DB_PATH', __DIR__ . '/../data/app.sqlite');
define('UPLOAD_DIR', __DIR__ . '/../public/uploads');
define('MAX_UPLOAD_BYTES', 200 * 1024 * 1024); // 120MB

/**
 * BASE_URL:
 * - Nếu chạy ở root domain: ''
 * - Nếu chạy trong subfolder: '/nhac'  (không có dấu / cuối)
 */
define('BASE_URL', '');

/** MIME audio được phép */
$GLOBALS['ALLOWED_AUDIO_MIME'] = [
    'audio/mpeg'   => 'mp3',
    'audio/mp3'    => 'mp3',
    'audio/x-mpeg' => 'mp3',
    'audio/wav'    => 'wav',
    'audio/x-wav'  => 'wav',
    'audio/ogg'    => 'ogg',
    'audio/webm'   => 'webm',
    'audio/aac'    => 'aac',
    'audio/mp4'    => 'm4a',
    'audio/x-m4a'  => 'm4a',
];

/** MIME video được phép */
$GLOBALS['ALLOWED_VIDEO_MIME'] = [
    'video/mp4'        => 'mp4',
    'video/webm'       => 'webm',
    'video/ogg'        => 'ogv',
    'video/quicktime'  => 'mov',
    'video/x-matroska' => 'mkv',
];

define('UPLOAD_URL', BASE_URL . '/uploads');
