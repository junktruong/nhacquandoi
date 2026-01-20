<?php
declare(strict_types=1);

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never {
    header('Location: ' . BASE_URL . $path);
    exit;
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create dir: $dir");
        }
    }
}

function now_iso(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
}

/** slugify tiếng Việt cơ bản */
function slugify(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $map = [
        'àáạảãâầấậẩẫăằắặẳẵ' => 'a',
        'èéẹẻẽêềếệểễ' => 'e',
        'ìíịỉĩ' => 'i',
        'òóọỏõôồốộổỗơờớợởỡ' => 'o',
        'ùúụủũûừứựửữư' => 'u',
        'ỳýỵỷỹ' => 'y',
        'đ' => 'd',
    ];
    foreach ($map as $chars => $rep) {
        $text = preg_replace('/[' . $chars . ']/u', $rep, $text);
    }
    $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : $text;
}

function tree_indent(int $depth): string {
    return str_repeat('— ', max(0, $depth));
}
