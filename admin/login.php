<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/csrf.php';

session_boot();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $u = trim((string)($_POST['username'] ?? ''));
    $p = (string)($_POST['password'] ?? '');

    if (login_admin($u, $p)) redirect('/admin/dashboard.php');
    $error = 'Sai tài khoản hoặc mật khẩu.';
}

$title = 'Admin login — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout_header.php';
?>
<main class="container narrow">
  <h2 class="section-title">ADMIN</h2>
  <div class="panel">
    <?php if ($error): ?><div class="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <label class="label">Tài khoản</label>
      <input class="input" name="username" autocomplete="username" required>

      <label class="label">Mật khẩu</label>
      <input class="input" type="password" name="password" autocomplete="current-password" required>

      <button class="btn btn--gold" type="submit">Đăng nhập</button>
      <a class="btn btn--ghost" href="<?= e(BASE_URL) ?>/">Về trang phát nhạc</a>
    </form>

    <p class="muted small">Mặc định: <code>admin / admin123</code> (đổi ngay trong Admin → Tài khoản).</p>
  </div>
</main>
<?php require_once __DIR__ . '/../includes/layout_footer.php'; ?>
