<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

csrf_validate();

$pdo = db();
$action = (string)($_POST['action'] ?? '');

function ok(array $data = []): void {
  echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
  exit;
}
function fail(string $msg, int $code = 400): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  if ($action === 'add') {
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') fail('Tên danh mục không được trống.');

    // sort = max + 10
    if ($parent_id > 0) {
      $st = $pdo->prepare("SELECT COALESCE(MAX(sort),0) AS m FROM categories WHERE parent_id = :pid");
      $st->execute([':pid' => $parent_id]);
    } else {
      $st = $pdo->query("SELECT COALESCE(MAX(sort),0) AS m FROM categories WHERE parent_id IS NULL");
    }
    $maxSort = (int)($st->fetch()['m'] ?? 0);
    $sort = $maxSort + 10;

    $slugBase = slugify($name);
    $slug = $slugBase;
    $i = 1;
    while (true) {
      $q = $pdo->prepare("SELECT 1 FROM categories WHERE slug = :slug LIMIT 1");
      $q->execute([':slug' => $slug]);
      if (!$q->fetch()) break;
      $i++;
      $slug = $slugBase . '-' . $i;
    }

    $pdo->prepare("INSERT INTO categories(parent_id, name, slug, sort, created_at)
                   VALUES(:pid,:name,:slug,:sort,:t)")
        ->execute([
          ':pid' => $parent_id > 0 ? $parent_id : null,
          ':name' => $name,
          ':slug' => $slug,
          ':sort' => $sort,
          ':t' => now_iso()
        ]);

    ok(['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'parent_id' => $parent_id, 'sort' => $sort]);
  }

  if ($action === 'rename') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    if ($id <= 0) fail('Thiếu ID.');
    if ($name === '') fail('Tên không được trống.');

    $pdo->prepare("UPDATE categories SET name = :name WHERE id = :id")->execute([':name' => $name, ':id' => $id]);
    ok(['id' => $id, 'name' => $name]);
  }

  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) fail('Thiếu ID.');
    $pdo->prepare("DELETE FROM categories WHERE id = :id")->execute([':id' => $id]);
    ok(['id' => $id]);
  }

  if ($action === 'reorder') {
    $parent_id = (int)($_POST['parent_id'] ?? 0);
    $idsJson = (string)($_POST['ids'] ?? '[]');
    $ids = json_decode($idsJson, true);
    if (!is_array($ids)) fail('Dữ liệu sắp xếp không hợp lệ.');

    $pdo->beginTransaction();
    $sort = 10;

    if ($parent_id > 0) {
      $stmt = $pdo->prepare("UPDATE categories SET sort = :sort WHERE id = :id AND parent_id = :pid");
    } else {
      $stmt = $pdo->prepare("UPDATE categories SET sort = :sort WHERE id = :id AND parent_id IS NULL");
    }

    foreach ($ids as $id) {
      $id = (int)$id;
      if ($id <= 0) continue;
      $params = [':sort' => $sort, ':id' => $id];
      if ($parent_id > 0) $params[':pid'] = $parent_id;
      $stmt->execute($params);
      $sort += 10;
    }

    $pdo->commit();
    ok(['parent_id' => $parent_id]);
  }

  fail('Action không hợp lệ.');
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  fail($e->getMessage(), 500);
}
