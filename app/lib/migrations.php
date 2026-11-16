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
        5 => function () {
            // Owner chose green + yellow as the site colours
            q("INSERT INTO settings (`key`, `value`) VALUES ('theme', 'green') ON DUPLICATE KEY UPDATE `value` = 'green'");
        },
        6 => function () {
            // New product (draft): admin sets price + uploads the file, then turns it on
            $catId = val('SELECT id FROM categories WHERE slug = ?', [slugify('টাকা-পয়সা ও বিনিয়োগ')]);
            if (!$catId) {
                q('INSERT INTO categories (name, slug, icon, sort_order) VALUES (?,?,?,?)',
                  ['টাকা-পয়সা ও বিনিয়োগ', slugify('টাকা-পয়সা ও বিনিয়োগ'), '💰', 1]);
                $catId = (int)db()->lastInsertId();
            }
            if (!val('SELECT id FROM products WHERE title = ?', ['বেতনের টাকায় সঞ্চয় ও বিনিয়োগ'])) {
                q('INSERT INTO products (title, slug, category_id, short_desc, description, price, image, download_limit, is_active)
                   VALUES (?,?,?,?,?,?,?,?,0)', [
                    'বেতনের টাকায় সঞ্চয় ও বিনিয়োগ',
                    unique_slug('beton-er-takay-sanchay-o-biniyog'),
                    $catId,
                    'মাসের বেতন থেকে কীভাবে নিয়মিত সঞ্চয় করবেন ও ধাপে ধাপে বিনিয়োগ শুরু করবেন — সহজ বাংলায় প্র্যাকটিক্যাল গাইড।',
                    "চাকরির বেতন আসে, মাস শেষে কিছুই থাকে না? এই গাইডে সহজ বাংলায় ধাপে ধাপে দেখানো হয়েছে কীভাবে বেতনের টাকা থেকে নিয়মিত সঞ্চয় করে বিনিয়োগ শুরু করবেন।\n\n"
                    . "যা পাবেন:\n"
                    . "• মাসিক বাজেট বানানোর সহজ পদ্ধতি\n"
                    . "• বেতনের কত অংশ খরচ, সঞ্চয় ও বিনিয়োগে যাবে — তার হিসাব\n"
                    . "• জরুরি তহবিল (ইমার্জেন্সি ফান্ড) গড়ার উপায়\n"
                    . "• সঞ্চয়পত্র, DPS, FDR ও অন্যান্য বিকল্পের তুলনা\n"
                    . "• নিজের হিসাব রাখার জন্য প্র্যাকটিক্যাল খাতা\n\n"
                    . "কেনার পর অর্ডারের পেজ বা অ্যাকাউন্ট থেকে ফাইলটি ডাউনলোড করতে পারবেন।",
                    0,
                    'assets/img/sanchay-biniyog-cover.jpg',
                    5,
                ]);
            }
        },
        7 => function () {
            // Download-link email after approval
            if (!val("SHOW COLUMNS FROM orders LIKE 'email_sent_at'")) {
                db()->exec("ALTER TABLE orders ADD email_sent_at DATETIME NULL, ADD email_error VARCHAR(255) NULL");
            }
        },
        8 => function () {
            // e-Return guide (draft until the admin uploads the PDF and switches it on)
            $catName = 'ট্যাক্স ও সরকারি কাজ';
            $catId = val('SELECT id FROM categories WHERE slug = ?', [slugify($catName)]);
            if (!$catId) {
                q('INSERT INTO categories (name, slug, icon, sort_order) VALUES (?,?,?,?)', [$catName, slugify($catName), '🧾', 2]);
                $catId = (int)db()->lastInsertId();
            }
            $title = 'নিজের আয়কর রিটার্ন নিজে জমা দিন (e-Return)';
            if (!val('SELECT id FROM products WHERE title = ?', [$title])) {
                q('INSERT INTO products (title, slug, category_id, short_desc, description, price, sale_price, image, download_limit, is_active)
                   VALUES (?,?,?,?,?,?,?,?,?,0)', [
                    $title,
                    unique_slug('nijer-aykor-return-nije-joma-din-e-return'),
                    $catId,
                    'এজেন্ট ছাড়াই ঘরে বসে অনলাইনে আয়কর রিটার্ন জমা দেওয়ার ধাপে ধাপে বাংলা গাইড — কর হিসাবের উদাহরণ ও পূরণযোগ্য ওয়ার্কশিট সহ।',
                    "আয়কর রিটার্ন মানেই উকিলের অফিসে দৌড়ঝাঁপ আর বাড়তি খরচ — এমন নয়। এই গাইড দেখে চাকরিজীবী, শিক্ষক, ছোট ব্যবসায়ী বা গৃহিণী — যে কেউ নিজেই e-Return পোর্টালে রিটার্ন জমা দিতে পারবেন।\n\n"
                    . "যা পাবেন (২৬ পৃষ্ঠা, PDF):\n"
                    . "• কার রিটার্ন দিতে হবে, কী কী কাগজ লাগবে — টিক দেওয়ার চেকলিস্ট\n"
                    . "• অনলাইনে e-TIN নেওয়া ও e-Return পোর্টালে রেজিস্ট্রেশন\n"
                    . "• বেতনের আয়, কর হার ও স্তর অনুযায়ী কর হিসাবের সহজ নিয়ম\n"
                    . "• সঞ্চয়পত্র, DPS, বীমায় বিনিয়োগ করে বৈধভাবে কর কমানোর (রেয়াত) হিসাব\n"
                    . "• উৎসে কাটা কর ও ন্যূনতম করের নিয়ম\n"
                    . "• চাকরিজীবী, শিক্ষক ও দোকান মালিক — তিনজনের সম্পূর্ণ কর হিসাবের উদাহরণ\n"
                    . "• সম্পদ-দায় ও জীবনযাত্রার খরচের বিবরণী কীভাবে মেলাবেন\n"
                    . "• পোর্টালে ধাপে ধাপে পূরণ, অনলাইনে কর পরিশোধ ও প্রাপ্তিস্বীকার\n"
                    . "• সাধারণ ভুল, সময়সীমা, প্রশ্নোত্তর\n"
                    . "• ৬টি প্রিন্টযোগ্য ওয়ার্কশিট ও জমার আগের চেকলিস্ট\n\n"
                    . "দ্রষ্টব্য: এটি শিক্ষামূলক গাইড, আইনি পরামর্শ নয়। কর হার ও সময়সীমা প্রতি বছর বদলাতে পারে — জমার আগে NBR-এর সর্বশেষ নিয়ম মিলিয়ে নিন।",
                    149,
                    99,
                    'assets/img/aykor-return-cover.jpg',
                    5,
                ]);
            }
        },
        9 => function () {
            // Product reviews from verified buyers
            db()->exec("CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  order_item_id INT UNSIGNED NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  name VARCHAR(60) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  admin_reply TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_product (product_id, status),
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        },
        10 => function () {
            // Real customer feedback added by the admin (Facebook, WhatsApp...) — labelled with its source
            if (!val("SHOW COLUMNS FROM reviews LIKE 'source'")) {
                db()->exec("ALTER TABLE reviews ADD source VARCHAR(40) NULL AFTER admin_reply");
            }
        },
        11 => function () {
            // Sales made before the website (Facebook/WhatsApp), entered by the admin
            if (!val("SHOW COLUMNS FROM products LIKE 'prior_sales'")) {
                db()->exec("ALTER TABLE products ADD prior_sales INT UNSIGNED NOT NULL DEFAULT 0 AFTER download_limit");
            }
        },
        12 => function () {
            // Product links from the title (/product/পণ্যের-নাম); old links keep working via redirects
            db()->exec("CREATE TABLE IF NOT EXISTS product_slugs (
                old_slug VARCHAR(220) NOT NULL PRIMARY KEY, product_id INT UNSIGNED NOT NULL,
                CONSTRAINT fk_slug_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            foreach (rows('SELECT id, title, slug FROM products') as $p) {
                set_product_slug((int)$p['id'], unique_slug($p['title'], (int)$p['id']));
            }
        },
        13 => function () {
            // The savings book is written: describe its real contents (only if the admin hasn't edited the text)
            $p = row('SELECT id, description FROM products WHERE title = ?', ['বেতনের টাকায় সঞ্চয় ও বিনিয়োগ']);
            if ($p && str_starts_with((string)$p['description'], 'চাকরির বেতন আসে, মাস শেষে কিছুই থাকে না?')
                && str_contains((string)$p['description'], 'নিজের হিসাব রাখার জন্য প্র্যাকটিক্যাল খাতা')) {
                q('UPDATE products SET short_desc = ?, description = ? WHERE id = ?', [
                    'মাস শেষে টাকা থাকে না? বেতন ভাগ করার সূত্র, জরুরি তহবিল, ঋণমুক্তি আর সঞ্চয়পত্র-DPS-বন্ড-শেয়ারে নিরাপদ বিনিয়োগের ধাপে ধাপে বাংলা গাইড — ওয়ার্কশিট সহ।',
                    "চাকরির বেতন আসে, মাস শেষে কিছুই থাকে না? এই বইয়ে সহজ বাংলায় দেখানো হয়েছে কীভাবে বেতনের টাকা পরিকল্পনা করে ভাগ করবেন, নিয়মিত সঞ্চয় করবেন আর বুঝে-শুনে নিরাপদে বিনিয়োগ শুরু করবেন।\n\n"
                    . "যা পাবেন (২৪ পৃষ্ঠা, PDF):\n"
                    . "• কেন টাকা থাকে না — ৭টি সাধারণ কারণ ও ৩০ দিনের খরচ-পরীক্ষা\n"
                    . "• বেতন ভাগ করার সূত্র (৫০-৩০-২০ ও বাংলাদেশি রূপ) ও \"আগে নিজেকে দিন\" অটোমেটিক সঞ্চয়\n"
                    . "• জরুরি তহবিল কত রাখবেন, কোথায় রাখবেন\n"
                    . "• ক্রেডিট কার্ড ও ঋণ থেকে বের হওয়ার পরিকল্পনা\n"
                    . "• মূল্যস্ফীতি ও চক্রবৃদ্ধির জাদু — মাসে ৫,০০০ টাকা ২০ বছরে কত হয়\n"
                    . "• সঞ্চয়পত্র, DPS, FDR, ট্রেজারি বন্ড, শেয়ার, মিউচুয়াল ফান্ড, সোনা, জমি ও বীমা — কোনটা কার জন্য\n"
                    . "• প্রতারণা চেনার ১০টি লক্ষণ\n"
                    . "• বিনিয়োগ করে আয়কর রেয়াত পাওয়ার হিসাব\n"
                    . "• ২৫ হাজার, ৫০ হাজার ও ১ লাখ টাকা আয়ের তিনটি নমুনা পরিকল্পনা\n"
                    . "• মাসিক বাজেট, নেট ওয়ার্থ ও বিনিয়োগ রেজিস্টারের ওয়ার্কশিট + ১২ মাসের সঞ্চয় চ্যালেঞ্জ\n\n"
                    . "দ্রষ্টব্য: শিক্ষামূলক গাইড, ব্যক্তিগত আর্থিক পরামর্শ নয়। মুনাফার হার ও নিয়ম পরিবর্তনশীল — বিনিয়োগের আগে সর্বশেষ তথ্য যাচাই করুন।",
                    $p['id'],
                ]);
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
