# Parallel staging deploy — review-bstg.beastierated.com

Goal: deploy the `carousel-data` review-page build to a NEW backend staging host
`review-bstg.beastierated.com` on the existing server, **without touching
`bstg.beastierated.com` in any way** (no vhost edits, no restart, no code, no DB).

Everything below is isolated: a separate code dir (`/home/beastierated/backend-review`),
a separate `.env`, a **separate fresh empty database**, a separate Apache vhost
file, and a separate TLS cert. `apache2ctl configtest` + graceful `reload` (never
`restart`) mean the running `bstg` vhost keeps serving throughout.

Run as the deploy user (sudo where shown). Replace `SERVER_IP`, `<USER>`
(= `SERVER_USER`, e.g. `beastierated`), and `<DB_PASS>`.

---

## 0. Verify bstg's DB engine (READ-ONLY — does not change bstg)

```bash
ssh <USER>@SERVER_IP
grep -E '^(DB_CONNECTION|DB_HOST|DB_PORT|DB_USERNAME)=' /home/beastierated/backend/.env
```
Expect `DB_CONNECTION=mysql` (steps below assume MySQL). If it's `pgsql`, swap the
DB-create + `.env` DB block for Postgres equivalents. Do NOT reuse bstg's
DB_DATABASE/DB_USERNAME — backend-review gets its own.

## 1. DNS (hosting/registrar panel — not a repo change)

Add an **A record**: `review-bstg.beastierated.com → SERVER_IP`. Must resolve
before certbot (step 6). `dig +short review-bstg.beastierated.com` should return SERVER_IP.

## 2. Clone the branch into a SEPARATE dir

```bash
cd /home/beastierated
git clone -b carousel-data https://github.com/Mark-Simister/backend.git backend-review
cd backend-review
```
(If the repo is private, use the same credential/deploy method the existing
`/home/beastierated/backend` checkout uses.)

## 3. Composer install

```bash
composer install --no-interaction --prefer-dist --optimize-autoloader
```

## 4. Fresh empty database (MySQL) — separate from bstg

```bash
sudo mysql <<'SQL'
CREATE DATABASE beastie_review CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'beastie_review'@'localhost' IDENTIFIED BY '<DB_PASS>';
GRANT ALL PRIVILEGES ON beastie_review.* TO 'beastie_review'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 5. Separate .env

```bash
cp .env.example .env      # do NOT copy bstg's .env
php artisan key:generate  # its own APP_KEY
```
Then edit `.env`:
```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://review-bstg.beastierated.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=beastie_review
DB_USERNAME=beastie_review
DB_PASSWORD=<DB_PASS>

CACHE_STORE=file
SESSION_DRIVER=file

# Public review pages — STAGING: noindex + Disallow, review-visible statuses
PUBLIC_REVIEW_STATUSES=ready_for_review,published
PUBLIC_REVIEW_INDEXABLE=false
PUBLIC_REVIEW_CACHE_TTL=0
```

## 6. Migrate, import, caches

```bash
php artisan migrate --force
php artisan db:seed --class=PublishedReviewPayloadSeeder --force   # reads committed published_review_payloads.json

php artisan config:cache
php artisan route:cache
php artisan view:cache

sudo chown -R <USER>:<USER> storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```
NB: after any later `.env` change, re-run `php artisan config:cache` (config is cached).

## 7. Apache vhost — NEW file only (bstg's vhost is never opened)

`sudo nano /etc/apache2/sites-available/review-bstg.beastierated.com.conf`:
```apache
<VirtualHost *:80>
    ServerName review-bstg.beastierated.com
    DocumentRoot /home/beastierated/backend-review/public

    <Directory /home/beastierated/backend-review/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/review-bstg-error.log
    CustomLog ${APACHE_LOG_DIR}/review-bstg-access.log combined
</VirtualHost>
```
```bash
sudo a2ensite review-bstg.beastierated.com.conf
sudo apache2ctl configtest          # MUST print "Syntax OK" before reloading
sudo systemctl reload apache2        # graceful reload — does NOT drop bstg
```

## 8. SSL via certbot (only the new host)

```bash
sudo certbot --apache -d review-bstg.beastierated.com
```
certbot appends a `:443` vhost for this host only; it does not modify bstg.

## 9. Validate ON review-bstg (staging is noindex, safe)

```bash
SLUG=vecomfy-fleece-lining-extra-warm-dog-hoodie-in-winter-small-dog-jacket-puppy-coats-with-hooded-red-xs-b07hcb1jps
curl -sL https://review-bstg.beastierated.com/review/$SLUG -o out.html
grep -Eic "BeastieScore|application/ld\+json|<h1|Affiliate|Product|Vecomfy" out.html   # expect several hits, NOT just <div id="root">
grep -o 'rel="canonical" href="[^"]*"' out.html                                        # expect https://review-bstg.beastierated.com/review/<slug>
curl -I https://review-bstg.beastierated.com/sitemap.xml                               # 200 + application/xml
curl -sL https://review-bstg.beastierated.com/robots.txt                               # staging → "Disallow: /"
```

## 10. (LATER — only after validation) route au.fstg /review/* here

In the `au.fstg.beastierated.com` vhost (separate task, separate change), reverse-proxy
`/review/*`, `/sitemap.xml`, `/robots.txt` to `review-bstg` with **`ProxyPreserveHost On`**
so the canonical becomes `au.fstg…`. Not part of this deploy.

---

## Rollback (each step independent; bstg untouched throughout)

```bash
# a) Apache: disable + remove the vhost, reload
sudo a2dissite review-bstg.beastierated.com.conf
sudo rm /etc/apache2/sites-available/review-bstg.beastierated.com.conf
sudo rm -f /etc/apache2/sites-available/review-bstg.beastierated.com-le-ssl.conf
sudo apache2ctl configtest && sudo systemctl reload apache2

# b) TLS cert
sudo certbot delete --cert-name review-bstg.beastierated.com

# c) Code
rm -rf /home/beastierated/backend-review

# d) Database
sudo mysql -e "DROP DATABASE IF EXISTS beastie_review; DROP USER IF EXISTS 'beastie_review'@'localhost'; FLUSH PRIVILEGES;"

# e) DNS: remove the review-bstg A record in the hosting/registrar panel
```
bstg.beastierated.com is never modified, so it needs no rollback.
```
```
