<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

// Admin pages should NEVER be cached (prevents stale CSRF token via browser/service worker cache)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$pdo = db();

// Counts (UI + warning when turning a leaf category into a parent)
$songCount = [];
foreach ($pdo->query('SELECT category_id, COUNT(*) AS cnt FROM songs GROUP BY category_id')->fetchAll() as $r) {
  $songCount[(int)$r['category_id']] = (int)$r['cnt'];
}

$childCount = [];
foreach ($pdo->query('SELECT parent_id, COUNT(*) AS cnt FROM categories WHERE parent_id IS NOT NULL GROUP BY parent_id')->fetchAll() as $r) {
  $childCount[(int)$r['parent_id']] = (int)$r['cnt'];
}

// build parent->children map
$rows = $pdo->query("SELECT * FROM categories ORDER BY parent_id IS NOT NULL, parent_id ASC, sort ASC, id ASC")->fetchAll();
$byParent = [];
foreach ($rows as $r) {
  $pid = $r['parent_id'] === null ? 0 : (int)$r['parent_id'];
  $byParent[$pid][] = $r;
}

function render_tree(array $byParent, array $songCount, array $childCount, int $parentId = 0): void {
  $kids = $byParent[$parentId] ?? [];
  echo '<ul class="cat-children" data-parent="' . (int)$parentId . '">';
  foreach ($kids as $c) {
    $id = (int)$c['id'];
    $sc = (int)($songCount[$id] ?? 0);
    $cc = (int)($childCount[$id] ?? 0);

    echo '<li class="cat-item" data-id="' . $id . '" data-songs="' . $sc . '" data-kids="' . $cc . '">';
    echo '  <div class="cat-row">';
    echo '    <div class="handle" draggable="true" title="Kéo để đổi thứ tự">⠿</div>';
    echo '    <input class="cat-name" value="' . e((string)$c['name']) . '" disabled />';
    echo '    <div class="cat-badges">';
    if ($cc > 0) echo '      <span class="cat-badge cat-badge--kids" title="Số mục con">' . $cc . ' mục con</span>';
    if ($sc > 0) echo '      <span class="cat-badge cat-badge--songs" title="Số bài hát đang gắn với danh mục này">' . $sc . ' bài</span>';
    echo '    </div>';
    echo '    <div class="cat-actions">';
    echo '      <button class="cat-btn cat-btn-edit" type="button">Sửa</button>';
    echo '      <button class="cat-btn cat-btn-save cat-btn--gold" type="button" style="display:none;">Lưu</button>';

    // Leaf => show "Nhạc" (can upload). If it has children => only show link if there are existing songs.
    if ($cc === 0 || $sc > 0) {
      $label = ($cc === 0) ? 'Nhạc' : 'Nhạc (ẩn)';
      echo '      <a class="cat-btn cat-btn-music" href="' . e(BASE_URL) . '/admin/songs.php?cat=' . $id . '" title="Quản lý bài hát">' . $label . '</a>';
    }

    echo '      <button class="cat-btn cat-btn-add" type="button">+ Con</button>';
    echo '      <button class="cat-btn cat-btn-del cat-btn--danger" type="button">Xóa</button>';
    echo '    </div>';
    echo '  </div>';

    render_tree($byParent, $songCount, $childCount, $id);
    echo '</li>';
  }
  echo '</ul>';
}

$title = 'Danh mục — ' . APP_NAME;
$extra_css = ['/assets/admin-categories.css'];
$extra_js  = ['/assets/admin-categories.js'];

require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container">
  <div class="admin-top">
    <h2 class="section-title">DANH MỤC (KÉO THẢ)</h2>
    <div class="admin-top__actions">
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/dashboard.php">Dashboard</a>
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/logout.php">Đăng xuất</a>
    </div>
  </div>

  <div class="panel cat-admin"
       data-cats-admin
       data-csrf="<?= e(csrf_token()) ?>"
       data-api="<?= e(BASE_URL) ?>/admin/categories_api.php">

    <noscript>
      <div class="alert">Trang này cần bật JavaScript để kéo thả / thêm mục con.</div>
    </noscript>

    <div class="cat-toolbar">
      <div>
        <b>Kéo thả để đổi thứ tự (trong cùng một cấp)</b>
        <div class="cat-hint">
          • Nhấn <b>Sửa</b> để đổi tên, Enter hoặc bấm <b>Lưu</b> để lưu.
          • <b>+ Con</b> để thêm mục con.
          • Xóa sẽ xóa luôn mục con & bài hát.
        </div>
      </div>
      <button class="cat-btn cat-btn--gold" type="button" data-add-root>+ Thêm danh mục cấp cao nhất</button>
    </div>

    <?php render_tree($byParent, $songCount, $childCount, 0); ?>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
