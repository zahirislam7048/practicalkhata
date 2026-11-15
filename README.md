# Practicalkhata.com

ডিজিটাল খাতা/নোট বিক্রির ই-কমার্স সাইট — PHP 8 + MySQL, Namecheap cPanel এ চলে।

- কাস্টমার: রেজিস্টার/লগইন, কার্ট, কুপন, bKash/Nagad (manual Send Money + TrxID) চেকআউট, অ্যাকাউন্ট থেকে ডাউনলোড (সীমা সহ)
- অ্যাডমিন (`/admin/`): ড্যাশবোর্ড, পেমেন্ট যাচাই করে অর্ডার অনুমোদন/বাতিল, পণ্য যোগ/এডিট (ছবি + ফাইল), কুপন, কাস্টমার, সেটিংস
- পেইড ফাইলগুলো `public_html` এর বাইরে থাকে — লিংক শেয়ার করে কেউ ডাউনলোড করতে পারবে না

## ফোল্ডার

| Repo তে | সার্ভারে যায় | কী আছে |
|---|---|---|
| `public/` | `~/public_html` | ওয়েবসাইটের পেজ, CSS, ছবি |
| `app/` | `~/practicalkhata_app` | কোড, `config.php`, পেইড ফাইল (`storage/files`) |
| `database/schema.sql` | `~/practicalkhata_app/schema.sql` | ডাটাবেস টেবিল |

`config.php`, আপলোড করা ছবি ও ফাইল Git এ নেই, তাই deploy করলে কখনো মুছে যাবে না।

## প্রথমবার সেটআপ (cPanel)

1. **PHP ভার্সন**: cPanel → *Select PHP Version* / *MultiPHP Manager* → PHP **8.1 বা নতুন**।
2. **ডাটাবেস**: cPanel → *MySQL® Database Wizard* → ডাটাবেস + ইউজার বানান, ইউজারকে **ALL PRIVILEGES** দিন। নাম/পাসওয়ার্ড লিখে রাখুন।
3. **Git Version Control**: cPanel → *Git™ Version Control* → *Create*
   - Clone URL: `https://github.com/zahirislam7048/practicalkhata.git`
   - Repository Path: `repositories/practicalkhata`
   - তৈরি হলে → *Manage* → *Pull or Deploy* → **Deploy HEAD Commit**
4. **ইনস্টল**: ব্রাউজারে `https://practicalkhata.com/install.php` খুলুন → ডাটাবেসের তথ্য ও অ্যাডমিন ইমেইল/পাসওয়ার্ড দিন।
5. **সেটিংস**: অ্যাডমিন → সেটিংস → bKash / Nagad নাম্বার দিন। এরপর পণ্য যোগ করুন।
6. **SSL**: cPanel → *SSL/TLS Status* → AutoSSL চালু করুন (সাইট https এ যাবে)।

> Addon Domain হলে `.cpanel.yml` এ `WEBROOT` কে ডোমেইনের ফোল্ডারে বদলান (যেমন `$HOME/practicalkhata.com`)।

## GitHub থেকে অটো আপডেট

`main` ব্রাঞ্চে push হলে `.github/workflows/deploy.yml` cPanel কে বলে নতুন কোড pull করে `.cpanel.yml` চালাতে।
একবার GitHub repo → **Settings → Secrets and variables → Actions** এ এই ৪টি secret যোগ করুন:

| Secret | কোথায় পাবেন |
|---|---|
| `CPANEL_HOST` | cPanel হোমের *General Information* → Server Name / hostname (যেমন `server123.web-hosting.com`) |
| `CPANEL_USER` | আপনার cPanel ইউজারনেম |
| `CPANEL_TOKEN` | cPanel → *Security → Manage API Tokens* → Create |
| `CPANEL_REPO_PATH` | Git Version Control এ দেখানো Repository Path (যেমন `/home/USER/repositories/practicalkhata`) |

Secret না দিলে: cPanel → Git Version Control → Manage → *Pull or Deploy* → **Update from Remote** তারপর **Deploy HEAD Commit** হাতে চাপুন।

## বড় ফাইল আপলোড

ফাইল আপলোডে "অনেক বড়" এরর এলে cPanel → *MultiPHP INI Editor* → `upload_max_filesize` ও `post_max_size` বাড়ান (যেমন 64M)।

## লোকালি চালানো

```bash
cp app/config.sample.php app/config.php   # ডাটাবেস তথ্য দিন
php -S localhost:8000 -t public
```
