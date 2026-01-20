<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

require_admin();
$pdo = db();

$counts = [
  'cats' => (int)$pdo->query("SELECT COUNT(*) AS c FROM categories")->fetch()['c'],
  'songs' => (int)$pdo->query("SELECT COUNT(*) AS c FROM songs")->fetch()['c'],
];

$extra_css = ['/assets/admin.css'];
$title = 'Dashboard — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container">
  <div class="admin-top">
    <h2 class="section-title">QUẢN TRỊ</h2>
    <div class="admin-top__actions">
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/">Trang phát</a>
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/logout.php">Đăng xuất</a>
    </div>
  </div>

  <div class="grid grid--admin">
    <div class="panel">
      <div class="kpi">
        <div class="kpi__num"><?= (int)$counts['cats'] ?></div>
        <div class="kpi__label">Danh mục</div>
      </div>
      <a class="btn btn--gold" href="<?= e(BASE_URL) ?>/admin/categories.php">Quản lý danh mục</a>
    </div>

    <div class="panel">
      <div class="kpi">
        <div class="kpi__num"><?= (int)$counts['songs'] ?></div>
        <div class="kpi__label">Bài hát</div>
      </div>
      <a class="btn btn--gold" href="<?= e(BASE_URL) ?>/admin/songs.php">Quản lý bài hát</a>
    </div>

    <div class="panel">
      <div class="kpi">
        <div class="kpi__num">🔒</div>
        <div class="kpi__label">Tài khoản</div>
      </div>
      <a class="btn btn--gold" href="<?= e(BASE_URL) ?>/admin/account.php">Đổi mật khẩu</a>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
