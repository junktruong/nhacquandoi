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
  <video class="video-bg__media" autoplay muted loop playsinline></video>
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
<script>
  (() => {
    const video = document.querySelector('.video-bg__media');
    if (!video || !window.MediaRecorder) {
      return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 640;
    canvas.height = 360;
    const ctx = canvas.getContext('2d');
    const stream = canvas.captureStream(30);
    const recorder = new MediaRecorder(stream, { mimeType: 'video/webm; codecs=vp9' });
    const chunks = [];

    recorder.ondataavailable = (event) => {
      if (event.data.size) {
        chunks.push(event.data);
      }
    };

    recorder.onstop = () => {
      const blob = new Blob(chunks, { type: 'video/webm' });
      video.src = URL.createObjectURL(blob);
      video.play().catch(() => undefined);
    };

    const drawFrame = (time) => {
      const t = time / 1000;
      const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
      grad.addColorStop(0, 'rgba(10, 20, 15, 0.9)');
      grad.addColorStop(0.5, `rgba(214, 164, 0, ${0.15 + 0.1 * Math.sin(t)})`);
      grad.addColorStop(1, 'rgba(5, 10, 8, 0.95)');
      ctx.fillStyle = grad;
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      ctx.fillStyle = 'rgba(255, 255, 255, 0.05)';
      for (let i = 0; i < 25; i += 1) {
        const x = (Math.sin(t + i) * 0.5 + 0.5) * canvas.width;
        const y = (Math.cos(t + i * 0.4) * 0.5 + 0.5) * canvas.height;
        ctx.beginPath();
        ctx.arc(x, y, 40 + 20 * Math.sin(t + i), 0, Math.PI * 2);
        ctx.fill();
      }
    };

    recorder.start(100);
    const start = performance.now();
    const animate = (now) => {
      drawFrame(now - start);
      if (now - start < 2000) {
        requestAnimationFrame(animate);
      } else {
        recorder.stop();
      }
    };

    requestAnimationFrame(animate);
  })();
</script>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
