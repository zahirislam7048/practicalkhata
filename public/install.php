<?php
/*
 * One-time installer: writes app config, creates tables, creates the first admin.
 * Once an admin exists it refuses to run again.
 */
define('PK_INSTALLER', true);
require __DIR__ . '/_init.php';

function installed(): bool
{
    if ($GLOBALS['config'] === null) {
        return false;
    }
    try {
        return (bool)val("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    } catch (Throwable $t) {
        return false;
    }
}

if (installed()) {
    http_response_code(403);
    exit('Practicalkhata ইতিমধ্যে ইনস্টল করা আছে। <a href="admin/">অ্যাডমিন প্যানেলে যান</a>');
}

$errors = [];
$configText = null;
$hasConfig = $GLOBALS['config'] !== null;

if (is_post()) {
    csrf_check();
    $cfg = $GLOBALS['config'] ?? [
        'db_host'  => input('db_host', 'localhost') ?: 'localhost',
        'db_name'  => input('db_name'),
        'db_user'  => input('db_user'),
        'db_pass'  => (string)($_POST['db_pass'] ?? ''),
        'base_url' => '',
        'debug'    => false,
    ];
    $name = input('admin_name');
    $email = mb_strtolower(input('admin_email'));
    $pass = (string)($_POST['admin_pass'] ?? '');

    if ($name === '') $errors[] = 'অ্যাডমিনের নাম দিন।';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'সঠিক ইমেইল দিন।';
    if (mb_strlen($pass) < 8) $errors[] = 'অ্যাডমিন পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের দিন।';

    if (!$errors) {
        try {
            $GLOBALS['config'] = $cfg;
            $pdo = db();
            $schemaFile = is_file(APP_DIR . '/schema.sql') ? APP_DIR . '/schema.sql' : dirname(APP_DIR) . '/database/schema.sql';
            $schema = file_get_contents($schemaFile);
            foreach (preg_split('/;\s*$/m', (string)$schema) as $stmt) {
                $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                }
            }
            $existing = val('SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                q("UPDATE users SET role = 'admin', name = ?, password_hash = ? WHERE id = ?", [$name, password_hash($pass, PASSWORD_DEFAULT), $existing]);
            } else {
                q("INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,'admin')", [$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
            }
            if (!$hasConfig) {
                $configText = "<?php\nreturn " . var_export($cfg, true) . ";\n";
                if (@file_put_contents(CONFIG_FILE, $configText) !== false) {
                    @chmod(CONFIG_FILE, 0640);
                    $configText = null;
                }
            }
            if ($configText === null) {
                login_user((int)val('SELECT id FROM users WHERE email = ?', [$email]));
                flash('success', 'ইনস্টল সম্পন্ন! প্রথমে সেটিংসে bKash/Nagad নাম্বার দিন।');
                redirect('admin/settings.php');
            }
        } catch (PDOException $ex) {
            $GLOBALS['config'] = $hasConfig ? $GLOBALS['config'] : null;
            $errors[] = 'ডাটাবেস সংযোগ/সেটআপ ব্যর্থ: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Practicalkhata ইনস্টল</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<main class="container main">
<?php if ($configText !== null): ?>
  <div class="card form narrow">
    <h1>প্রায় শেষ!</h1>
    <p>ডাটাবেস তৈরি হয়েছে, কিন্তু config ফাইল লেখা যায়নি। cPanel → File Manager এ
      <code><?= e(CONFIG_FILE) ?></code> ফাইল তৈরি করে নিচের লেখা পেস্ট করুন, তারপর <a href="login.php">লগইন</a> করুন।</p>
    <textarea rows="12" readonly><?= e($configText) ?></textarea>
  </div>
<?php else: ?>
  <form method="post" class="card form narrow">
    <h1>Practicalkhata ইনস্টল</h1>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
    <?= csrf_field() ?>
    <?php if (!$hasConfig): ?>
      <h3>১. MySQL ডাটাবেস</h3>
      <p class="muted small">cPanel → <b>MySQL® Database Wizard</b> দিয়ে একটি ডাটাবেস ও ইউজার তৈরি করে (ALL PRIVILEGES দিয়ে) এখানে লিখুন।</p>
      <label>DB Host<input name="db_host" value="<?= e(input('db_host', 'localhost')) ?>"></label>
      <label>DB Name<input name="db_name" value="<?= e(input('db_name')) ?>" placeholder="cpaneluser_khata" required></label>
      <label>DB User<input name="db_user" value="<?= e(input('db_user')) ?>" placeholder="cpaneluser_khata" required></label>
      <label>DB Password<input type="password" name="db_pass"></label>
    <?php else: ?>
      <p class="muted small">config.php পাওয়া গেছে — ডাটাবেস সেটআপ সেখান থেকেই হবে।</p>
    <?php endif; ?>
    <h3><?= $hasConfig ? '' : '২. ' ?>অ্যাডমিন অ্যাকাউন্ট</h3>
    <label>নাম<input name="admin_name" value="<?= e(input('admin_name')) ?>" required></label>
    <label>ইমেইল<input type="email" name="admin_email" value="<?= e(input('admin_email')) ?>" required></label>
    <label>পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)<input type="password" name="admin_pass" required minlength="8"></label>
    <button class="btn btn-block">ইনস্টল করুন</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
