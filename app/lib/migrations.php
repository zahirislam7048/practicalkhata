<?php
/**
 * Small one-way data/schema updates for sites that are already installed.
 * Runs when an admin opens the admin panel; each step runs once (tracked in settings.db_version).
 */
function run_migrations(): void
{
    $version = (int)setting('db_version', '0');
    $steps = [
        1 => function () {
            // Draft product: admin sets the price, uploads the file, then turns it on.
            if (val('SELECT id FROM products WHERE title = ?', ['৩০ দিনের Instagram রূপকথা'])) {
                return;
            }
            q('INSERT INTO products (title, slug, short_desc, description, price, image, download_limit, is_active)
               VALUES (?,?,?,?,?,?,?,0)', [
                '৩০ দিনের Instagram রূপকথা',
                unique_slug('30-diner-instagram-rupkotha'),
                '৩০ দিনে ধাপে ধাপে আপনার Instagram সাজান — প্রতিদিনের কাজ, কনটেন্ট আইডিয়া ও ক্যাপশন।',
                "৩০ দিনের Instagram রূপকথা একটি ডিজিটাল গাইড, যেখানে প্রতিদিন একটি করে কাজ দেওয়া আছে।\n\n"
                . "যা পাবেন:\n"
                . "• ৩০ দিনের দৈনিক টাস্ক প্ল্যান\n"
                . "• Reels ও পোস্টের কনটেন্ট আইডিয়া\n"
                . "• রেডি ক্যাপশন ও হ্যাশট্যাগ গাইড\n"
                . "• প্রোফাইল ও বায়ো সাজানোর টিপস\n"
                . "• প্রতিদিনের অগ্রগতি লেখার খাতা\n\n"
                . "কেনার পর আপনার অ্যাকাউন্ট থেকে ফাইলটি ডাউনলোড করতে পারবেন।",
                0,
                'assets/img/rupkotha-cover.jpg',
                5,
            ]);
        },
        2 => function () {
            // Guest checkout: orders without an account, reached by a secret link
            if (!val("SHOW COLUMNS FROM orders LIKE 'access_token'")) {
                db()->exec("ALTER TABLE orders
                    MODIFY user_id INT UNSIGNED NULL,
                    ADD guest_name VARCHAR(120) NULL AFTER user_id,
                    ADD guest_phone VARCHAR(20) NULL AFTER guest_name,
                    ADD guest_email VARCHAR(190) NULL AFTER guest_phone,
                    ADD access_token CHAR(40) NULL AFTER guest_email,
                    ADD UNIQUE KEY uniq_token (access_token)");
            }
            foreach (rows('SELECT id FROM orders WHERE access_token IS NULL') as $o) {
                q('UPDATE orders SET access_token = ? WHERE id = ?', [bin2hex(random_bytes(20)), $o['id']]);
            }
        },
        3 => function () {
            // "Payment Due" status: admin asks for the remaining amount, customer sends another TrxID
            if (!val("SHOW COLUMNS FROM orders LIKE 'due_amount'")) {
                db()->exec("ALTER TABLE orders
                    MODIFY status ENUM('pending','paid','rejected','due') NOT NULL DEFAULT 'pending',
                    ADD due_amount DECIMAL(10,2) NULL AFTER status,
                    ADD payment_log TEXT NULL AFTER due_amount");
            }
        },
        4 => function () {
            // Marketplace layout: categories, homepage banners
            db()->exec("CREATE TABLE IF NOT EXISTS categories (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(140) NOT NULL UNIQUE,
                icon VARCHAR(16) NULL, sort_order INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            db()->exec("CREATE TABLE IF NOT EXISTS banners (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, image VARCHAR(255) NOT NULL, title VARCHAR(200) NULL, link VARCHAR(255) NULL,
                sort_order INT NOT NULL DEFAULT 0, is_active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            if (!val("SHOW COLUMNS FROM products LIKE 'category_id'")) {
                db()->exec("ALTER TABLE products ADD category_id INT UNSIGNED NULL AFTER slug, ADD KEY idx_cat (category_id)");
            }
            if (!val('SELECT COUNT(*) FROM categories')) {
                $defaults = [['Instagram ও সোশ্যাল মিডিয়া', '📱'], ['প্র্যাকটিক্যাল খাতা', '📒'], ['নোট ও গাইড', '📚'], ['ফ্রি রিসোর্স', '🎁']];
                foreach ($defaults as $i => [$name, $icon]) {
                    q('INSERT INTO categories (name, slug, icon, sort_order) VALUES (?,?,?,?)', [$name, slugify($name), $icon, $i]);
                }
                $ig = val('SELECT id FROM categories WHERE icon = ?', ['📱']);
                q("UPDATE products SET category_id = ? WHERE title LIKE '%Instagram%' AND category_id IS NULL", [$ig]);
            }
        },
    ];
    foreach ($steps as $n => $step) {
        if ($n > $version) {
            $step();
            q('INSERT INTO settings (`key`, `value`) VALUES (\'db_version\', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [(string)$n]);
        }
    }
}
