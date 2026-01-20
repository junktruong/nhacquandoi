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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $old = (string)($_POST['old'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $new2 = (string)($_POST['new2'] ?? '');

    try {
        if (strlen($new) < 6) throw new RuntimeException('Mật khẩu mới tối thiểu 6 ký tự.');
        if ($new !== $new2) throw new RuntimeException('Nhập lại mật khẩu không khớp.');

        $st = $pdo->prepare("SELECT password_hash FROM admins WHERE id = :id");
        $st->execute([':id' => (int)($_SESSION['admin_id'] ?? 0)]);
        $row = $st->fetch();
        if (!$row || !password_verify($old, (string)$row['password_hash'])) throw new RuntimeException('Mật khẩu cũ không đúng.');

        $pdo->prepare("UPDATE admins SET password_hash = :h WHERE id = :id")
            ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => (int)$_SESSION['admin_id']]);

        $flash = 'Đã đổi mật khẩu.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$title = 'Tài khoản — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container narrow">
  <div class="admin-top">
    <h2 class="section-title">TÀI KHOẢN</h2>
    <div class="admin-top__actions">
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/dashboard.php">Dashboard</a>
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/admin/logout.php">Đăng xuất</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="alert ok"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

  <div class="panel">
    <h3>Đổi mật khẩu</h3>
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

      <label class="label">Mật khẩu cũ</label>
      <input class="input" type="password" name="old" required>

      <label class="label">Mật khẩu mới</label>
      <input class="input" type="password" name="new" minlength="6" required>

      <label class="label">Nhập lại mật khẩu mới</label>
      <input class="input" type="password" name="new2" minlength="6" required>

      <button class="btn btn--gold" type="submit">Cập nhật</button>
    </form>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
