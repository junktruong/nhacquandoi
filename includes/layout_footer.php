  <footer class="footer">
    <div>© <?= date('Y') ?> • <?= e(APP_NAME) ?></div>
  </footer>
</div>
  <?php if (!empty($extra_js) && is_array($extra_js)): foreach ($extra_js as $js): ?>
    <script src="<?= e(BASE_URL) ?><?= e($js) ?>"></script>
  <?php endforeach; endif; ?>
<script src="<?= e(BASE_URL) ?>/assets/app.js"></script>
</body>
</html>
