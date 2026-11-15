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
    ];
    foreach ($steps as $n => $step) {
        if ($n > $version) {
            $step();
            q('INSERT INTO settings (`key`, `value`) VALUES (\'db_version\', ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [(string)$n]);
        }
    }
}
