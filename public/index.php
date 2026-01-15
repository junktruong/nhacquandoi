<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$tops = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort ASC, id ASC")->fetchAll();

$title = APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>

<main class="container">
  <div class="hero">
    <div class="hero__card">
      <div class="hero__title"><?= e(APP_NAME) ?></div>
      <div class="hero__sub">Chọn danh mục để phát nhạc. Danh mục hỗ trợ nhiều cấp (đặc biệt mục “Nhạc truyền thống binh chủng” có thể thêm nhiều binh chủng con).</div>
      <div class="hero__actions">
        <a class="btn btn--gold" href="#cats">Bắt đầu</a>
        <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin">Admin</a>
      </div>
    </div>
  </div>

  <h2 id="cats" class="section-title">DANH MỤC PHÁT NHẠC</h2>
  <div class="grid grid--cats">
    <?php foreach ($tops as $c): ?>
      <a class="card card--cat" href="<?= e(BASE_URL) ?>/category.php?id=<?= (int)$c['id'] ?>">
        <div class="card__title"><?= e($c['name']) ?></div>
        <div class="card__sub">Xem bài hát / mục con</div>
      </a>
    <?php endforeach; ?>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
