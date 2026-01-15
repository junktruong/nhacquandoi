<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(404); exit('Not found'); }

$st = $pdo->prepare("SELECT * FROM categories WHERE id = :id");
$st->execute([':id' => $id]);
$cat = $st->fetch();
if (!$cat) { http_response_code(404); exit('Not found'); }

$children = categories_children($pdo, (int)$cat['id']);

$parent = null;
$siblings = [];
if (!empty($cat['parent_id'])) {
    $pst = $pdo->prepare("SELECT * FROM categories WHERE id = :pid");
    $pst->execute([':pid' => (int)$cat['parent_id']]);
    $parent = $pst->fetch();
    $siblings = categories_children($pdo, (int)$cat['parent_id']);
} else {
    $siblings = $children;
}

$songs = [];
if (!$children) {
    $ss = $pdo->prepare("SELECT * FROM songs WHERE category_id = :id ORDER BY id DESC");
    $ss->execute([':id' => (int)$cat['id']]);
    $songs = $ss->fetchAll();
}

$title = $cat['name'] . ' — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container">
  <div class="two-col">
    <aside class="sidebar">
      <div class="sidebar__top">
        <a class="back" href="<?= e(BASE_URL) ?>/">← Danh mục</a>
        <div class="sidebar__title"><?= e($parent ? $parent['name'] : $cat['name']) ?></div>
      </div>

      <div class="sidebar__list">
        <?php if ($siblings): ?>
          <?php foreach ($siblings as $s): ?>
            <a class="pill <?= (int)$s['id'] === (int)$cat['id'] ? 'pill--active' : '' ?>"
               href="<?= e(BASE_URL) ?>/category.php?id=<?= (int)$s['id'] ?>">
              <?= e($s['name']) ?>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">Chưa có mục con.</div>
        <?php endif; ?>
      </div>
    </aside>

    <section class="main">
      <div class="player-card">
        <div class="logo-wrap">
  <img id="logoSpin" class="logo" src="<?= e(BASE_URL) ?>/assets/Logo_BĐBPVN.png" alt="logo">
</div>


        <?php if ($children): ?>
          <h2 class="big-title"><?= e($cat['name']) ?></h2>
          <p class="muted">Danh mục này có mục con. Chọn mục ở bên trái hoặc bấm một mục phía dưới.</p>

          <div class="grid grid--subs">
            <?php foreach ($children as $ch): ?>
              <a class="card card--sub" href="<?= e(BASE_URL) ?>/category.php?id=<?= (int)$ch['id'] ?>">
                <div class="card__title"><?= e($ch['name']) ?></div>
              </a>
            <?php endforeach; ?>
          </div>

        <?php else: ?>
          <h2 class="big-title"><?= e($cat['name']) ?></h2>

          <div class="player">
            <audio id="audio" preload="metadata"></audio>

            <div class="player__now">
              <div class="now__label">Đang chọn:</div>
              <div class="now__title" id="nowTitle">Chưa chọn bài</div>
            </div>

            <div class="player__controls">
              <button class="icon-btn" id="btnPrev" title="Bài trước">⏮</button>
              <button class="play-btn" id="btnPlay" title="Phát/Tạm dừng">▶</button>
              <button class="icon-btn" id="btnNext" title="Bài sau">⏭</button>
            </div>

            <div class="player__progress">
              <span class="time" id="tCur">00:00</span>
              <input type="range" id="seek" min="0" max="1000" value="0" />
              <span class="time" id="tDur">00:00</span>
            </div>

            <div class="player__volume">
              <span class="muted">Âm lượng</span>
              <input type="range" id="vol" min="0" max="100" value="70" />
            </div>

            <div class="player__filters">
              <span class="muted">Chế độ phát</span>
              <label class="toggle">
                <span class="toggle__label">Có lời</span>
                <input type="checkbox" id="lyricsToggle" aria-label="Chuyển đổi có lời và nhạc nền">
                <span class="toggle__track"></span>
                <span class="toggle__label">Nhạc nền</span>
              </label>
            </div>
          </div>

          <div class="list-head">
            <h3>CHỌN BÀI HÁT</h3>
            <input class="search" id="songSearch" placeholder="Tìm nhanh…" />
          </div>

          <div class="song-list" id="songList">
            <?php if (!$songs): ?>
              <div class="empty">Chưa có bài hát. Vào <a href="<?= e(BASE_URL) ?>/admin">Admin</a> để upload.</div>
            <?php endif; ?>

            <?php foreach ($songs as $i => $song): ?>
              <?php $mediaType = $song['media_type'] ?? 'audio'; ?>
              <button class="song-item"
                      type="button"
                      data-index="<?= (int)$i ?>"
                      data-title="<?= e($song['title']) ?>"
                      data-src="<?= e(UPLOAD_URL . '/' . $song['filename']) ?>"
                      data-media="<?= e($mediaType) ?>"
                      data-lyrics="<?= !empty($song['has_lyrics']) ? '1' : '0' ?>">
                <span class="song-item__idx"><?= (int)($i + 1) ?></span>
                <span class="song-item__title"><?= e($song['title']) ?></span>
                <span class="song-item__meta <?= !empty($song['has_lyrics']) ? 'song-item__meta--lyrics' : 'song-item__meta--instrumental' ?>">
                  <?= !empty($song['has_lyrics']) ? 'Có lời' : 'Nhạc nền' ?>
                </span>
                <?php if ($mediaType === 'video'): ?>
                  <span class="song-item__video">
                    <span class="video-btn" role="button" tabindex="0" data-action="video" title="Mở video">🎬</span>
                  </span>
                <?php endif; ?>
              </button>
            <?php endforeach; ?>
          </div>

          <div class="video-modal" id="videoModal" hidden>
            <div class="video-modal__backdrop" data-action="close"></div>
            <div class="video-modal__card">
              <div class="video-modal__head">
                <div class="video-modal__title" id="videoTitle">Video</div>
                <button class="icon-btn" type="button" data-action="close" title="Đóng">✕</button>
              </div>
              <video id="videoPlayer" controls preload="metadata"></video>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
