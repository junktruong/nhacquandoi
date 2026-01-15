<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    ensure_dir(dirname(DB_PATH));
    ensure_dir(UPLOAD_DIR);

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    init_schema($pdo);
    seed_defaults($pdo);

    return $pdo;
}

function init_schema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            parent_id INTEGER NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            sort INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(parent_id) REFERENCES categories(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            note TEXT NULL,
            filename TEXT NOT NULL UNIQUE,
            mime TEXT NULL,
            original_name TEXT NULL,
            uploaded_at TEXT NOT NULL,
            FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
        );
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_categories_parent ON categories(parent_id);");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_songs_category ON songs(category_id);");
}

function seed_defaults(PDO $pdo): void {
    // Admin
    $adminCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM admins")->fetch()['c'];
    if ($adminCount === 0) {
        $pdo->prepare("INSERT INTO admins(username, password_hash, created_at) VALUES(:u,:p,:t)")
            ->execute([
                ':u' => 'admin',
                ':p' => password_hash('admin123', PASSWORD_DEFAULT),
                ':t' => now_iso()
            ]);
    }

    // Categories
    $count = (int)$pdo->query("SELECT COUNT(*) AS c FROM categories")->fetch()['c'];
    if ($count > 0) return;

    $pdo->beginTransaction();

    $tops = [
        ['Nhạc nghi lễ', 10],
        ['Bài hát quy định', 20],
        ['05 điệu vũ sinh hoạt', 30],
        ['Nhạc truyền thống binh chủng', 40],
    ];

    $topIds = [];
    $insTop = $pdo->prepare("INSERT INTO categories(parent_id, name, slug, sort, created_at) VALUES(NULL,:name,:slug,:sort,:t)");
    foreach ($tops as [$name, $sort]) {
        $slugBase = slugify($name);
        $slug = $slugBase;
        $i = 1;
        while ((int)$pdo->query("SELECT COUNT(*) AS c FROM categories WHERE slug=" . $pdo->quote($slug))->fetch()['c'] > 0) {
            $i++;
            $slug = $slugBase . '-' . $i;
        }
        $insTop->execute([':name' => $name, ':slug' => $slug, ':sort' => $sort, ':t' => now_iso()]);
        $topIds[$name] = (int)$pdo->lastInsertId();
    }

    $ins = $pdo->prepare("INSERT INTO categories(parent_id, name, slug, sort, created_at) VALUES(:pid,:name,:slug,:sort,:t)");

    // Nhạc nghi lễ: thêm vài mục mẫu
    $lePid = $topIds['Nhạc nghi lễ'];
    $subsLe = ['Kèn nghiêm', 'Nhạc chào mừng', 'Quốc ca', 'Đoàn ca', 'Diễu binh, diễu hành', 'Nhạc trao thưởng', 'Hồn tử sĩ'];
    $k = 1;
    foreach ($subsLe as $n) {
        $slug = slugify($n . '-' . $k);
        $ins->execute([':pid' => $lePid, ':name' => $n, ':slug' => $slug, ':sort' => $k * 10, ':t' => now_iso()]);
        $k++;
    }

    // Nhạc truyền thống binh chủng: seed nhiều binh chủng con
    $bcPid = $topIds['Nhạc truyền thống binh chủng'];
    $branches = [
        'Bộ binh',
        'Pháo binh',
        'Tăng thiết giáp',
        'Phòng không - Không quân',
        'Hải quân',
        'Bộ đội Biên phòng',
        'Công binh',
        'Thông tin liên lạc',
        'Đặc công',
        'Hóa học',
        'Quân y',
        'Hậu cần',
    ];
    $k = 1;
    foreach ($branches as $n) {
        $slug = slugify($n . '-' . $k);
        $ins->execute([':pid' => $bcPid, ':name' => $n, ':slug' => $slug, ':sort' => $k * 10, ':t' => now_iso()]);
        $k++;
    }

    $pdo->commit();
}

function categories_children(PDO $pdo, ?int $parentId): array {
    if ($parentId === null) {
        $st = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort ASC, id ASC");
        return $st->fetchAll();
    }
    $st = $pdo->prepare("SELECT * FROM categories WHERE parent_id = :pid ORDER BY sort ASC, id ASC");
    $st->execute([':pid' => $parentId]);
    return $st->fetchAll();
}

function categories_tree(PDO $pdo, ?int $parentId = null, int $depth = 0): array {
    $rows = categories_children($pdo, $parentId);
    $out = [];
    foreach ($rows as $r) {
        $r['_depth'] = $depth;
        $out[] = $r;
        $kids = categories_tree($pdo, (int)$r['id'], $depth + 1);
        foreach ($kids as $k) $out[] = $k;
    }
    return $out;
}

function leaf_categories(PDO $pdo): array {
    $sql = "
      SELECT c.*
      FROM categories c
      LEFT JOIN categories ch ON ch.parent_id = c.id
      GROUP BY c.id
      HAVING COUNT(ch.id) = 0
      ORDER BY c.sort ASC, c.id ASC
    ";
    return $pdo->query($sql)->fetchAll();
}
