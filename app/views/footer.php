</main>
<?php $wa = whatsapp_link('আসসালামু আলাইকুম, আমি ' . setting('site_name', 'Practical Khata') . ' থেকে একটি পণ্য সম্পর্কে জানতে চাই।'); ?>
<footer class="site-footer">
  <div class="container footer-trust">
    <div><?= icon('zap', 26) ?><b>সাথে সাথে ডাউনলোড</b><span>পেমেন্ট যাচাইয়ের পরই</span></div>
    <div><?= icon('wallet', 26) ?><b>bKash / Nagad</b><span>সহজ পেমেন্ট</span></div>
    <div><?= icon('shield', 26) ?><b>নিরাপদ অ্যাকাউন্ট</b><span>ফাইল শুধু আপনার জন্য</span></div>
    <div><?= icon('support', 26) ?><b>সাপোর্ট</b><span>যেকোনো সমস্যায় পাশে আছি</span></div>
  </div>
  <div class="container footer-grid">
    <div>
      <a class="brand brand-light" href="<?= url() ?>"><span class="brand-mark">PK</span> <?= e(setting('site_name', 'Practical Khata')) ?></a>
      <p><?= e(setting('tagline', 'পড়াশোনার ডিজিটাল খাতা ও নোট')) ?></p>
      <div class="pay-badges"><span class="pb-bkash">bKash</span><span class="pb-nagad">Nagad</span></div>
    </div>
    <div>
      <h4>দোকান</h4>
      <a href="<?= url() ?>">হোম</a>
      <a href="<?= url('index.php?sort=new#products') ?>">সব পণ্য</a>
      <a href="<?= url('index.php?offer=1#products') ?>">অফার</a>
      <a href="<?= url('cart.php') ?>">কার্ট</a>
    </div>
    <div>
      <h4>আমার অ্যাকাউন্ট</h4>
      <a href="<?= url('account.php') ?>">আমার ডাউনলোড</a>
      <a href="<?= url('track.php') ?>">অর্ডার ট্র্যাক করুন</a>
      <a href="<?= url('login.php') ?>">লগইন</a>
      <a href="<?= url('register.php') ?>">রেজিস্টার</a>
    </div>
    <div>
      <h4>যোগাযোগ</h4>
      <?php if (setting('contact_phone')): ?><a href="tel:<?= e(setting('contact_phone')) ?>"><?= icon('phone', 16) ?> <?= e(setting('contact_phone')) ?></a><?php endif; ?>
      <?php if (setting('contact_email')): ?><a href="mailto:<?= e(setting('contact_email')) ?>"><?= icon('mail', 16) ?> <?= e(setting('contact_email')) ?></a><?php endif; ?>
      <?php if ($wa): ?><a href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('phone', 16) ?> WhatsApp</a><?php endif; ?>
      <?php if (setting('facebook_url')): ?><a href="<?= e(setting('facebook_url')) ?>" target="_blank" rel="noopener"><?= icon('fb', 16) ?> Facebook পেজ</a><?php endif; ?>
    </div>
  </div>
  <div class="footer-bottom"><div class="container">© <?= bn(date('Y')) ?> <?= e(setting('site_name', 'Practical Khata')) ?> — সর্বস্বত্ব সংরক্ষিত</div></div>
</footer>

<?php if ($wa): ?>
<a class="wa-float" href="<?= e($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp এ যোগাযোগ">
  <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8s-.4-.1-.6.1-.7.8-.8 1-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.8-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7a2.7 2.7 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.2-.2-.5-.3z"/></svg>
</a>
<?php endif; ?>

<nav class="mobile-nav">
  <a href="<?= url() ?>"><?= icon('home', 22) ?><span>হোম</span></a>
  <a href="<?= url('index.php?offer=1#products') ?>"><?= icon('tag', 22) ?><span>অফার</span></a>
  <a href="<?= url('cart.php') ?>" class="mn-cart"><?= icon('cart', 22) ?><?php if (cart_count()): ?><b><?= bn(cart_count()) ?></b><?php endif; ?><span>কার্ট</span></a>
  <a href="<?= url(current_user() ? 'account.php' : 'track.php') ?>"><?= icon('download', 22) ?><span>ডাউনলোড</span></a>
</nav>
<?php require __DIR__ . '/tracking_events.php'; ?>
<script src="<?= url('assets/js/app.js') ?>?v=3"></script>
</body>
</html>
