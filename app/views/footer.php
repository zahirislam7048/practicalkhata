</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong><?= e(setting('site_name', 'Practical Khata')) ?></strong>
      <p class="muted"><?= e(setting('tagline')) ?></p>
    </div>
    <div>
      <?php if (setting('contact_phone')): ?><div>📞 <?= e(setting('contact_phone')) ?></div><?php endif; ?>
      <?php if (setting('contact_email')): ?><div>✉️ <?= e(setting('contact_email')) ?></div><?php endif; ?>
      <?php if (setting('facebook_url')): ?><div><a href="<?= e(setting('facebook_url')) ?>" target="_blank" rel="noopener">Facebook পেজ</a></div><?php endif; ?>
    </div>
    <div class="muted">© <?= date('Y') ?> <?= e(setting('site_name', 'Practical Khata')) ?></div>
  </div>
</footer>
<?php require __DIR__ . '/tracking_events.php'; ?>
<script src="<?= url('assets/js/app.js') ?>?v=1"></script>
</body>
</html>
