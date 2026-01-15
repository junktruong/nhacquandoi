<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();
$pdo = db();

$flash = '';
$error = '';

$leaf = leaf_categories($pdo);
$filterCat = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'upload') {
            $category_id = (int)($_POST['category_id'] ?? 0);
            $title = trim((string)($_POST['title'] ?? ''));
            $note = trim((string)($_POST['note'] ?? ''));

            if ($category_id <= 0) throw new RuntimeException('Chọn danh mục (mục cấp cuối).');
            if ($title === '') throw new RuntimeException('Thiếu tiêu đề bài hát.');
            if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Chọn file audio để upload.');

            $f = $_FILES['audio'];
            if ((int)$f['size'] > MAX_UPLOAD_BYTES) throw new RuntimeException('File quá lớn.');

            $tmp = (string)$f['tmp_name'];
            $orig = (string)$f['name'];

            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($tmp) ?: 'application/octet-stream';

            $allowed = $GLOBALS['ALLOWED_AUDIO_MIME'] ?? [];
            if (!isset($allowed[$mime])) throw new RuntimeException('Định dạng không hỗ trợ. MIME: ' . $mime);

            $ext = $allowed[$mime];
            $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
            $dest = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $safeName;

            if (!move_uploaded_file($tmp, $dest)) throw new RuntimeException('Không lưu được file upload.');
            @chmod($dest, 0644);

            $pdo->prepare("INSERT INTO songs(category_id, title, note, filename, mime, original_name, uploaded_at)
                           VALUES(:cid,:title,:note,:fn,:mime,:orig,:t)")
                ->execute([
                    ':cid' => $category_id,
                    ':title' => $title,
                    ':note' => $note !== '' ? $note : null,
                    ':fn' => $safeName,
                    ':mime' => $mime,
                    ':orig' => $orig,
                    ':t' => now_iso()
                ]);

            $flash = 'Đã upload & thêm bài hát.';
            $filterCat = $category_id;
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new RuntimeException('Thiếu ID.');

            $st = $pdo->prepare("SELECT filename FROM songs WHERE id = :id");
            $st->execute([':id' => $id]);
            $row = $st->fetch();
            if ($row) {
                $file = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $row['filename'];
                if (is_file($file)) @unlink($file);
            }
            $pdo->prepare("DELETE FROM songs WHERE id = :id")->execute([':id' => $id]);
            $flash = 'Đã xóa bài hát.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($filterCat > 0) {
    $st = $pdo->prepare("SELECT s.*, c.name AS category_name
                         FROM songs s JOIN categories c ON c.id = s.category_id
                         WHERE s.category_id = :cid
                         ORDER BY s.id DESC");
    $st->execute([':cid' => $filterCat]);
    $songs = $st->fetchAll();
} else {
    $songs = $pdo->query("SELECT s.*, c.name AS category_name
                          FROM songs s JOIN categories c ON c.id = s.category_id
                          ORDER BY s.id DESC
                          LIMIT 200")->fetchAll();
}

$title = 'Bài hát — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container">
  <div class="admin-top">
    <h2 class="section-title">BÀI HÁT</h2>
    <div class="admin-top__actions">
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/dashboard.php">Dashboard</a>
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/logout.php">Đăng xuất</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert ok"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

  <div class="grid grid--admin2">
    <div class="panel">
      <h3>Upload bài hát</h3>
      <form method="post" class="form" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">

        <label class="label">Danh mục (chọn mục cấp cuối)</label>
        <select class="input" name="category_id" required>
          <option value="">— Chọn —</option>
          <?php foreach ($leaf as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?> (ID <?= (int)$c['id'] ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label class="label">Tiêu đề bài hát</label>
        <input class="input" name="title" required>

        <label class="label">Ghi chú (tuỳ chọn)</label>
        <input class="input" name="note" placeholder="VD: tempo/phiên bản...">

        <label class="label">File audio</label>
        <input class="input" type="file" name="audio" accept="audio/*" required>

        <button class="btn btn--gold" type="submit">Upload</button>
      </form>

      <p class="muted small">Không thấy danh mục? Vào “Danh mục” để tạo cây trước (ví dụ thêm binh chủng con).</p>
    </div>

    <div class="panel">
      <h3>Danh sách bài hát</h3>

      <form method="get" class="form form--row">
        <label class="label">Lọc theo danh mục</label>
        <select class="input" name="cat" onchange="this.form.submit()">
          <option value="0">— Tất cả —</option>
          <?php foreach ($leaf as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="table">
        <div class="tr th">
          <div>ID</div><div>Tiêu đề</div><div>Danh mục</div><div>Nghe thử</div><div></div>
        </div>

        <?php foreach ($songs as $s): ?>
          <div class="tr">
            <div><?= (int)$s['id'] ?></div>
            <div>
              <div><b><?= e($s['title']) ?></b></div>
              <?php if (!empty($s['note'])): ?><div class="muted small"><?= e((string)$s['note']) ?></div><?php endif; ?>
            </div>
            <div><?= e($s['category_name']) ?></div>
            <div><audio controls preload="none" src="<?= e(UPLOAD_URL . '/' . $s['filename']) ?>"></audio></div>
            <div class="actions">
              <form method="post" class="inline" onsubmit="return confirm('Xóa bài này?');">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn--mini btn--danger" type="submit">Xóa</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
