<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$tops = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY sort ASC, id ASC")->fetchAll();

$title = APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>

<div class="video-bg" aria-hidden="true">
  <video class="video-bg__media" autoplay muted loop playsinline>
    <source src="<?= e(BASE_URL) ?>/assets/background.mp4" type="video/mp4">
  </video>
  <div class="video-bg__overlay"></div>
</div>

<main class="container">
  <div class="hero">
    <div class="hero__card">
      <div class="hero__badge">Quân đội nhân dân Việt Nam</div>
      <div class="hero__title">Hệ thống phát nhạc truyền thống</div>
      <div class="hero__sub">Bố trí danh mục rõ ràng, chuẩn hóa theo tuyến – ngành – binh chủng, sẵn sàng phục vụ tuyên truyền và sinh hoạt đơn vị.</div>
      <ul class="hero__details">
        <li>Hỗ trợ danh mục nhiều cấp và quản trị tập trung.</li>
        <li>Đảm bảo thống nhất nội dung, dễ truy cập, dễ vận hành.</li>
      </ul>
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

  <div class="page-author">
    <div class="page-author__label">Tác giả</div>
    <div class="page-author__name">Trung úy Nguyễn Văn Đúc</div>
    <div class="page-author__unit">Phó đội trưởng Vận động quần chúng • Đồn Biên phòng Cửa Lân</div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
